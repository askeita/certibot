<?php

namespace App\Command;

use App\Repository\MongoDBQueryBuilder;
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
     * @param string $chromeDriverPath
     */
    public function __construct(LoggerInterface $logger,
                                private readonly string $mongoDbUrl,
                                private readonly string $chromeDriverPath)
    {
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
        $this->addArgument("version", InputArgument::REQUIRED, "The Symfony version to crawl (must be a number between 3 and 7).");
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
        if (!ctype_digit($argVersion) || (int)$argVersion < 3 || (int)$argVersion > 7) {
            $io->error("The version must be a number between 3 and 7.");

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
        $queryBuilder = (new MongoDBQueryBuilder($this->mongoDbUrl, "symfony_certification"))
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

        if (empty($this->chromeDriverPath)) {
            $message = "ChromeDriver path is not set. Please configure the 'CHROMEDRIVER_PATH' environment variable.";
            $this->logger->error($message);
            throw new \Exception($message);
        }

        $client = Client::createChromeClient($this->chromeDriverPath, [
            '--headless',
            '--disable-dev-shm-usage',
            '--no-sandbox'
        ]);

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
     */
    private function processSection(Client $client, int $version, string $section, $topicsLinksCollection, SymfonyStyle $io): void
    {
        $crawler = $client->request("GET", "https://symfony.com/doc/$version.0/index.html");

        // Find the search input field, fill it with the section title and submit the search form
        $searchInput = $crawler->filter('input[type="search"]')->first();
        $searchInput->sendKeys($section);
        $searchInput->submit();

        // Wait for the search results to load
        try {
            $resultsCrawler = $client->waitFor('.search-results');
        } catch (NoSuchElementException $e) {
            $io->error("No search results found for topic: " . json_encode($section) . " - " . $e->getMessage());

            return;
        } catch (TimeoutException $e) {
            $io->error("Timeout while waiting for search results for topic: " . json_encode($section) . " - " . $e->getMessage());

            return;
        } catch (\Exception $e) {
            $io->error("An error occurred while waiting for search results: " . $e->getMessage());

            return;
        }

        // Get the search results
        $links = $resultsCrawler->filter('.search-results .search-result')->each(function ($node) {
            static $counter = 0;

            if ($counter > 3 || empty($node)) {
                return [];
            }

            $title = $node->filter('.search-result-title')->text();
            $url = $node->filter("a")->first()->attr("href");
            $counter++;

            return [
                "title" => $title,
                "url" => $url,
            ];
        });

        if (empty($links)) {
            $io->error("No links found for topic: " . json_encode($section));
            return;
        }

        // Insert the links into the corresponding MongoDB collection
        $topicsLinksCollection->insertOne([
            'section' => $section,
            'links' => $links,
            'scraped_at' => (new \DateTime())->format('Y-m-d H:i:s')
        ]);

        $io->success("Finished crawling the Symfony documentation website for topic: " . json_encode($section));
    }

}
