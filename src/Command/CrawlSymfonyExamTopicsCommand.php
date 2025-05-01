<?php

namespace App\Command;

use Exception;
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
 * CrawlSymfonyExamTopicsCommand handles the crawling of the Symfony certification website.
 */
#[AsCommand(
    name: 'app:crawl:symfony-exam-topics',
    description: 'Crawls the Symfony certification website and retrieves the list of exam topics for a specific Symfony version.'
)]
class CrawlSymfonyExamTopicsCommand extends Command
{
    /**
     * @var LoggerInterface
     */
    private LoggerInterface $logger;


    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     * @param string $mongoDbUrl
     * @param string $chromeDriverPath
     */
    public function __construct(LoggerInterface         $logger,
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
     * @param InputInterface $input         Input interface
     * @param OutputInterface $output       Output interface
     * @return int                          returns the exit code
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (empty($this->mongoDbUrl)) {
            $io->error("MongoDB URL is not set. Please configure the 'MONGODB_URL' environment variable.");

            return Command::FAILURE;
        }

        try {
            $version = $this->validateVersion($input->getArgument('version'), $io);
            $topics = $this->crawlExamTopics($version);
            $this->saveExamTopicsToDatabase($version, $topics);

            $io->success("Symfony $version Exam Topics:");
            foreach ($topics as $topic) {
                $io->writeln("- " . json_encode($topic));
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logger->error("Element not found: " . $e->getMessage());
            $io->error("An element was not found on the page. Please restart and eventually check the page structure.");

            return Command::FAILURE;
        }
    }

    /**
     * Validates the Symfony version argument.
     *
     * @param mixed $argVersion     version argument from the command line
     * @param SymfonyStyle $io      SymfonyStyle instance for output
     * @return int                  validated version
     */
    private function validateVersion(mixed $argVersion, SymfonyStyle $io): int
    {
        $version = (int) trim($argVersion);

        if (!ctype_digit($argVersion) || $version < 3 || $version > 7) {
            $io->error("Invalid Symfony version. Please provide a number between 3 and 7.");

            return Command::FAILURE;
        }

        return $version;
    }


    /**
     * Crawls the Symfony certification website for exam topics.
     *
     * @param int $version          Symfony version to crawl
     * @throws TimeoutException     Timeout exception while waiting for an element
     * @throws Exception            exception during crawling
     */
    private function crawlExamTopics(int $version): array|int
    {
        if (empty($this->chromeDriverPath)) {
            $this->logger->error("ChromeDriver path is not set. Please configure the 'CHROMEDRIVER_PATH' environment variable.");

            return Command::FAILURE;
        }

        $client = Client::createChromeClient($this->chromeDriverPath, [
            '--headless',
            '--no-sandbox',
            '--disable-dev-shm-usage'
        ]);
        $crawler = $client->request("GET", "https://certification.symfony.com/exams/symfony.html");

        try {
            $button = $crawler->selectButton("Symfony $version");
            $client->executeScript("arguments[0].click();", [$button->getElement(0)]);

            $crawler = $client->waitFor("#symfony$version");
            return $crawler
                ->filter("#symfony$version ul.list-of-exam-topics > li")
                ->each(function ($node) {
                    $parentTopic = trim($node->filter("h3")->text());

                    if (!empty($parentTopic)) {
                        $childTopics[$parentTopic] = $node->filter("ul > li")->each(function ($child) {
                            return trim($child->text());
                        });
                    }

                    return $childTopics ?? [];
                });
        } catch (NoSuchElementException|\Symfony\Component\HttpClient\Exception\TimeoutException $e) {
            throw new Exception("Error during crawling: " . $e->getMessage());
        }
    }

    /**
     * Saves the crawled exam topics to the database.
     *
     * @param int $version      Symfony version
     * @param array $topics     the crawled exam topics
     * @return void
     */
    private function saveExamTopicsToDatabase(int $version, array $topics): void
    {
        // Create a new client and connect to the server
        $mongoClient = new MongoClient($this->mongoDbUrl, [], []);

        $collection = $mongoClient->selectCollection("symfony_certification", "sf{$version}_exam_topics");
        $collection->drop();
        $collection->insertOne([
            "version" => "Symfony $version",
            "topics" => $topics,
            "scraped_at" => (new \DateTime())->format("Y-m-d H:i:s"),
        ]);
    }

}
