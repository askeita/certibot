<?php

namespace App\Tests\Service;

use App\Document\User;
use App\Service\EmailVerificationService;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;

/**
 * EmailVerificationServiceTest
 *
 * Unit tests for the EmailVerificationService class.
 */
class EmailVerificationServiceTest extends TestCase
{
    private MailerInterface $mailer;
    private EmailVerificationService $emailVerificationService;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->emailVerificationService = new EmailVerificationService($this->mailer);
    }

    public function testGenerateVerificationToken(): void
    {
        $token1 = $this->emailVerificationService->generateVerificationToken();
        $token2 = $this->emailVerificationService->generateVerificationToken();

        // Check that tokens are strings
        $this->assertIsString($token1);
        $this->assertIsString($token2);

        // Check that tokens are not empty
        $this->assertNotEmpty($token1);
        $this->assertNotEmpty($token2);

        // Check that tokens are unique (very high probability)
        $this->assertNotEquals($token1, $token2);

        // Check token length (bin2hex of 32 bytes = 64 characters)
        $this->assertEquals(64, strlen($token1));
        $this->assertEquals(64, strlen($token2));

        // Check that tokens contain only valid hex characters
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $token1);
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $token2);
    }

    public function testSendVerificationEmailSuccess(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('user@example.com');

        $verificationUrl = 'https://example.com/verify?token=abc123';

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($email) use ($user, $verificationUrl) {
                $this->assertInstanceOf(TemplatedEmail::class, $email);

                // Check sender
                $from = $email->getFrom();
                $this->assertCount(1, $from);
                $this->assertEquals('noreply@certibot.com', $from[0]->getAddress());
                $this->assertEquals('CertiBot', $from[0]->getName());

                // Check recipient
                $to = $email->getTo();
                $this->assertCount(1, $to);
                $this->assertEquals('user@example.com', $to[0]->getAddress());
                $this->assertEquals('testuser', $to[0]->getName());

                // Check subject
                $this->assertEquals('Please verify your email address', $email->getSubject());

                // Check template
                $this->assertEquals('emails/verification.html.twig', $email->getHtmlTemplate());

                // Check context
                $context = $email->getContext();
                $this->assertArrayHasKey('user', $context);
                $this->assertArrayHasKey('verificationUrl', $context);
                $this->assertSame($user, $context['user']);
                $this->assertEquals($verificationUrl, $context['verificationUrl']);

                return true;
            }));

        $this->emailVerificationService->sendVerificationEmail($user, $verificationUrl);
    }

    public function testSendVerificationEmailWithTransportException(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('user@example.com');

        $verificationUrl = 'https://example.com/verify?token=abc123';

        $this->mailer->expects($this->once())
            ->method('send')
            ->willThrowException(new TransportException('SMTP server unavailable'));

        $this->expectException(TransportException::class);
        $this->expectExceptionMessage('SMTP server unavailable');

        $this->emailVerificationService->sendVerificationEmail($user, $verificationUrl);
    }

    public function testMultipleTokenGenerationUniqueness(): void
    {
        $tokens = [];
        $numberOfTokens = 100;

        // Generate multiple tokens
        for ($i = 0; $i < $numberOfTokens; $i++) {
            $tokens[] = $this->emailVerificationService->generateVerificationToken();
        }

        // Check that all tokens are unique
        $uniqueTokens = array_unique($tokens);
        $this->assertCount($numberOfTokens, $uniqueTokens, 'All generated tokens should be unique');

        // Check that all tokens have the correct format
        foreach ($tokens as $token) {
            $this->assertEquals(64, strlen($token));
            $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $token);
        }
    }

    public function testSendVerificationEmailWithComplexUserData(): void
    {
        $user = new User();
        $user->setUsername('user.name+tag@domain');
        $user->setEmail('complex.email+test@sub.domain.com');

        $verificationUrl = 'https://example.com/verify?token=' . $this->emailVerificationService->generateVerificationToken();

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($email) use ($user, $verificationUrl) {
                $to = $email->getTo();
                $this->assertEquals('complex.email+test@sub.domain.com', $to[0]->getAddress());
                $this->assertEquals('user.name+tag@domain', $to[0]->getName());

                $context = $email->getContext();
                $this->assertEquals($verificationUrl, $context['verificationUrl']);

                return true;
            }));

        $this->emailVerificationService->sendVerificationEmail($user, $verificationUrl);
    }

    public function testTokenGenerationCryptographicSecurity(): void
    {
        // Test that the token generation uses cryptographically secure random bytes
        $token = $this->emailVerificationService->generateVerificationToken();

        // Since we're using random_bytes(), we can't predict the output,
        // but we can test the format and length
        $this->assertEquals(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $token);

        // Test entropy by checking distribution of characters (basic test)
        $charCounts = array_count_values(str_split($token));

        // Should have reasonable character distribution (not all same character)
        $this->assertGreaterThan(1, count($charCounts), 'Token should have character diversity');

        // No single character should dominate too much (very basic entropy test)
        foreach ($charCounts as $count) {
            $this->assertLessThan(32, $count, 'No character should appear more than half the time');
        }
    }

    public function testSendVerificationEmailWithEmptyUserData(): void
    {
        $user = new User();
        $user->setUsername('');
        $user->setEmail('test@example.com');

        $verificationUrl = 'https://example.com/verify?token=abc123';

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($email) {
                $to = $email->getTo();
                $this->assertEquals('', $to[0]->getName()); // Empty username should be preserved
                return true;
            }));

        $this->emailVerificationService->sendVerificationEmail($user, $verificationUrl);
    }

    public function testSendVerificationEmailWithSpecialCharactersInUrl(): void
    {
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('user@example.com');

        $verificationUrl = 'https://example.com/verify?token=abc123&param=value with spaces&special=chars!@#$%';

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($email) use ($verificationUrl) {
                $context = $email->getContext();
                $this->assertEquals($verificationUrl, $context['verificationUrl']);
                return true;
            }));

        $this->emailVerificationService->sendVerificationEmail($user, $verificationUrl);
    }
}
