<?php

namespace App\Controller;

use App\Command\CrawlPhpManualDocCommand;
use App\Command\CrawlPhpManualTopicsCommand;
use App\Command\ReformulateTextToMcqCommand;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;


/**
 * PhpCrawlController — triggers the PHP data ingestion pipeline via HTTP.
 *
 * Exposes the three pipeline steps (topics → links → MCQs) as HTTP endpoints
 * so they can be called from the front-end during the "no quiz found" flow.
 * This mirrors CrawlController for Symfony, but without a version parameter.
 */
#[Route('/php')]
class PhpCrawlController extends AbstractController
{
    /**
     * Step 1 — Stores the curated list of PHP manual topics.
     *
     * @throws ExceptionInterface
     */
    #[Route('/execute-crawl-topics-command', name: 'app_php_execute_crawl_topics_command', methods: ['GET'])]
    public function executeCrawlTopicsCommand(CrawlPhpManualTopicsCommand $command): JsonResponse
    {
        return $this->runCommand($command, []);
    }

    /**
     * Step 2 — Crawls the PHP manual and collects documentation links per topic.
     *
     * @throws ExceptionInterface
     */
    #[Route('/execute-crawl-doc-command', name: 'app_php_execute_crawl_doc_command', methods: ['GET'])]
    public function executeCrawlDocCommand(CrawlPhpManualDocCommand $command): JsonResponse
    {
        return $this->runCommand($command, []);
    }

    /**
     * Step 3 — Generates MCQs from the collected documentation links.
     *
     * @throws ExceptionInterface
     */
    #[Route('/execute-mcq-command', name: 'app_php_execute_mcq_command', methods: ['GET'])]
    public function executeMcqCommand(ReformulateTextToMcqCommand $command): JsonResponse
    {
        return $this->runCommand($command, ['source' => 'php']);
    }

    // ── Private helpers ─────────────────────────────────────────────────────

    /**
     * Runs a console command with the provided arguments and returns a JSON response.
     *
     * @param array<string, mixed> $arguments
     * @throws ExceptionInterface
     */
    private function runCommand(Command $command, array $arguments): JsonResponse
    {
        $input = new ArrayInput($arguments);
        $output = new BufferedOutput();

        try {
            $returnCode = $command->run($input, $output);

            if ($returnCode === Command::SUCCESS) {
                return $this->json(['success' => true, 'output' => $output->fetch()]);
            }

            return $this->json([
                'success' => false,
                'error'   => 'Command failed with code: ' . $returnCode,
                'output'  => $output->fetch(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Command error: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}

