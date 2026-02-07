<?php

namespace App\Tests\Controller;

use App\Document\User;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;


/**
 * RegistrationControllerTest
 *
 * Unit tests for the RegistrationController class.
 */
class RegistrationControllerTest extends WebTestCase
{
    /**
     * Clean the database before running a test
     * Call this method after createClient() in each test
     */
    private function cleanDatabase(): void
    {
        $dm = static::getContainer()->get(DocumentManager::class);

        // Clear the UnitOfWork
        $dm->clear();

        // Delete all users from the collection to ensure clean state
        try {
            $dm->getDocumentCollection(User::class)->deleteMany([]);
        } catch (\Exception $e) {
            // Si la collection n'existe pas encore, ce n'est pas grave
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Ensure kernel is properly shut down after each test
        static::ensureKernelShutdown();
    }

    public function testRegisterPageDisplaysCorrectly(): void
    {
        $client = static::createClient();
        $this->cleanDatabase();
        $crawler = $client->request('GET', '/register');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form[name="registration_form"]');
        $this->assertSelectorExists('input[name="registration_form[username]"]');
        $this->assertSelectorExists('input[name="registration_form[email]"]');
        $this->assertSelectorExists('input[name="registration_form[plainPassword][first]"]');
        $this->assertSelectorExists('input[name="registration_form[plainPassword][second]"]');
    }

    public function testRegisterRedirectsWhenAlreadyLoggedIn(): void
    {
        $client = static::createClient();
        $this->cleanDatabase();

        $dm = static::getContainer()->get(DocumentManager::class);

        // Mock a logged-in user
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('test@example.com');
        $user->setPassword('hashedpassword');
        $user->setIsVerified(true);

        $dm->persist($user);
        $dm->flush();

        $client->loginUser($user);
        $client->request('GET', '/register');

        $this->assertResponseRedirects('/');
    }

    public function testSuccessfulRegistration(): void
    {
        $client = static::createClient();
        $this->cleanDatabase();

        $crawler = $client->request('GET', '/register');

        $form = $crawler->selectButton('Sign up')->form([
            'registration_form[username]' => 'newuser',
            'registration_form[email]' => 'newuser@example.com',
            'registration_form[plainPassword][first]' => 'password123',
            'registration_form[plainPassword][second]' => 'password123',
        ]);

        $client->submit($form);

        // After a successful registration, we should be redirected to the login page
        $this->assertResponseRedirects('/login');

        $client->followRedirect();

        // On the login page, either a success flash message (if email sent) or warning message (if email failed) is displayed
        // Both indicate successful registration
        $hasSuccess = $client->getCrawler()->filter('.alert-success')->count() > 0;
        $hasWarning = $client->getCrawler()->filter('.alert-warning')->count() > 0;

        $this->assertTrue($hasSuccess || $hasWarning, 'Expected either success or warning flash message after registration');

        if ($hasSuccess) {
            $this->assertSelectorTextContains('.alert-success', 'Registration successful!');
        } else {
            $this->assertSelectorTextContains('.alert-warning', 'Registration successful!');
        }
    }

    public function testRegistrationWithExistingUsername(): void
    {
        $client = static::createClient();
        $this->cleanDatabase();

        // First registration
        $crawler = $client->request('GET', '/register');
        $form = $crawler->selectButton('Sign up')->form([
            'registration_form[username]' => 'existinguser',
            'registration_form[email]' => 'first@example.com',
            'registration_form[plainPassword][first]' => 'password123',
            'registration_form[plainPassword][second]' => 'password123',
        ]);
        $client->submit($form);

        // Try to register with same username
        $crawler = $client->request('GET', '/register');
        $form = $crawler->selectButton('Sign up')->form([
            'registration_form[username]' => 'existinguser',
            'registration_form[email]' => 'second@example.com',
            'registration_form[plainPassword][first]' => 'password123',
            'registration_form[plainPassword][second]' => 'password123',
        ]);
        $client->submit($form);

        // An error flash message should be displayed using the "error" label,
        // which is rendered as .alert-danger in the base template.
        $this->assertSelectorExists('.alert-danger');
        $this->assertSelectorTextContains('.alert-danger', 'This username is already taken.');
    }

    public function testRegistrationWithExistingEmail(): void
    {
        $client = static::createClient();
        $this->cleanDatabase();

        // First registration
        $crawler = $client->request('GET', '/register');
        $form = $crawler->selectButton('Sign up')->form([
            'registration_form[username]' => 'user1',
            'registration_form[email]' => 'existing@example.com',
            'registration_form[plainPassword][first]' => 'password123',
            'registration_form[plainPassword][second]' => 'password123',
        ]);
        $client->submit($form);

        // Try to register with same email
        $crawler = $client->request('GET', '/register');
        $form = $crawler->selectButton('Sign up')->form([
            'registration_form[username]' => 'user2',
            'registration_form[email]' => 'existing@example.com',
            'registration_form[plainPassword][first]' => 'password123',
            'registration_form[plainPassword][second]' => 'password123',
        ]);
        $client->submit($form);

        // An error flash message should be displayed using the "error" label,
        // which is rendered as .alert-danger in the base template.
        $this->assertSelectorExists('.alert-danger');
        $this->assertSelectorTextContains('.alert-danger', 'This email is already registered.');
    }

    /**
     * Test the email verification flow with a valid token.
     */
    public function testEmailVerificationFlow(): void
    {
        $client = static::createClient();
        $this->cleanDatabase();

        // Create a user with verification token
        $dm = static::getContainer()->get(DocumentManager::class);
        $user = new User();
        $user->setUsername('verifyuser');
        $user->setEmail('verify@example.com');
        $user->setPassword('hashedpassword');
        $user->setVerificationToken('test_token_123');
        $user->setIsVerified(false);

        $dm->persist($user);
        $dm->flush();

        // Clear the DocumentManager to avoid "Document is not MANAGED" when refreshing later
        $dm->clear();

        // Test verification
        $client->request('GET', '/verify/email/test_token_123');
        $this->assertResponseRedirects('/login');
        $client->followRedirect();
        $this->assertSelectorExists('.alert-success');
        $this->assertSelectorTextContains('.alert-success', 'Your email has been verified successfully!');

        // Reload the user from the database and verify its state
        $reloadedUser = $dm->getRepository(User::class)->findOneBy(['email' => 'verify@example.com']);
        $this->assertNotNull($reloadedUser);
        $this->assertTrue($reloadedUser->isVerified());
        $this->assertNull($reloadedUser->getVerificationToken());
    }

    /**
     * Test that an invalid verification token shows the correct message.
     */
    public function testInvalidVerificationToken(): void
    {
        $client = static::createClient();
        $this->cleanDatabase();

        $client->request('GET', '/verify/email/invalid_token');

        $this->assertResponseRedirects('/login');
        $client->followRedirect();

        // Invalid token uses flash label "error",
        // rendered as .alert-danger by the base template.
        $this->assertSelectorExists('.alert-danger');
        $this->assertSelectorTextContains('.alert-danger', 'Invalid verification token.');
    }

    /**
     * Test that trying to verify an already verified user shows the correct message.
     */
    public function testAlreadyVerifiedUser(): void
    {
        $client = static::createClient();
        $this->cleanDatabase();

        // Create a verified user
        $dm = static::getContainer()->get(DocumentManager::class);
        $user = new User();
        $user->setUsername('verifieduser');
        $user->setEmail('verified@example.com');
        $user->setPassword('hashedpassword');
        $user->setVerificationToken('token_123');
        $user->setIsVerified(true);

        $dm->persist($user);
        $dm->flush();

        // Try to verify again
        $client->request('GET', '/verify/email/token_123');
        $this->assertResponseRedirects('/login');
        $client->followRedirect();
        $this->assertSelectorTextContains('.alert-info', 'Your email is already verified');
    }
}
