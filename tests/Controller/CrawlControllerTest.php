<?php

namespace App\Tests\Controller;

use App\Command\CrawlSymfonyDocCommand;
use App\Command\CrawlSymfonyExamTopicsCommand;
use App\Command\ReformulateTextToMcqCommand;
use App\Controller\CrawlController;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;


/**
 * CrawlControllerTest
 *
 * Unit tests for the CrawlController class.
 */
class CrawlControllerTest extends WebTestCase
{
    /**
     * Set up the test environment
     *
     * @return void
     */
    public function setUp(): void
    {
        $this->mockContainer = $this->createMock(ContainerInterface::class);
    }

    /**
     * Create a mock of the CrawlController
     *
     * @return CrawlController
     */
    private function createController(): CrawlController
    {
        $controller = new CrawlController();
        $controller->setContainer($this->mockContainer);
        return $controller;
    }

    /**
     * Test the executeCrawlTopicsCommand method
     *
     * @return void
     * @throws ExceptionInterface
     */
    public function testExecuteCrawlTopicsCommandSuccess(): void
    {
        $mockCommand = $this->createMock(CrawlSymfonyExamTopicsCommand::class);
        $mockCommand->expects($this->once())
            ->method('run')
            ->willReturn(Command::SUCCESS);

        $controller = $this->createController();
        $response = $controller->executeCrawlTopicsCommand(5, $mockCommand);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(
            json_encode(['success' => true, 'output' => '']),
            $response->getContent()
        );
    }

    /**
     * Test the executeCrawlTopicsCommand method with failure
     *
     * @return void
     * @throws ExceptionInterface
     */
    public function testExecuteCrawlTopicsCommandFailure(): void
    {
        $mockCommand = $this->createMock(CrawlSymfonyExamTopicsCommand::class);
        $mockCommand->expects($this->once())
            ->method('run')
            ->willReturn(Command::FAILURE);

        $controller = $this->createController();
        $response = $controller->executeCrawlTopicsCommand(5, $mockCommand);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertStringContainsString(
            json_encode(['success' => false, 'error' => 'Command execution failed with return code: 1', 'output' => '']),
            $response->getContent()
        );
    }

    /**
     * Test the executeCrawlTopicsCommand method with exception
     *
     * @return void
     * @throws ExceptionInterface
     */
    public function testExecuteCrawlTopicsCommandException(): void
    {
        $mockCommand = $this->createMock(CrawlSymfonyExamTopicsCommand::class);
        $mockCommand->expects($this->once())
            ->method('run')
            ->willThrowException(new \Exception('Test exception'));

        $controller = $this->createController();
        $response = $controller->executeCrawlTopicsCommand(5, $mockCommand);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertStringContainsString(
            json_encode(["error" => "Command execution error: Test exception"]),
            $response->getContent()
        );
    }

    /**
     * Test the executeCrawlDocCommand method
     *
     * @return void
     * @throws ExceptionInterface
     */
    public function testExecuteCrawlDocCommandSuccess(): void
    {
        $mockCommand = $this->createMock(CrawlSymfonyDocCommand::class);
        $mockCommand->expects($this->once())
            ->method('run')
            ->willReturn(Command::SUCCESS);

        $controller = $this->createController();
        $response = $controller->executeCrawlDocCommand(5, $mockCommand);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(
            json_encode(['success' => true, 'output' => '']),
            $response->getContent()
        );
    }

    /**
     * Test the executeCrawlDocCommand method with failure
     *
     * @return void
     * @throws ExceptionInterface
     */
    public function testExecuteCrawlDocCommandFailure(): void
    {
        $mockCommand = $this->createMock(CrawlSymfonyDocCommand::class);
        $mockCommand->expects($this->once())
            ->method('run')
            ->willReturn(Command::FAILURE);

        $controller = $this->createController();
        $response = $controller->executeCrawlDocCommand(5, $mockCommand);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertStringContainsString(
            json_encode(["success" => false, "error" => "Crawl documentation command execution failed with return code: 1", 'output' => '']),
            $response->getContent()
        );
    }

