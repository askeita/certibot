<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * MainControllerTest
 *
 * Unit tests for the MainController class.
 */
class MainControllerTest extends WebTestCase
{
    /**
     * Test if the index page loads successfully
     *
     * @return void
     */
    public function testIndexPageLoadsSuccessfully(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists("h1");
    }

    /**
     * Tests a non-existent page
     *
     * @return void
     */
    public function testNotFoundPage(): void
    {
        $client = static::createClient();
        $client->request('GET', '/non-existent-page');

        $this->assertResponseStatusCodeSame(404);
    }

    /**
     * Test the /symfony route
     *
     * @return void
     */
    public function testSymfonyRoute(): void
    {
        $client = static::createClient();
        $client->request('GET', '/symfony');

        $this->assertResponseStatusCodeSame("404");
        $this->assertSelectorTextContains('h1', 'Symfony');
    }

    /**
     * Test the /symfony/{version}/exam-topics route
     *
     * @return void
     */
    public function testSymfonyExamTopicsRoute(): void
    {
        $client = static::createClient();
        $client->request('GET', '/symfony/5/exam-topics');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Symfony 5 Exam Topics');
    }

    /**
     * Test the /symfony/{version}/exam-topics route with no exam topics found
     *
     * @return void
     */
    public function testNoExamTopicsFound(): void
    {
        $client = static::createClient();
        $client->request('GET', '/symfony/0/exam-topics');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists("h3");
    }

}
