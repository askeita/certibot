<?php

namespace App\Service;

use App\Document\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;


/**
 * EmailService
 */
readonly class EmailService
{
    /**
     * Constructor
     */
    public function __construct(
        private MailerInterface $mailer,
        private string          $fromEmail = 'noreply@certibot.com',
        private string          $fromName = 'CertiBot'
    ) {
    }

    /**
     * Send verification email
     *
     * @param User $user user to send email to
     * @param string $verificationUrl verification URL
     * @return void
     * @throws TransportExceptionInterface
     */
    public function sendVerificationEmail(User $user, string $verificationUrl): void
    {
        $email = new TemplatedEmail()
            ->from(new Address($this->fromEmail, $this->fromName))
            ->to($user->getEmail())
            ->subject('Please Confirm your Email')
            ->htmlTemplate('emails/confirmation_email.html.twig')
            ->context([
                'user' => $user,
                'verificationUrl' => $verificationUrl,
            ]);

        $this->mailer->send($email);
    }
}
