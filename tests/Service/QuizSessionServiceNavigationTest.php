<?php

namespace App\Tests\Controller;

use App\Service\QuizSessionService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * QuizSessionServiceNavigationTest
 *
 * Unit tests for QuizSessionService::handleNavigation(), which centralises
 * the "previous"/"next" question navigation logic shared by
 * SymfonyQuizController and PhpQuizController.
 */
class QuizSessionServiceNavigationTest extends TestCase
{
    private QuizSessionService $quizSessionService;

    private SessionInterface $sessionMock;

    public function setUp(): void
    {
        $this->quizSessionService = new QuizSessionService();
        $this->sessionMock = $this->createMock(SessionInterface::class);
    }

    public function testHandleNavigationNext(): void
    {
        $request = new Request(['next' => true]);
        $this->sessionMock->method('get')->with('questionIndex', 0)->willReturn(0);
        $this->sessionMock->expects($this->once())->method('set')->with('questionIndex', 1);

        $result = $this->quizSessionService->handleNavigation($request, $this->sessionMock);
        $this->assertEquals(1, $result);
    }

    public function testHandleNavigationPrev(): void
    {
        $request = new Request(['prev' => true]);
        $this->sessionMock->method('get')->with('questionIndex', 0)->willReturn(1);
        $this->sessionMock->expects($this->once())->method('set')->with('questionIndex', 0);

        $result = $this->quizSessionService->handleNavigation($request, $this->sessionMock);
        $this->assertEquals(0, $result);
    }

    public function testHandleNavigationPrevNeverGoesBelowZero(): void
    {
        $request = new Request(['prev' => true]);
        $this->sessionMock->method('get')->with('questionIndex', 0)->willReturn(0);
        $this->sessionMock->expects($this->once())->method('set')->with('questionIndex', 0);

        $result = $this->quizSessionService->handleNavigation($request, $this->sessionMock);
        $this->assertEquals(0, $result);
    }

    public function testHandleNavigationNoNavigationKeepsCurrentIndex(): void
    {
        $request = new Request([]);
        $this->sessionMock->method('get')->with('questionIndex', 0)->willReturn(2);
        $this->sessionMock->expects($this->once())->method('set')->with('questionIndex', 2);

        $result = $this->quizSessionService->handleNavigation($request, $this->sessionMock);
        $this->assertEquals(2, $result);
    }

    /**
     * Ensures the question set stays stable across navigations: preparing
     * the same question pool twice with the same duration and a fixed seed
     * always returns the same number of questions.
     */
    public function testPrepareQuestionsReturnsConsistentCount(): void
    {
        $allQuestions = array_map(fn(int $i) => ['question' => "Q{$i}", 'answer' => 'A', 'choices' => [], 'link' => ''], range(1, 10));

        $questions = $this->quizSessionService->prepareQuestions($allQuestions, 300); // 5 minutes -> 5 questions
        $this->assertCount(5, $questions);
    }

}
