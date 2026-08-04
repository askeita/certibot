<?php

namespace App\Command;

use App\Repository\MongoDBQueryBuilder;
use App\Service\BrowserClientService;
use Exception;
use InvalidArgumentException;
use MongoDB\Client as MongoDBClient;
use OpenAI;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;


/**
 * ReformulateTextToMcqCommand handles the reformulation of text into multiple-choice questions using the OpenAI API.
 */
#[AsCommand(
    name: 'app:reformulate-text-to-mcq',
    description: 'Reformulates the text from a \<p\> tag into a multiple-choice question using GPT-4 API.',
)]
class ReformulateTextToMcqCommand extends Command
{
    private LoggerInterface $logger;

    /**
     * @var string|mixed
     */
    private string $openAIApiKey;

    /**
     * @var string|mixed
     */
    private string $openAIModel;

    /**
     * @var string|mixed
     */
    private int $maxTokens;

    /**
     * @var float|mixed
     */
    private mixed $temperature;

    /**
     * @var int|mixed
     */
    private mixed $topP;

    /**
     * @var int|mixed
     */
    private mixed $nValue;

    /**
     * Constructor
     *
     * @param LoggerInterface $logger
     * @param string $mongoDbUrl
     * @param array $openAIConfig
     * @param BrowserClientService $browserClientService
     */
    public function __construct(
        LoggerInterface $logger,
        private readonly string $mongoDbUrl,
        array $openAIConfig,
        private readonly BrowserClientService $browserClientService
    ) {
        parent::__construct();
        $this->logger = $logger;
        $this->openAIApiKey = $openAIConfig['api_key'] ?? '';
        $this->openAIModel = $openAIConfig['model'] ?? 'gpt-4o';
        $this->maxTokens = $openAIConfig['max_tokens'] ?? 200;
        $this->temperature = $openAIConfig['temperature'] ?? 0.5;
        $this->topP = $openAIConfig['top_p'] ?? 1;
        $this->nValue = $openAIConfig['n'] ?? 1;
    }

    /**
     * Configure
     *
     * @return void
     */
    protected function configure(): void
    {
        $this->addArgument("version", InputArgument::REQUIRED, "The text of the Symfony version to reformulate (must be a number between 3 and 7).");
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
        $this->logger->info("Starting the command to reformulate text into multiple-choice questions.");
        $io = new SymfonyStyle($input, $output);

        // Use environment variable for the OPENAI_API_KEY
        if (!$this->openAIApiKey) {
            $this->logger->error("OPENAI_API_KEY environment variable is not defined in the environment variables.");
            $io->error("OPENAI_API_KEY environment variable is not defined in the environment variables.");

            return Command::FAILURE;
        }

        try {
            $version = $this->validateVersion($input, $io);
            $links = $this->fetchLinksFromDatabase($version, $io);

            if (empty($links)) {
                return Command::FAILURE;
            }

            $questions = $this->generateQuestionsFromLinks($links, $io);
            if (empty($questions)) {
                $message = "No questions generated.";
                $this->logger->error($message);
                $io->error($message);

                return Command::FAILURE;
            }

            $this->saveQuestionsToDatabase($version, $questions);
            $this->logger->info("Multiple-choice questions generated successfully for Symfony $version");
            $io->success('Multiple-choice question generated!');

            return Command::SUCCESS;
        } catch (Exception $e) {
            $this->logger->error('Error occurred for the link: '.$e->getMessage());
            $io->error('An error occurred: '.$e->getMessage());

            return Command::FAILURE;
        }
    }

    /**
     * Validates the Symfony version argument.
     *
     * @param InputInterface $input
     * @param SymfonyStyle $io
     * @return int
     */
    private function validateVersion(InputInterface $input, SymfonyStyle $io): int
    {
        $argVersion = $input->getArgument('version');
        $version = (int)trim($argVersion);
        if (!ctype_digit($argVersion) || $version < 6 || $version > 8) {
            $message = "Invalid Symfony version. Please provide an integer between 6 and 8.";
            $this->logger->warning($message);
            $io->error($message);
            throw new InvalidArgumentException($message);
        }

        return $version;
    }

