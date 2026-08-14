<?php

namespace App\Command;

use App\Core\DocSource\DocSourceInterface;
use App\Core\Mcq\Port\McqGeneratorInterface;
use App\Core\Registry\TechnologyRegistry;
use App\Repository\MongoDBQueryBuilder;
use App\Service\BrowserClientService;
use Exception;
use InvalidArgumentException;
use MongoDB\Client as MongoDBClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;


/**
 * ReformulateTextToMcqCommand — generates MCQs from documentation links.
 *
 * Accepts a `source` argument ('symfony' or 'php') to select the target technology
 * via the TechnologyRegistry, making it fully generic across all registered sources.
 * OpenAI generation is delegated to McqGeneratorInterface (hexagonal output port),
 * allowing the AI provider to be swapped without modifying this command.
 */
#[AsCommand(
    name: 'app:reformulate-text-to-mcq',
    description: 'Generates multiple-choice questions from documentation links using the configured AI model.',
)]
class ReformulateTextToMcqCommand extends Command
{
    private const MAX_QUESTIONS = 75;
    private const MIN_TEXT_LENGTH = 50;
    private const MAX_CONSECUTIVE_ERRORS = 5;

    private int $requestDelay;
    private int $maxRetries;
    private int $retryDelay;

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $mongoDbUrl,
        private readonly McqGeneratorInterface $mcqGenerator,
        private readonly TechnologyRegistry $technologyRegistry,
        private readonly BrowserClientService $browserClientService,
        array $webScrapingConfig = []
    ) {
        parent::__construct();

        // Web scraping configuration to avoid rate limiting
        $this->requestDelay = $webScrapingConfig['request_delay'] ?? 2;
        $this->maxRetries = $webScrapingConfig['max_retries'] ?? 3;
        $this->retryDelay = $webScrapingConfig['retry_delay'] ?? 5;
    }

    protected function configure(): void
    {
        $this
            ->addArgument('source', InputArgument::REQUIRED, 'The technology source slug (e.g. "symfony", "php").')
            ->addArgument('version', InputArgument::OPTIONAL, 'The version identifier (required for versioned sources like Symfony).');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->logger->info('Starting MCQ generation command.');

        try {
            $source = $this->resolveSource($input, $io);
            $identifier = $this->resolveIdentifier($input, $source, $io);
            $links = $this->fetchLinksFromDatabase($source, $identifier, $io);

            if (empty($links)) {
                return Command::FAILURE;
            }

            $questions = $this->generateQuestionsFromLinks($links, $source, $io);

            if (empty($questions)) {
                $io->error('No questions were generated.');

                return Command::FAILURE;
            }

            $this->saveQuestionsToDatabase($source, $identifier, $questions);

            $this->logger->info(sprintf(
                'MCQ generation completed for %s: %d questions saved.',
                $source->getDocumentLabel($identifier),
                count($questions)
            ));
            $io->success(sprintf('%d MCQs generated for %s!', count($questions), $source->getDocumentLabel($identifier)));

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->logger->error('MCQ generation failed: ' . $e->getMessage());
            $io->error('An error occurred: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Resolves and validates the technology source from the 'source' argument.
     */
    private function resolveSource(InputInterface $input, SymfonyStyle $io): DocSourceInterface
    {
        $slug = trim((string) $input->getArgument('source'));

        if (!$this->technologyRegistry->has($slug)) {
            $available = implode(', ', array_keys($this->technologyRegistry->all()));
            $message = "Unknown source \"{$slug}\". Available: {$available}.";
            $io->error($message);
            throw new InvalidArgumentException($message);
        }

        return $this->technologyRegistry->get($slug);
    }

    /**
     * Resolves the version/identifier argument, validating it against the DocSource rules.
     */
    private function resolveIdentifier(InputInterface $input, DocSourceInterface $source, SymfonyStyle $io): mixed
    {
        if (!$source->supportsVersion()) {
            return null;
        }

        $identifier = $input->getArgument('version');
        if ($identifier === null) {
            $message = "The '{$source->getLabel()}' source requires a version argument.";
            $io->error($message);
            throw new InvalidArgumentException($message);
        }

        if (!$source->validateIdentifier($identifier)) {
            $message = "Invalid version '{$identifier}' for {$source->getLabel()}.";
            $io->error($message);
            throw new InvalidArgumentException($message);
        }

        return (int) $identifier;
    }

    /**
     * Reads the documentation links from MongoDB for the given source/identifier.
     *
     * @return string[] Shuffled list of documentation URLs.
     */
    private function fetchLinksFromDatabase(DocSourceInterface $source, mixed $identifier, SymfonyStyle $io): array
    {
        $queryBuilder = new MongoDBQueryBuilder($this->mongoDbUrl, $source->getDatabaseName());
        $queryBuilder->selectCollection($source->getLinksCollectionName($identifier));

        $linksCollection = json_decode(json_encode(
            $queryBuilder->find(null)->toArray()
        ), true);

        if (empty($linksCollection)) {
            $io->error(sprintf(
                'No links found for %s. Please run the crawl-doc command first.',
                $source->getDocumentLabel($identifier)
            ));

            return [];
        }

        $linksUrls = [];
        array_walk_recursive($linksCollection, function ($v) use (&$linksUrls) {
            if (is_string($v) && str_starts_with($v, 'https://')) {
                $linksUrls[] = $v;
            }
        });

        shuffle($linksUrls);

        return $linksUrls;
    }

    /**
     * Iterates over documentation links, fetches page content, and generates MCQs.
     *
     * @return array Generated question entries.
     */
    private function generateQuestionsFromLinks(array $links, DocSourceInterface $source, SymfonyStyle $io): array
    {
        $questions = [];
        $linkCount = 0;
        $consecutiveErrors = 0;
        $maxConsecutiveErrors = self::MAX_CONSECUTIVE_ERRORS;

        foreach ($links as $link) {
            try {
                $linkCount++;

                // Add delay between requests to avoid rate limiting (except for first request)
                if ($linkCount > 1 && !$source->requiresBrowserForContent()) {
                    $io->note("Waiting {$this->requestDelay} seconds before next request to avoid rate limiting...");
                    sleep($this->requestDelay);
                }

                $text = $this->fetchTextFromLink($link, $source, $io);
                if (!$text) {
                    $consecutiveErrors++;
                    if ($consecutiveErrors >= $maxConsecutiveErrors) {
                        $io->warning('Too many consecutive errors. Stopping to avoid further issues.');
                        break;
                    }
                    continue;
                }

                $question = $this->mcqGenerator->generateFromText($text, $link);
                if ($question) {
                    $questions[] = $question;
                    $io->success(sprintf('Question %d/%d generated from: %s', count($questions), self::MAX_QUESTIONS, $link));
                }

                if (count($questions) >= self::MAX_QUESTIONS) {
                    break;
                }
            } catch (\Exception $e) {
                $consecutiveErrors++;
                $errorMessage = $e->getMessage();
                $message = 'Error occurred for the link: '.$link.' - '.$errorMessage;
                $this->logger->error($message);
                $io->error($message);

                // If too many consecutive errors, stop
                if ($consecutiveErrors >= $maxConsecutiveErrors) {
                    $io->warning("Too many consecutive errors ({$consecutiveErrors}). Stopping to avoid further issues.");
                    break;
                }

                // Wait before continuing after an error
                $waitTime = 5;
                $io->note("Waiting {$waitTime} seconds before continuing...");
                sleep($waitTime);
            }
        }

        return $questions;
    }

    /**
     * Fetches paragraph text from a documentation page.
     *
     * Uses a real browser for JavaScript-rendered sites (e.g. symfony.com)
     * and a lightweight HTTP client for static HTML sites (e.g. php.net).
     */
    private function fetchTextFromLink(string $link, DocSourceInterface $source, SymfonyStyle $io): ?string
    {
        if ($source->requiresBrowserForContent()) {
            return $this->fetchTextWithBrowser($link, $io);
        }

        return $this->fetchTextWithHttp($link, $io);
    }

    /**
     * Fetches page content using Panther (for JS-rendered sites).
     */
    private function fetchTextWithBrowser(string $link, SymfonyStyle $io): ?string
    {
        $this->logger->debug("Fetching with browser: {$link}");
        $client = $this->browserClientService->createClient();
        try {
            $crawler = $client->request('GET', $link);
            $class = substr_count($link, '#') > 1 ? 'section' : '';

            $pElements = $crawler->filter("div{$class} > p");
            if ($pElements->count() < 1) {
                $pElements = $crawler->filter('p');
            }

            if ($pElements->count() < 1) {
                $io->error("No <p> tags found in: {$link}");

                return null;
            }

            $randomIndex = $pElements->count() > 1 ? rand(0, $pElements->count() - 1) : 0;
            $text = $pElements->eq($randomIndex)->text();

            if (empty($text) || strlen($text) < self::MIN_TEXT_LENGTH) {
                $io->warning("Text too short in: {$link}");

                return null;
            }

            return $text;
        } finally {
            $client->quit();
        }
    }

    /**
     * Fetches page content using a lightweight HTTP client (for static HTML sites).
     * Includes retry logic with exponential backoff to handle rate limiting.
     */
    private function fetchTextWithHttp(string $link, SymfonyStyle $io): ?string
    {
        $this->logger->debug("Fetching with HTTP: {$link}");

        $attempt = 0;
        $lastError = null;

        while ($attempt < $this->maxRetries) {
            $attempt++;

            try {
                $context = stream_context_create([
                    'http' => [
                        'method'     => 'GET',
                        'user_agent' => 'Mozilla/5.0 (compatible; CertiBot/1.0)',
                        'timeout'    => 30,
                        'header'     => "Accept: text/html\r\n",
                    ],
                ]);

                $html = @file_get_contents($link, false, $context);

                // Check if request was successful
                if ($html === false) {
                    $error = error_get_last();
                    $lastError = $error['message'] ?? 'Unknown error';

                    // Check if it's a rate limit error (HTTP 429 or similar)
                    if (strpos($lastError, '429') !== false || strpos($lastError, 'Too Many Requests') !== false) {
                        if ($attempt < $this->maxRetries) {
                            $backoffDelay = $this->retryDelay * pow(2, $attempt - 1);
                            $io->warning("Rate limit detected for {$link}. Waiting {$backoffDelay} seconds before retry {$attempt}/{$this->maxRetries}...");
                            $this->logger->warning("Rate limit hit for {$link}, retry {$attempt} after {$backoffDelay}s");
                            sleep($backoffDelay);
                            continue;
                        }
                    }

                    throw new \RuntimeException("Failed to fetch {$link}: {$lastError}");
                }

                // Successfully fetched content
                $dom = new \DOMDocument();
                @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
                $xpath = new \DOMXPath($dom);

                $paragraphs = $xpath->query('//div[contains(@class,"description")]//p');
                if ($paragraphs === false || $paragraphs->length === 0) {
                    $paragraphs = $xpath->query('//div[@id="layout-content"]//p');
                }
                if ($paragraphs === false || $paragraphs->length === 0) {
                    $paragraphs = $xpath->query('//p');
                }

                if ($paragraphs === false || $paragraphs->length === 0) {
                    $io->error("No <p> tags found in: {$link}");
                    return null;
                }

                $candidates = [];
                /** @var \DOMElement $p */
                foreach ($paragraphs as $p) {
                    $text = trim($p->textContent);
                    if (strlen($text) >= self::MIN_TEXT_LENGTH) {
                        $candidates[] = $text;
                    }
                }

                if (empty($candidates)) {
                    $io->warning("No usable paragraphs found in: {$link}");
                    return null;
                }

                return $candidates[array_rand($candidates)];

            } catch (\RuntimeException $e) {
                if ($attempt >= $this->maxRetries) {
                    $io->error("Failed to fetch {$link} after {$this->maxRetries} attempts: " . $e->getMessage());
                    $this->logger->error("Max retries exceeded for {$link}: " . $e->getMessage());
                    return null;
                }
            }
        }

        return null;
    }

    /**
     * Persists the generated questions to MongoDB.
     */
    private function saveQuestionsToDatabase(DocSourceInterface $source, mixed $identifier, array $questions): void
    {
        $mongoClient = new MongoDBClient($this->mongoDbUrl);
        $collection = $mongoClient->selectCollection(
            $source->getDatabaseName(),
            $source->getMcqCollectionName($identifier)
        );
        $collection->drop();
        $collection->insertOne([
            'source'     => $source->getDocumentLabel($identifier),
            'mcq'        => $questions,
            'scraped_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);
    }

}
