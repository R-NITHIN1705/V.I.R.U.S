<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use App\Shared\Entity\Category;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

trait CreatesPantherUser
{
    /**
     * @return array{email: string, password: string}
     */
    private function createPantherUser(): array
    {
        $email = 'e2e-' . bin2hex(random_bytes(6)) . '@example.test';
        $password = 'e2e-password-123';
        $user = new User($email, '');
        $user->setPassword(self::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, $password));

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($user);
        $entityManager->flush();

        return [
            'email' => $email,
            'password' => $password,
        ];
    }

    private function createTechCategory(): string
    {
        $slug = 'tech-e2e-' . bin2hex(random_bytes(4));
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist(new Category('Tech', $slug, 1, '#3B82F6'));
        $entityManager->flush();

        return $slug;
    }
}
