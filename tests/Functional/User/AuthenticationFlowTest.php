<?php

declare(strict_types=1);

namespace App\Tests\Functional\User;

use App\User\Entity\AuthToken;
use App\User\Entity\User;
use App\User\Service\AuthTokenService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[CoversNothing]
final class AuthenticationFlowTest extends WebTestCase
{
    public function testRegistrationValidationAndDuplicateEmail(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/register');
        $csrf = $crawler->filter('form[data-auth-form] input[name="_csrf_token"]')->attr('value');

        $client->request('POST', '/register', ['_csrf_token' => $csrf, 'name' => 'A User', 'email' => 'bad-email', 'password' => 'short', 'confirm_password' => 'different']);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[role="alert"]', 'valid email');
        self::assertSelectorTextContains('[role="alert"]', 'do not match');

        $email = 'auth-'.bin2hex(random_bytes(6)).'@example.test';
        $crawler = $client->request('GET', '/register');
        $csrf = $crawler->filter('input[name="_csrf_token"]')->attr('value');
        $client->request('POST', '/register', ['_csrf_token' => $csrf, 'name' => 'A User', 'email' => $email, 'password' => 'long-enough-password', 'confirm_password' => 'long-enough-password']);
        self::assertResponseRedirects('/login');

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);
        self::assertFalse($user->isVerified());
        self::assertNotSame('long-enough-password', $user->getPassword());

        $crawler = $client->request('GET', '/register');
        $csrf = $crawler->filter('input[name="_csrf_token"]')->attr('value');
        $client->request('POST', '/register', ['_csrf_token' => $csrf, 'name' => 'A User', 'email' => $email, 'password' => 'long-enough-password', 'confirm_password' => 'long-enough-password']);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[role="alert"]', 'already exists');
    }

    public function testVerificationLoginResetLogoutAndForgotPasswordPrivacy(): void
    {
        $client = self::createClient();
        $email = 'auth-'.bin2hex(random_bytes(6)).'@example.test';
        $user = new User($email, '');
        $user->setFullName('Flow User');
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, 'original-long-password'));
        $user->setVerified(false);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $em->persist($user);
        $em->flush();

        $tokens = self::getContainer()->get(AuthTokenService::class);
        $verifyToken = $tokens->issue($user, 'verify');
        $client->request('GET', '/verify-email?token='.$verifyToken);
        self::assertResponseRedirects('/login');
        self::assertTrue($user->isVerified());

        $crawler = $client->request('GET', '/login');
        $csrf = $crawler->filter('input[name="_csrf_token"]')->attr('value');
        $client->request('POST', '/login', ['_csrf_token' => $csrf, '_username' => $email, '_password' => 'wrong-password']);
        self::assertResponseRedirects();
        $crawler = $client->followRedirect();
        self::assertSelectorExists('.alert-error');

        $crawler = $client->request('GET', '/login');
        $csrf = $crawler->filter('input[name="_csrf_token"]')->attr('value');
        $client->request('POST', '/login', ['_csrf_token' => $csrf, '_username' => $email, '_password' => 'original-long-password']);
        self::assertResponseRedirects();
        $client->followRedirect();
        $client->request('GET', '/alerts');
        self::assertResponseIsSuccessful();

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user);
        $tokens = self::getContainer()->get(AuthTokenService::class);
        $resetToken = $tokens->issue($user, 'reset');
        $crawler = $client->request('GET', '/reset-password?token='.$resetToken);
        $csrf = $crawler->filter('form[data-auth-form] input[name="_csrf_token"]')->attr('value');
        $client->request('POST', '/reset-password', ['_csrf_token' => $csrf, 'token' => $resetToken, 'password' => 'replacement-long-password', 'confirm_password' => 'replacement-long-password']);
        self::assertResponseRedirects('/login');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertNull($em->getRepository(AuthToken::class)->findOneBy(['tokenHash' => hash('sha256', $resetToken)]));

        $crawler = $client->request('GET', '/login');
        $csrf = $crawler->filter('form[action="/login"] input[name="_csrf_token"]')->attr('value');
        $client->request('POST', '/login', ['_csrf_token' => $csrf, '_username' => $email, '_password' => 'replacement-long-password']);
        self::assertResponseRedirects();
        $crawler = $client->followRedirect();
        $logout = $crawler->filter('form[action="/logout"]');
        self::assertCount(1, $logout);
        $client->submit($logout->form());
        $client->request('GET', '/alerts');
        self::assertResponseRedirects('/login');

        $crawler = $client->request('GET', '/forgot-password');
        $csrf = $crawler->filter('input[name="_csrf_token"]')->attr('value');
        $client->request('POST', '/forgot-password', ['_csrf_token' => $csrf, 'email' => 'unknown-'.bin2hex(random_bytes(4)).'@example.test']);
        self::assertResponseRedirects('/login');
        $crawler = $client->followRedirect();
        $unknownEmailMessage = trim($crawler->filter('.alert-success')->text());

        $crawler = $client->request('GET', '/forgot-password');
        $csrf = $crawler->filter('input[name="_csrf_token"]')->attr('value');
        $client->request('POST', '/forgot-password', ['_csrf_token' => $csrf, 'email' => $email]);
        self::assertResponseRedirects('/login');
        $crawler = $client->followRedirect();
        self::assertSame($unknownEmailMessage, trim($crawler->filter('.alert-success')->text()));
    }
}
