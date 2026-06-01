<?php

namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class EmailService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly Environment $twig,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly string $mailerFrom,
    ) {
    }

    /**
     * Generate a random 8-digit verification code
     */
    public function generateVerificationCode(): string
    {
        return str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT);
    }

    /**
     * Send verification code email
     */
    public function sendVerificationCode(string $email, string $code): void
    {
        $emailMessage = (new Email())
            ->from($this->mailerFrom)
            ->to($email)
            ->subject('Code de validation - MediaT')
            ->html($this->twig->render('emails/verification_code.html.twig', [
                'code' => $code,
                'email' => $email,
            ]));

        $this->mailer->send($emailMessage);
    }

    /**
     * Send password reset email with reset link
     */
    public function sendPasswordResetEmail(string $email, string $token): void
    {
        $resetUrl = $this->urlGenerator->generate('app_reset_password', [
            'token' => $token,
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $emailMessage = (new Email())
            ->from($this->mailerFrom)
            ->to($email)
            ->subject('Réinitialisation de votre mot de passe - MediaT')
            ->html($this->twig->render('emails/reset_password.html.twig', [
                'email' => $email,
                'resetUrl' => $resetUrl,
                'token' => $token,
            ]));

        $this->mailer->send($emailMessage);
    }
}
