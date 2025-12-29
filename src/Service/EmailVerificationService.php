<?php

namespace App\Service;

use App\Document\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;


/**
 * EmailVerificationService
 */
class EmailVerificationService
{
    private MailerInterface $mailer;
    private string $fromEmail;


    /**
     * Constructor
     */
    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
        $this->fromEmail = 'noreply@certibot.com'; // You can make this configurable via env variable
    }

    /**
     * Send verification email
     */
    public function sendVerificationEmail(User $user, string $verificationUrl): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->fromEmail, 'CertiBot'))
            ->to(new Address($user->getEmail(), $user->getUsername()))
            ->subject('Please verify your email address')
            ->htmlTemplate('emails/verification.html.twig')
            ->context([
                'user' => $user,
                'verificationUrl' => $verificationUrl,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Generate a unique verification token
     */
    public function generateVerificationToken(): string
    {
        return bin2hex(random_bytes(32));
    }
}
