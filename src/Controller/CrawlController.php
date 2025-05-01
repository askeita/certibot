<?php

namespace App\Controller;

use App\Command\CrawlSymfonyDocCommand;
use App\Command\CrawlSymfonyExamTopicsCommand;
use App\Command\ReformulateTextToMcqCommand;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;


/**
 * CrawlController
 *
 * Handles crawling operations for the Symfony certification website.
 */
#[Route("/symfony")]
class CrawlController extends AbstractController
{
    /**
     * Executes CrawlSymfonyExamTopicsCommand
     *
 * @param int $version                                              Symfony version
     * @param CrawlSymfonyExamTopicsCommand $crawlTopicsCommand     Crawl command for Symfony exam topics
     * @return JsonResponse                                         JSON response with a command execution result
     * @throws ExceptionInterface                                   ExceptionInterface
     */
    #[Route('/{version}/execute-crawl-topics-command', name: 'app_execute_crawl_topics_command', methods: ['GET'])]
    public function executeCrawlTopicsCommand(int $version, CrawlSymfonyExamTopicsCommand $crawlTopicsCommand): JsonResponse
    {
        if ($version < 3 || $version > 7) {
            return $this->json(
                ['error' => 'The version must be a number between 3 and 7.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $input = new ArrayInput(['version' => trim($version),]);
        $output = new BufferedOutput();

        try {
            $returnCode = $crawlTopicsCommand->run($input, $output);

            if ($returnCode === Command::SUCCESS) {
                return $this->json(['success' => true, 'output' => $output->fetch()]);
            } else {
                return $this->json([
                    'success' => false,
                    'error' => 'Command execution failed with return code: ' . $returnCode,
                    'output' => $output->fetch(),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Command execution error: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * @param int $version
     * @param CrawlSymfonyDocCommand $crawlDocCommand
     * @return JsonResponse
     * @throws ExceptionInterface
     */
    #[Route('/{version}/execute-crawl-doc-command', name: 'app_execute_crawl_doc_command', methods: ['GET'])]
    public function executeCrawlDocCommand(int $version, CrawlSymfonyDocCommand $crawlDocCommand): JsonResponse
    {
        if ($version < 3 || $version > 7) {
            return $this->json(
                ['error' => 'The version must be a number between 3 and 7.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $input = new ArrayInput(['version' => trim($version)]);
        $output = new BufferedOutput();

        try {
            $returnCode = $crawlDocCommand->run($input, $output);

            if ($returnCode === Command::SUCCESS) {
                return $this->json(['success' => true, 'output' => $output->fetch()]);
            } else {
                return $this->json([
                    'success' => false,
                    'error' => 'Crawl documentation command execution failed with return code: '.$returnCode,
                    'output' => $output->fetch(),
                ], RESPONSE::HTTP_INTERNAL_SERVER_ERROR);
            }
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'Crawl documentation error: '.$e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }

    /**
     * @param int $version
     * @param ReformulateTextToMcqCommand $mcqCommand
     * @return JsonResponse
     * @throws ExceptionInterface
     */
    #[Route('/{version}/execute-mcq-command', name: 'app_execute_mcq_command', methods: ['GET'])]
    public function executeMcqCommand(int $version, ReformulateTextToMcqCommand $mcqCommand): JsonResponse
    {
        if ($version < 3 || $version > 7) {
            return $this->json(
                ['error' => 'The version must be a number between 3 and 7.'],
                Response::HTTP_BAD_REQUEST
            );
        }

        $input = new ArrayInput(['version' => trim($version)]);
        $output = new BufferedOutput();

        try {
            $returnCode = $mcqCommand->run($input, $output);
            $output = new BufferedOutput();

            if ($returnCode === Command::SUCCESS) {
                return $this->json([
                    'success' => true,
                    'output' => $output->fetch(),
                ], Response::HTTP_OK);
            } else {
                return $this->json([
                    'success' => false,
                    'error' => 'MCQ generation command execution failed with return code: '.$returnCode,
                    'output' => $output->fetch(),
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
        } catch (\Exception $e) {
            return $this->json(
                ['error' => 'MCQ generation command encountered an execution error: ' . $e->getMessage()],
                Response::HTTP_INTERNAL_SERVER_ERROR
            );
        }
    }
}