    /**
     * Test the executeCrawlDocCommand method with exception
     *
     * @return void
     * @throws ExceptionInterface
     */
    public function testExecuteCrawlDocCommandException(): void
    {
        $mockCommand = $this->createMock(CrawlSymfonyDocCommand::class);
        $mockCommand->expects($this->once())
            ->method('run')
            ->willThrowException(new \Exception('Test exception'));

        $controller = $this->createController();
        $response = $controller->executeCrawlDocCommand(5, $mockCommand);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertStringContainsString(
            json_encode(['error' => 'Crawl documentation error: Test exception']),
            $response->getContent()
        );
    }

    /**
     * Test the executeCrawlDocCommand method with an invalid version
     *
     * @return void
     * @throws ExceptionInterface
     */
    public function testExecuteCrawlDocCommandInvalidVersion(): void
    {
        $mockCommand = $this->createMock(CrawlSymfonyDocCommand::class);
        $mockCommand->expects($this->never())
            ->method('run');

        $controller = $this->createController();
        $response = $controller->executeCrawlDocCommand(0, $mockCommand);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString(
            json_encode(["error" => "The version must be a number between 3 and 7."]),
            $response->getContent()
        );
    }

    /**
     * Test the executeCrawlTopicsCommand method with an invalid version
     *
     * @throws ExceptionInterface
     */
    public function testExecuteCrawlTopicsCommandInvalidVersion(): void
    {
        // Create a mock command that simulates the behavior of the CrawlSymfonyExamTopicsCommand
        $mockCommand = $this->createMock(CrawlSymfonyExamTopicsCommand::class);
        $mockCommand->expects($this->never())
            ->method('run');

        $controller = $this->createController();
        $response = $controller->executeCrawlTopicsCommand(0, $mockCommand);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString(
            json_encode(["error" => "The version must be a number between 3 and 7."]),
            $response->getContent()
        );
    }

    /**
     * Test the executeReformulateTextToMcqCommand method
     *
     * @return void
     * @throws ExceptionInterface
     */
    public function testExecuteReformulateTextToMcqCommandSuccess(): void
    {
        $mockCommand = $this->createMock(ReformulateTextToMcqCommand::class);
        $mockCommand->expects($this->once())
            ->method('run')
            ->willReturn(Command::SUCCESS);

        $controller = $this->createController();
        $response = $controller->executeMcqCommand(5, $mockCommand);

        //$this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertJsonStringEqualsJsonString(
            json_encode(['success' => true, 'output' => '']),
            $response->getContent()
        );
    }

    /**
     * Test the executeReformulateTextToMcqCommand method with failure
     *
     * @return void
     * @throws ExceptionInterface
     */
    public function testExecuteReformulateTextToMcqCommandFailure(): void
    {
        $mockCommand = $this->createMock(ReformulateTextToMcqCommand::class);
        $mockCommand->expects($this->once())
            ->method('run')
            ->willReturn(Command::FAILURE);

        $controller = $this->createController();
        $response = $controller->executeMcqCommand(5, $mockCommand);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertStringContainsString(
            json_encode(["success" => false, "error" => "MCQ generation command execution failed with return code: 1", "output" => '']),
            $response->getContent()
        );
    }

    /**
     * Test the executeReformulateTextToMcqCommand method with exception
     *
     * @return void
     * @throws ExceptionInterface
     */
    public function testExecuteReformulateTextToMcqCommandException(): void
    {
        $mockCommand = $this->createMock(ReformulateTextToMcqCommand::class);
        $mockCommand->expects($this->once())
            ->method('run')
            ->willThrowException(new \Exception('Test exception'));

        $controller = $this->createController();
        $response = $controller->executeMcqCommand(5, $mockCommand);

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertStringContainsString(
            json_encode(["error" => "MCQ generation command encountered an execution error: Test exception"]),
            $response->getContent()
        );
    }

    /**
     * Test the executeReformulateTextToMcqCommand method with invalid version
     *
     * @return void
     * @throws ExceptionInterface
     */
    public function testExecuteReformulateTextToMcqCommandInvalidVersion(): void
    {
        $mockCommand = $this->createMock(ReformulateTextToMcqCommand::class);
        $mockCommand->expects($this->never())
            ->method('run');

        $controller = $this->createController();
        $response = $controller->executeMcqCommand(0, $mockCommand);

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertStringContainsString(
            json_encode(["error" => "The version must be a number between 3 and 7."]),
            $response->getContent()
        );
    }

    /**
     * Tear down the test environment
     *
     * @return void
     */
    public function tearDown(): void
    {
        // Clean up any resources or state after each test
        unset($this->mockContainer);
        parent::tearDown();
    }

}
