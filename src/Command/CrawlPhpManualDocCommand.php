<?php

namespace App\Command;

use App\Repository\MongoDBQueryBuilder;
use App\Technology\Php\PhpManualDocSource;
use MongoDB\Client as MongoClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * CrawlPhpManualDocCommand — crawls PHP manual section pages and extracts sub-page links.
 *
 * For each topic stored by CrawlPhpManualTopicsCommand, this command visits
 * https://www.php.net/manual/en/{slug}.php and extracts links to sub-pages
 * (e.g. language.types.integer, language.types.string…).
 *
 * php.net serves static HTML so a lightweight HTTP client (file_get_contents + DOMDocument)
 * is used instead of Panther/Selenium — faster and more reliable for static pages.
 */
#[AsCommand(
    name: 'app:crawl:php-manual-doc',
    description: 'Crawls the PHP manual and collects documentation links for each topic.'
)]
class CrawlPhpManualDocCommand extends Command
{
    private const BASE_URL = 'https://www.php.net/manual/en/';
    private const HTTP_TIMEOUT = 30;
    private const MAX_LINKS_PER_TOPIC = 8;
    private const USER_AGENT = 'Mozilla/5.0 (compatible; CertiBot/1.0; +https://github.com/certibot)';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $mongoDbUrl,
        private readonly PhpManualDocSource $phpManualDocSource,
    ) {
        parent::__construct();
    }

    /**
     * Executes the command to crawl PHP manual documentation links.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (empty($this->mongoDbUrl)) {
            $io->error("MongoDB URL is not set.");

            return Command::FAILURE;
        }

        $topics = $this->fetchTopicsFromDatabase($io);
        if (empty($topics)) {
            return Command::FAILURE;
        }

        try {
            $this->crawlTopicsLinks($topics, $io);
            $io->success('Successfully crawled the PHP manual documentation.');

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logger->error('CrawlPhpManualDocCommand failed: ' . $e->getMessage());
            $io->error('An error occurred: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Reads the stored topics list from MongoDB.
     *
     * @return array<array{slug: string, title: string, url: string}>
     */
    private function fetchTopicsFromDatabase(SymfonyStyle $io): array
    {
        $queryBuilder = new MongoDBQueryBuilder($this->mongoDbUrl, $this->phpManualDocSource->getDatabaseName());
        $queryBuilder->selectCollection($this->phpManualDocSource->getTopicsCollectionName());

        $result = json_decode(json_encode($queryBuilder->find(null)->toArray()), true);

        if (empty($result) || empty($result[0]['topics'])) {
            $io->error(
                'No PHP topics found in the database. ' .
                'Please run app:crawl:php-manual-topics first.'
            );

            return [];
        }

        return $result[0]['topics'];
    }

    /**
     * For each topic, crawls the PHP manual section page and persists the found links.
     *
     * @param array<array{slug: string, title: string, url: string}> $topics
     */
    private function crawlTopicsLinks(array $topics, SymfonyStyle $io): void
    {
        $mongoClient = new MongoClient($this->mongoDbUrl);
        $collection = $mongoClient->selectCollection(
            $this->phpManualDocSource->getDatabaseName(),
            $this->phpManualDocSource->getLinksCollectionName()
        );
        $collection->drop();

        foreach ($topics as $topic) {
            $links = $this->extractLinksFromPage($topic['url'], $topic['slug'], $io);

            if (empty($links)) {
                $io->warning(sprintf('No links found for topic: %s (%s)', $topic['title'], $topic['slug']));
                continue;
            }

            $collection->insertOne([
                'section'    => $topic['title'],
                'slug'       => $topic['slug'],
                'links'      => $links,
                'scraped_at' => (new \DateTime())->format('Y-m-d H:i:s'),
            ]);

            $io->success(sprintf('Crawled "%s": %d links found.', $topic['title'], count($links)));
        }
    }

    /**
     * Fetches a PHP manual section page and extracts links to its sub-pages.
     *
     * @return array<array{title: string, url: string}>
     */
    private function extractLinksFromPage(string $url, string $sectionSlug, SymfonyStyle $io): array
    {
        $html = $this->fetchPageHtml($url);
        if ($html === null) {
            $io->error("Failed to fetch page: {$url}");

            return [];
        }

        $dom = new \DOMDocument();
        @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($dom);

        // PHP manual "Table of Contents" lists sub-pages in .chunklist elements
        $nodes = $xpath->query('//ul[contains(@class,"chunklist")]//a[@href]');

        if ($nodes === false || $nodes->length === 0) {
            // Fallback: any internal link starting with the section prefix
            $nodes = $xpath->query('//div[@id="layout-content"]//a[@href]');
        }

        $links = [];
        $seen = [];
        $sectionPrefix = str_replace('.', '.', $sectionSlug) . '.';

        /** @var \DOMElement $node */
        foreach ($nodes as $node) {
            if (count($links) >= self::MAX_LINKS_PER_TOPIC) {
                break;
            }

            $href = $node->getAttribute('href');
            $title = trim($node->textContent);

            if (empty($href) || empty($title)) {
                continue;
            }

            // Normalise to absolute URL
            if (!str_starts_with($href, 'http')) {
                $href = self::BASE_URL . ltrim($href, '/');
            }

            // Keep only sub-pages of this section (e.g. language.types.integer.php)
            $basename = basename(parse_url($href, PHP_URL_PATH) ?? '');
            $baseName = str_replace('.php', '', $basename);
            if (!str_starts_with($baseName, $sectionPrefix)) {
                continue;
            }

            if (isset($seen[$href])) {
                continue;
            }

            $seen[$href] = true;
            $links[] = ['title' => $title, 'url' => $href];
        }

        return $links;
    }

    /**
     * Fetches raw HTML from a URL using a lightweight HTTP context.
     */
    private function fetchPageHtml(string $url): ?string
    {
        $context = stream_context_create([
            'http' => [
                'method'     => 'GET',
                'user_agent' => self::USER_AGENT,
                'timeout'    => self::HTTP_TIMEOUT,
                'header'     => "Accept: text/html\r\n",
            ],
        ]);

        $html = @file_get_contents($url, false, $context);

        if ($html === false) {
            $this->logger->error("HTTP fetch failed for: {$url}");

            return null;
        }

        return $html;
    }
}

