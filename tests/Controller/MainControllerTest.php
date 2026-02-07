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
}
