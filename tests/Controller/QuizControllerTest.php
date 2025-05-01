<?php

namespace App\Tests\Controller;

use App\Controller\QuizController;
use App\Repository\MongoDBQueryBuilder;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;


/**
 * QuizControllerTest
 *
 * Unit tests for the QuizController class.
 */
class QuizControllerTest extends WebTestCase
{
    /**
     * @var QuizController
     */
    private QuizController $quizController;

    /**
     * Sets up the test environment
     *
     * @return void
     */
    public function setUp(): void
    {
        $mcqQueryBuilderMock = $this->createMock(MongoDBQueryBuilder::class);
        $this->sessionMock = $this->createMock(SessionInterface::class);

        $this->quizController = new QuizController($mcqQueryBuilderMock);
    }

    /**
     * Tests the handleNavigation method for "next" navigation
     *
     * @return void
     * @throws \ReflectionException
     */
    public function testHandleNavigationNext(): void
    {
        $request = new Request(["next" => true]);
        $this->sessionMock->method("get")->with("questionIndex", 0)->willReturn(0);
        $this->sessionMock->expects($this->once())->method("set")->with("questionIndex", 1);

        $result = $this->invokePrivateMethod("handleNavigation", [$request, $this->sessionMock]);
        $this->assertEquals(1, $result);
    }

    /**
     * Tests the handleNavigation method for "prev" navigation
     *
     * @return void
     * @throws \ReflectionException
     */
    public function testHandleNavigationPrev(): void
    {
        $request = new Request(["prev" => true]);
        $this->sessionMock->method("get")->with("questionIndex", 0)->willReturn(1);
        $this->sessionMock->expects($this->once())->method("set")->with("questionIndex", 0);

        $result = $this->invokePrivateMethod("handleNavigation", [$request, $this->sessionMock]);
        $this->assertEquals(0, $result);
    }

    /**
     *
     * @param string $methodName
     * @param array $parameters
     * @return mixed
     * @throws \ReflectionException
     */
    private function invokePrivateMethod(string $methodName, array $parameters): mixed
    {
        $reflection = new \ReflectionClass(QuizController::class);
        $method = $reflection->getMethod($methodName);
        $method->setAccessible(true);

        return $method->invokeArgs($this->quizController, $parameters);
    }

    /**
     * Tests the handleNavigation method for "submit" navigation
     *
     * @return void
     * @throws \ReflectionException
     */
    public function testHandleNavigationSubmit(): void
    {
        $request = new Request(["submit" => true]);
        $this->sessionMock->method("get")->with("questionIndex", 0)->willReturn(0);
        $this->sessionMock->expects($this->once())->method("set")->with("questionIndex", 0);

        $result = $this->invokePrivateMethod("handleNavigation", [$request, $this->sessionMock]);
        $this->assertEquals(0, $result);
    }

    /**
     * Tests the handleNavigation method for "finish" navigation
     *
     * @return void
     * @throws \ReflectionException
     */
    public function testHandleNavigationFinish(): void
    {
        $request = new Request(["finish" => true]);
        $this->sessionMock->method("get")->with("questionIndex", 0)->willReturn(0);
        $this->sessionMock->expects($this->once())->method("set")->with("questionIndex", 0);

        $result = $this->invokePrivateMethod("handleNavigation", [$request, $this->sessionMock]);
        $this->assertEquals(0, $result);
    }

    /**
     * Tests the handleNavigation method for "start" navigation
     *
     * @return void
     * @throws \ReflectionException
     */
    public function testHandleNavigationStart(): void
    {
        $request = new Request(["start" => true]);
        $this->sessionMock->method("get")->with("questionIndex", 0)->willReturn(0);
        $this->sessionMock->expects($this->once())->method("set")->with("questionIndex", 0);

        $result = $this->invokePrivateMethod("handleNavigation", [$request, $this->sessionMock]);
        $this->assertEquals(0, $result);
    }

    /**
     * Tests the handleNavigation method for "restart" navigation
     *
     * @return void
     * @throws \ReflectionException
     */
    public function testHandleNavigationRestart(): void
    {
        $request = new Request(["restart" => true]);
        $this->sessionMock->method("get")->with("questionIndex", 0)->willReturn(0);
        $this->sessionMock->expects($this->once())->method("set")->with("questionIndex", 0);

        $result = $this->invokePrivateMethod("handleNavigation", [$request, $this->sessionMock]);
        $this->assertEquals(0, $result);
    }

    /**
     * Tests the handleNavigation method for "default" navigation
     *
     * @return void
     * @throws \ReflectionException
     */
    public function testHandleNavigationDefault(): void
    {
        $request = new Request([]);
        $this->sessionMock->method("get")->with("questionIndex", 0)->willReturn(0);
        $this->sessionMock->expects($this->once())->method("set")->with("questionIndex", 0);

        $result = $this->invokePrivateMethod("handleNavigation", [$request, $this->sessionMock]);
        $this->assertEquals(0, $result);
    }

    /**
     * Tests the handleNavigation method for "invalid" navigation
     *
     * @return void
     * @throws \ReflectionException
     */
    public function testHandleNavigationInvalid(): void
    {
        $request = new Request(["invalid" => true]);
        $this->sessionMock->method("get")->with("questionIndex", 0)->willReturn(0);
        $this->sessionMock->expects($this->once())->method("set")->with("questionIndex", 0);

        $result = $this->invokePrivateMethod("handleNavigation", [$request, $this->sessionMock]);
        $this->assertEquals(0, $result);
    }

    /**
     * Tests the handleNavigation method for "no navigation" case
     *
     * @return void
     * @throws \ReflectionException
     */
    public function testHandleNavigationNoNavigation(): void
    {
        $request = new Request([]);
        $this->sessionMock->method("get")->with("questionIndex", 0)->willReturn(0);
        $this->sessionMock->expects($this->once())->method("set")->with("questionIndex", 0);

        $result = $this->invokePrivateMethod("handleNavigation", [$request, $this->sessionMock]);
        $this->assertEquals(0, $result);
    }

}
