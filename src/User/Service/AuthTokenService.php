<?php

declare(strict_types=1);

namespace App\User\Service;

use App\User\Entity\AuthToken;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class AuthTokenService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public function issue(User $user, string $purpose): string
    {
        $repo = $this->entityManager->getRepository(AuthToken::class);
        foreach ($repo->findBy(['user' => $user, 'purpose' => $purpose]) as $old) {
            $this->entityManager->remove($old);
        }
        $plain = bin2hex(random_bytes(32));
        $token = new AuthToken($user, $purpose, hash('sha256', $plain), new \DateTimeImmutable('+1 hour'));
        $this->entityManager->persist($token);
        $this->entityManager->flush();
        return $plain;
    }

    public function consume(string $plain, string $purpose): ?AuthToken
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $plain)) return null;
        $token = $this->entityManager->getRepository(AuthToken::class)->findOneBy([
            'tokenHash' => hash('sha256', $plain), 'purpose' => $purpose,
        ]);
        if (!$token instanceof AuthToken || $token->getExpiresAt() <= new \DateTimeImmutable()) return null;
        $this->entityManager->remove($token);
        $this->entityManager->flush();
        return $token;
    }

    public function send(User $user, string $purpose): void
    {
        $token = $this->issue($user, $purpose);
        $route = $purpose === 'verify' ? 'app_verify_email' : 'app_reset_password';
        $url = $this->urlGenerator->generate($route, ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
        $subject = $purpose === 'verify' ? 'Verify your News Aggregator account' : 'Reset your News Aggregator password';
        $body = "Hello {$user->getFullName()},\n\nUse this secure link within one hour:\n{$url}\n\nIf you did not request this, you can ignore this message.";
        $from = getenv('AUTH_FROM_EMAIL') ?: 'no-reply@localhost';
        if (!$this->mailerAvailable()) return;
        @mail($user->getEmail(), $subject, $body, 'From: '.$from);
    }

    private function mailerAvailable(): bool
    {
        $path = trim((string) ini_get('sendmail_path'));
        $binary = strtok($path, ' ');
        if (!is_string($binary) || $binary === '') return false;
        if (str_contains($binary, DIRECTORY_SEPARATOR)) return is_executable($binary);
        foreach (explode(PATH_SEPARATOR, (string) getenv('PATH')) as $directory) {
            if ($directory !== '' && is_executable($directory.DIRECTORY_SEPARATOR.$binary)) return true;
        }
        return false;
    }
}