    /**
     * Fetches links from the database for a specific Symfony version.
     *
     * @param int $version
     * @param SymfonyStyle $io
     * @return array
     */
    private function fetchLinksFromDatabase(int $version, SymfonyStyle $io): array
    {
        $queryBuilder = new MongoDBQueryBuilder($this->mongoDbUrl, "symfony_certification")
            ->selectCollection("sf{$version}_topics_links");
        $linksCollection = json_decode(json_encode(
            $queryBuilder
                ->find(null)
                ->toArray()
        ), true);

        if (empty($linksCollection["links"])) {
            $message = "No links found for Symfony version $version. Please check the database and eventually run the `CrawlSymfonyDocCommand` command.";
            $this->logger->error($message);
            $io->error($message);
        }

        $linksUrls = [];
        //foreach ($linksCollection as $link) {
            array_walk_recursive($linksCollection, function ($v) use (&$linksUrls) {
                if (str_starts_with($v, "https://")) {
                    $linksUrls[] = $v;
                }
            });
        //}
        shuffle($linksUrls);

        return $linksUrls;
    }

    /**
     * Generates questions from links using the OpenAI API.
     *
     * @param array $links
     * @param SymfonyStyle $io
     * @return array
     */
    private function generateQuestionsFromLinks(array $links, SymfonyStyle $io): array
    {
        $openAIClient = OpenAI::client($this->openAIApiKey);
        $questions = [];
        $consecutiveErrors = 0;
        $maxConsecutiveErrors = 5;

        // Create browser client once for all requests
        $browserClient = null;
        try {
            $browserClient = $this->browserClientService->createClient();
        } catch (\RuntimeException $e) {
            $io->error("Failed to create browser client: " . $e->getMessage());
            return [];
        }

        foreach ($links as $link) {
            try {
                // Add delay between HTTP requests to Symfony.com to avoid rate limiting
                // This is BEFORE fetching, not after
                if (count($questions) > 0) {
                    $io->note("Waiting 3 seconds before next request to avoid rate limiting...");
                    sleep(3);
                }

                $text = $this->fetchTextFromLink($link, $browserClient, $io);
                if (!$text) {
                    $consecutiveErrors++;
                    if ($consecutiveErrors >= $maxConsecutiveErrors) {
                        $io->warning('Too many consecutive errors. Stopping to avoid further issues.');
                        break;
                    }
                    continue;
                }

                // Text successfully fetched, reset error counter
                $consecutiveErrors = 0;

                $question = $this->generateQuestionFromText($openAIClient, $text, $link, $io);
                if ($question) {
                    $questions[] = $question;
                    $io->success("Question #" . count($questions) . " generated successfully from: $link");
                }

                if (count($questions) === 75) {
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

        // Close browser client
        if ($browserClient) {
            try {
                $browserClient->quit();
            } catch (\Exception $e) {
                $this->logger->warning("Failed to close browser client: " . $e->getMessage());
            }
        }

        return $questions;
    }

    /**
     * Fetches text from a link using the Symfony Panther client.
     *
     * @param string $link
     * @param \Symfony\Component\Panther\Client $client
     * @param SymfonyStyle $io
     * @return string|null
     */
    private function fetchTextFromLink(string $link, Client $client, SymfonyStyle $io): ?string
    {
        $this->logger->debug("Processing link: " . $link);

        $maxRetries = 3;
        $retryDelay = 5;

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                // Fetch the content of the links
                $crawler = $client->request("GET", $link);

                // Check if page contains rate limit message
                $pageText = $crawler->filter('body')->text();
                if (str_contains(strtolower($pageText), 'rate limit') ||
                    str_contains(strtolower($pageText), 'too many requests')) {
                    throw new \RuntimeException("Request rate limit has been exceeded.");
                }

                // Successfully fetched, now extract text
                $class = substr_count($link, '#') > 1 ? 'section' : '';

                $pElements = $crawler->filter('div' . $class . ' > p');
                $pTagsCount = $pElements->count();
                if ($pTagsCount < 1) {
                    $pElements = $crawler->filter('p');
                    $pTagsCount = $pElements->count();

                    if ($pTagsCount < 1) {
                        $io->warning('No <p> tags found in the link: ' . $link);
                        return null;
                    }
                }

                $randomIndex = $pTagsCount > 1 ? rand(0, $pTagsCount - 1) : 0;
                $pTag = $pElements->eq($randomIndex);
                $text = $pTag->text();

                if (empty($text) || strlen($text) < 50) {
                    $io->warning("Text too short or empty in the link: $link");
                    return null;
                }

                return $text;

            } catch (\RuntimeException $e) {
                $errorMsg = $e->getMessage();
                $this->logger->error("Browser client error (attempt {$attempt}/{$maxRetries}): " . $errorMsg);

                // Check if it's a rate limit error
                if (str_contains(strtolower($errorMsg), 'rate limit') ||
                    str_contains(strtolower($errorMsg), 'too many requests')) {

                    if ($attempt < $maxRetries) {
                        $waitTime = $retryDelay * $attempt;
                        $io->warning("Rate limit detected for {$link}. Waiting {$waitTime} seconds before retry {$attempt}/{$maxRetries}...");
                        sleep($waitTime);
                        continue;
                    } else {
                        $io->error("Rate limit error persists after {$maxRetries} attempts for: {$link}");
                        return null;
                    }
                }

                // Other runtime errors
                if ($attempt < $maxRetries) {
                    $io->warning("Error fetching {$link}, retrying in {$retryDelay} seconds... (attempt {$attempt}/{$maxRetries})");
                    sleep($retryDelay);
                    continue;
                }

                $io->error("Failed to fetch {$link} after {$maxRetries} attempts: " . $errorMsg);
                return null;

            } catch (\Exception $e) {
                $this->logger->error("Unexpected error: " . $e->getMessage());
                $io->error("Unexpected error for {$link}: " . $e->getMessage());
                return null;
            }
        }

        return null;
    }

    /**
     * Generates a question from the text using the OpenAI API.
     *
     * @param OpenAI\Client $openAIClient
     * @param string $text
     * @param string $link
     * @param SymfonyStyle $io
     * @return array|null
     * @throws \JsonException
     */
    private function generateQuestionFromText(OpenAI\Client $openAIClient, string $text, string $link, SymfonyStyle $io): ?array
    {
        $this->logger->info("Call of OpenAI API for the link: ".$link);
        // Use GPT-4 API to reformulate the text into a multiple-choice question
        $response = $openAIClient->chat()->create([
            'model' => $this->openAIModel,
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful assistant.'],
                [
                    'role' => 'user',
                    'content' => "Reformulate the following text into a multiple-choice 
                                        question:\n\n$text. Give the correct answer to that multiple-choice question. 
                                        In the content of the response message, only return a JSON array with three 
                                        keys: 'question', 'choices' and 'answer.' The 'question' key should contain the 
                                        question. The 'choices' key should contain the choices starting by A), B), C) 
                                        or D), and separated by a question mark. The 'answer' key should contain the 
                                        correct answer. The answer must be in the format: Correct Answer: <answer>. 
                                        For example, Correct Answer: A."
                ],
            ],
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
            'top_p' => $this->topP,
            'n' => $this->nValue,
        ]);

        $this->logger->debug("Response received from OpenAI API: " . json_encode($response));
        $content = $response->choices[0]->message->content;
        $jsonContent = $content;
        if (!str_starts_with($jsonContent, "{")) {
            preg_match('/({[\s\S]*})/', $content, $matches);
            if (!empty($matches[1])) {
                $jsonContent = $matches[1];
            } else {
                $io->error('Invalid response format for the link: ' . $link);
                return null;
            }
        }
        $data = json_decode($jsonContent, true, 512, JSON_THROW_ON_ERROR);

        if (!isset($data["choices"])) {
            $io->error("No choices found in the response for the link: ".$link);
            return null;
        } elseif (!isset($data["answer"])) {
            $io->error('No answer found in the response for the link: '.$link);
            return null;
        }

        return [
            'link' => $link,
            'question' => $data['question'],
            'choices' => $data['choices'],
            'answer' => trim(explode('Correct Answer:', $data['answer'])[1]),
        ];
    }

    /**
     * Saves the generated questions to the database.
     *
     * @param int $version
     * @param array $questions
     * @return void
     */
    private function saveQuestionsToDatabase(int $version, array $questions): void
    {
        $mongoClient = new MongoDBClient($this->mongoDbUrl, [], []);
        $collection = $mongoClient->selectCollection("symfony_certification", "sf{$version}_mcq_gpt-4o");
        $collection->drop();

        $collection->insertOne([
            "version" => "Symfony $version",
            "mcq" => $questions,
            "scraped_at" => new \DateTime()->format("Y-m-d H:i:s"),
        ]);
    }

}
