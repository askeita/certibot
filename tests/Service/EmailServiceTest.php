<?php

namespace App\Tests\Service;

use App\Document\User;
use App\Service\EmailService;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

/**
 * EmailServiceTest
 *
 * Unit tests for the EmailService class.
 */
class EmailServiceTest extends TestCase
{
    private MailerInterface $mailer;
    private EmailService $emailService;

    protected function setUp(): void
    {
        $this->mailer = $this->createMock(MailerInterface::class);
        $this->emailService = new EmailService($this->mailer, 'test@example.com', 'Test App');
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
                $this->assertEquals('test@example.com', $from[0]->getAddress());
                $this->assertEquals('Test App', $from[0]->getName());

                // Check recipient - EmailService uses only email address, not name
                $to = $email->getTo();
                $this->assertCount(1, $to);
                $this->assertEquals('user@example.com', $to[0]->getAddress());

                // Check subject - EmailService uses different subject
                $this->assertEquals('Please Confirm your Email', $email->getSubject());

                // Check template - EmailService uses different template
                $this->assertEquals('emails/confirmation_email.html.twig', $email->getHtmlTemplate());

                // Check context
                $context = $email->getContext();
                $this->assertArrayHasKey('user', $context);
                $this->assertArrayHasKey('verificationUrl', $context);
                $this->assertSame($user, $context['user']);
                $this->assertEquals($verificationUrl, $context['verificationUrl']);

                return true;
            }));

        $this->emailService->sendVerificationEmail($user, $verificationUrl);
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

        $this->emailService->sendVerificationEmail($user, $verificationUrl);
    }

    public function testConstructorWithDefaultValues(): void
    {
        $emailService = new EmailService($this->mailer);

        // We can't directly test the private properties, but we can test the behavior
        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('user@example.com');

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($email) {
                $from = $email->getFrom();
                $this->assertEquals('noreply@certibot.com', $from[0]->getAddress());
                $this->assertEquals('CertiBot', $from[0]->getName());
                return true;
            }));

        $emailService->sendVerificationEmail($user, 'https://example.com/verify');
    }

    public function testSendVerificationEmailWithCustomFromDetails(): void
    {
        $customEmailService = new EmailService($this->mailer, 'custom@test.com', 'Custom App');

        $user = new User();
        $user->setUsername('testuser');
        $user->setEmail('user@example.com');

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($email) {
                $from = $email->getFrom();
                $this->assertEquals('custom@test.com', $from[0]->getAddress());
                $this->assertEquals('Custom App', $from[0]->getName());
                return true;
            }));

        $customEmailService->sendVerificationEmail($user, 'https://example.com/verify');
    }

    public function testSendVerificationEmailWithSpecialCharacters(): void
    {
        $user = new User();
        $user->setUsername('tëst_ûser');
        $user->setEmail('üser@exämple.com');

        $verificationUrl = 'https://example.com/verify?token=abc123&special=%20chars';

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($email) use ($user, $verificationUrl) {
                $to = $email->getTo();
                $this->assertEquals('üser@exämple.com', $to[0]->getAddress());

                $context = $email->getContext();
                $this->assertEquals($verificationUrl, $context['verificationUrl']);

                return true;
            }));

        $this->emailService->sendVerificationEmail($user, $verificationUrl);
    }

    public function testSendVerificationEmailWithLongUsername(): void
    {
        $user = new User();
        $longUsername = str_repeat('a', 255); // Very long username
        $user->setUsername($longUsername);
        $user->setEmail('user@example.com');

        $this->mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(function ($email) use ($longUsername) {
                $to = $email->getTo();
                $this->assertEquals('user@example.com', $to[0]->getAddress());
                return true;
            }));

        $this->emailService->sendVerificationEmail($user, 'https://example.com/verify');
    }
}
