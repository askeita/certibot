<?php

namespace App\Command;

use App\Technology\Php\PhpManualDocSource;
use MongoDB\Client as MongoClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * CrawlPhpManualTopicsCommand — stores a curated list of PHP manual sections.
 *
 * Unlike Symfony (where topics come from a live certification website), PHP
 * has no official certification. This command saves a pedagogically curated
 * set of PHP manual sections as the "topics" that subsequent commands will
 * use to crawl documentation links and generate MCQs.
 *
 * The topic list is defined in PhpManualDocSource::TOPICS and can be extended
 * without modifying this command.
 */
#[AsCommand(
    name: 'app:crawl:php-manual-topics',
    description: 'Stores the curated list of PHP manual sections used as quiz topics.'
)]
class CrawlPhpManualTopicsCommand extends Command
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly string $mongoDbUrl,
        private readonly PhpManualDocSource $phpManualDocSource,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if (empty($this->mongoDbUrl)) {
            $io->error("MongoDB URL is not set. Please configure the 'MONGODB_URL' environment variable.");

            return Command::FAILURE;
        }

        try {
            $topics = $this->buildTopicsList();
            $this->saveTopicsToDatabase($topics);

            $io->success(sprintf('Saved %d PHP manual topics to the database.', count($topics)));
            foreach ($topics as $topic) {
                $io->writeln(sprintf('  - [%s] %s', $topic['slug'], $topic['title']));
            }

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->logger->error('CrawlPhpManualTopicsCommand failed: ' . $e->getMessage());
            $io->error('An error occurred: ' . $e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Builds the structured topics list from the curated constants.
     *
     * @return array<array{slug: string, title: string, url: string}>
     */
    private function buildTopicsList(): array
    {
        $topics = [];
        foreach (PhpManualDocSource::TOPICS as $slug => $title) {
            $topics[] = [
                'slug'  => $slug,
                'title' => $title,
                'url'   => sprintf('https://www.php.net/manual/en/%s.php', $slug),
            ];
        }

        return $topics;
    }

    /**
     * Persists the topics list to MongoDB.
     */
    private function saveTopicsToDatabase(array $topics): void
    {
        $mongoClient = new MongoClient($this->mongoDbUrl);
        $collection = $mongoClient->selectCollection(
            $this->phpManualDocSource->getDatabaseName(),
            $this->phpManualDocSource->getTopicsCollectionName()
        );
        $collection->drop();
        $collection->insertOne([
            'source'     => $this->phpManualDocSource->getDocumentLabel(),
            'topics'     => $topics,
            'scraped_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);
    }
}

