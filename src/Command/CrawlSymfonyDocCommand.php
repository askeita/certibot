<?php

namespace App\Command;

use App\Repository\MongoDBQueryBuilder;
use App\Service\BrowserClientService;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\TimeoutException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Panther\Client;
use MongoDB\Client as MongoClient;


/**
 * CrawlSymfonyDocCommand handles the crawling of the Symfony documentation website.
 */
#[AsCommand(
    name: 'app:crawl:symfony-doc',
    description: 'Crawls the Symfony documentation website and retrieves the links related to an exam topic.'
)]
class CrawlSymfonyDocCommand extends Command
{
    /**
     * @var LoggerInterface $logger logger instance
     */
    private LoggerInterface $logger;

    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     * @param string $mongoDbUrl
     * @param BrowserClientService $browserClientService
     */
    public function __construct(
        LoggerInterface $logger,
        private readonly string $mongoDbUrl,
        private readonly BrowserClientService $browserClientService,
    ) {
        parent::__construct();
        $this->logger = $logger;
    }

    /**
     * Configures command
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->addArgument("version", InputArgument::REQUIRED, "The Symfony version to crawl (must be a number between 6 and 8).");
    }

    /**
     * Executes command
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $version = $this->validateVersion($input, $io);
        if ($version === null) {
            return Command::FAILURE;
        }

        $topicsSections = $this->fetchExamTopics($version, $io);
        if (empty($topicsSections)) {
            return Command::FAILURE;
        }

        try {
            $this->crawlTopicsSections($version, $topicsSections, $io);
            $io->success("Successfully crawled the Symfony documentation website for version $version.");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $io->error("An error occurred: " . $e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Validates the version argument
     *
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return int|null
     */
    private function validateVersion(InputInterface $input, SymfonyStyle $io): ?int
    {
        $argVersion = $input->getArgument('version');
        if (!ctype_digit($argVersion) || (int)$argVersion < 6 || (int)$argVersion > 8) {
            $io->error("The version must be an integer between 6 and 8.");

            return null;
        }

        return (int)trim($argVersion);
    }

    /**
     * Fetches the exam topics for a specific Symfony version
     *
     * @param int $version
     * @param SymfonyStyle $io
     * @return array
     */
    private function fetchExamTopics(int $version, SymfonyStyle $io): array
    {
        $queryBuilder = new MongoDBQueryBuilder($this->mongoDbUrl, "symfony_certification")
            ->selectCollection("sf{$version}_exam_topics");
        $topicsCollection = json_decode(json_encode(
            $queryBuilder
                ->find(null)
                ->toArray()
        ), true);

        if (empty($topicsCollection)) {
            $io->error("No exam topics found for Symfony version $version. Please check the database and eventually run the `CrawlSymfonyExamTopicsCommand` command.");

            return [];
        }

        $sections = [];
        array_walk_recursive($topicsCollection[0]["topics"], function ($v) use (&$sections) {
            $sections[] = $v;
        });

        return $sections;
    }

    /**
     * Crawls the Symfony documentation website for the given topics and sections
     *
     * @param int $version
     * @param array $topicsSections
     * @param SymfonyStyle $io
     * @return void
     * @throws \Exception
     */
    private function crawlTopicsSections(int $version, array $topicsSections, SymfonyStyle $io): void
    {
        // Connection to the MongoDB database
        $mongoClient = new MongoClient($this->mongoDbUrl);
        $topicsLinksCollection = $mongoClient->selectCollection("symfony_certification", "sf{$version}_topics_links");
        $topicsLinksCollection->drop();

        $client = $this->browserClientService->createClient();

        foreach ($topicsSections as $section) {
            $this->processSection($client, $version, $section, $topicsLinksCollection, $io);
        }
    }

    /**
     * Processes a section link of the Symfony documentation
     *
     * @param Client $client
     * @param int $version
     * @param string $section
     * @param $topicsLinksCollection
     * @param SymfonyStyle $io
     * @return void
     * @throws NoSuchElementException
     */
    private function processSection(Client $client, int $version, string $section, $topicsLinksCollection, SymfonyStyle $io): void
    {
        $this->logger->debug("Processing section: " . json_encode($section));

        try {
            // Build the Symfony search URL for this topic and version. Current docs use a global search endpoint
            $query = urlencode($section);
            $searchUrl = sprintf('https://symfony.com/search?q=%s&version=%d.0', $query, $version);

            $this->logger->debug("Searching URL: " . $searchUrl);
            $client->request('GET', $searchUrl);

            // Wait until at least one search result item is present.
            // The exact selector may need to be tweaked if Symfony adjusts the markup.
            try {
                $resultsCrawler = $client->waitFor('main article, main .search-result, main li', 45);
            } catch (TimeoutException $e) {
                $io->error('Timeout while waiting for search results for topic: ' . json_encode($section)
                    . ' - Element "main article, main .search-result, main li" not found within 45 seconds. - ' .
                    $e->getMessage());

                return;
            }
        } catch (NoSuchElementException $e) {
            $io->error('No search results page found for topic: ' . json_encode($section) . ' - ' . $e->getMessage());

            return;
        } catch (\Exception $e) {
            $io->error('An error occurred while processing topic ' . json_encode($section) . ': ' . $e->getMessage());

            return;
        }

        // Extract links from the results page.
        // We are intentionally generous with selectors and then filter programmatically.
        $links = $resultsCrawler->filter('main a')->each(function ($node) use ($version) {
            static $counter = 0;

            if ($counter >= 4 || empty($node)) {
                return [];
            }

            $href = $node->attr('href');
            if (empty($href)) {
                return [];
            }

            // Keep only documentation links.
            if (!str_contains($href, '/doc/')) {
                return [];
            }

            $title = trim($node->text(''));
            if ($title === '') {
                return [];
            }

            $counter++;

            // Normalize URL (absolute vs relative).
            if (!str_starts_with($href, 'http')) {
                $href = 'https://symfony.com' . $href;
            }

            // Replace 'current' by the concrete version (e.g. 6.0) in documentation URLs
            $versionString = sprintf('%d.0', $version);
            $href = str_replace('/doc/current/', '/doc/' . $versionString . '/', $href);

            return [
                'title' => $title,
                'url'   => $href,
            ];
        });

        // Remove empty entries.
        $links = array_values(array_filter($links));

        if (empty($links)) {
            $io->error('No links found for topic: ' . json_encode($section));

            return;
        }

        // Insert the links into the corresponding MongoDB collection
        $topicsLinksCollection->insertOne([
            'section'    => $section,
            'links'      => $links,
            'scraped_at' => new \DateTime()->format('Y-m-d H:i:s'),
        ]);

        $this->logger->info("Successfully retrieved search results for topic: " . json_encode($section) . " with " .
            count($links) . " links found.");
        $io->success('Finished crawling the Symfony documentation website for topic: ' . json_encode($section));
    }

}
