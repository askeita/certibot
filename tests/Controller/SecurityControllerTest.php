<?php

namespace App\Tests\Controller;

use App\Document\User;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * SecurityControllerTest
 *
 * Unit tests for the SecurityController class.
 */
class SecurityControllerTest extends WebTestCase
{
    public function testLoginPageDisplaysCorrectly(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        // Le LoginFormType a un block prefix vide, les champs ne sont pas préfixés par "login_form"
        $this->assertSelectorExists('form');
        $this->assertSelectorExists('input[name="_username"]');
        $this->assertSelectorExists('input[name="_password"]');
        $this->assertSelectorExists('input[name="_remember_me"]');
    }

    public function testLoginRedirectsWhenAlreadyLoggedIn(): void
    {
        $client = static::createClient();

        $dm = static::getContainer()->get(DocumentManager::class);

        // Create a persisted, verified user
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('test@example.com');
        $user->setPassword('hashedpassword');
        $user->setIsVerified(true);

        $dm->persist($user);
        $dm->flush();

        $client->loginUser($user);
        $client->request('GET', '/login');

        $this->assertResponseRedirects('/');
    }

    public function testSuccessfulLogin(): void
    {
        $client = static::createClient();

        // Create a verified user
        $dm = static::getContainer()->get(DocumentManager::class);
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setUsername('loginuser');
        $user->setEmail('login@example.com');
        $user->setPassword($passwordHasher->hashPassword($user, 'password123'));
        $user->setIsVerified(true);

        $dm->persist($user);
        $dm->flush();

        // Attempt login
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Login')->form([
            '_username' => 'loginuser',
            '_password' => 'password123',
        ]);

        $client->submit($form);
        $this->assertResponseRedirects('/');

        // Verify user is authenticated
        $this->assertTrue($client->getContainer()->get('security.authorization_checker')->isGranted('IS_AUTHENTICATED_FULLY'));
    }

    public function testLoginWithEmail(): void
    {
        $client = static::createClient();

        // Create a verified user
        $dm = static::getContainer()->get(DocumentManager::class);
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setUsername('emailuser');
        $user->setEmail('email@example.com');
        $user->setPassword($passwordHasher->hashPassword($user, 'password123'));
        $user->setIsVerified(true);

        $dm->persist($user);
        $dm->flush();

        // Attempt login with email
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Login')->form([
            '_username' => 'email@example.com',
            '_password' => 'password123',
        ]);

        $client->submit($form);
        $this->assertResponseRedirects('/');
    }

    public function testFailedLoginWithInvalidCredentials(): void
    {
        $client = static::createClient();

        // Create a verified user
        $dm = static::getContainer()->get(DocumentManager::class);
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setUsername('invaliduser');
        $user->setEmail('invalid@example.com');
        $user->setPassword($passwordHasher->hashPassword($user, 'correctpassword'));
        $user->setIsVerified(true);

        $dm->persist($user);
        $dm->flush();

        // Attempt login with wrong password
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Login')->form([
            '_username' => 'invaliduser',
            '_password' => 'wrongpassword',
        ]);

        $client->submit($form);
        $this->assertResponseRedirects('/login');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-danger', 'Invalid credentials');
    }

    public function testFailedLoginWithUnverifiedUser(): void
    {
        $client = static::createClient();

        // Create an unverified user
        $dm = static::getContainer()->get(DocumentManager::class);
        $passwordHasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = new User();
        $user->setUsername('unverifieduser');
        $user->setEmail('unverified@example.com');
        $user->setPassword($passwordHasher->hashPassword($user, 'password123'));
        $user->setIsVerified(false);

        $dm->persist($user);
        $dm->flush();

        // Attempt login
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Login')->form([
            '_username' => 'unverifieduser',
            '_password' => 'password123',
        ]);

        $client->submit($form);
        $this->assertResponseRedirects('/login');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-danger', 'Please verify your email');
    }

    public function testLogout(): void
    {
        $client = static::createClient();

        $dm = static::getContainer()->get(DocumentManager::class);

        // Create and login user
        $user = new User();
        $user->setUsername('logoutuser');
        $user->setEmail('logout@example.com');
        $user->setPassword('hashedpassword');
        $user->setIsVerified(true);

        $dm->persist($user);
        $dm->flush();

        $client->loginUser($user);

        // Perform logout
        $client->request('GET', '/logout');
        $this->assertResponseRedirects('/');

        // Verify user is no longer authenticated
        $client->request('GET', '/');
        $this->assertNull($client->getContainer()->get('security.token_storage')->getToken()?->getUser());
    }

    public function testRememberMeCheckbox(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $rememberMeCheckbox = $crawler->filter('input[name="_remember_me"]');
        $this->assertCount(1, $rememberMeCheckbox);
        $this->assertEquals('checkbox', $rememberMeCheckbox->attr('type'));
    }
}
