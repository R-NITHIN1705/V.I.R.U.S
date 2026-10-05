<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Panther\PantherTestCase;

#[CoversNothing]
final class DashboardE2ETest extends PantherTestCase
{
    use CreatesPantherUser;

    public function testLoginAndDashboard(): void
    {
        $client = self::createPantherClient();
        $credentials = $this->createPantherUser();
        $client->request('GET', '/login');

        $client->submitForm('Sign In', [
            '_username' => $credentials['email'],
            '_password' => $credentials['password'],
        ]);

        self::assertSelectorExists('.virus-globe');
    }

    public function testCategoryFilter(): void
    {
        $client = self::createPantherClient();
        $credentials = $this->createPantherUser();
        $categorySlug = $this->createTechCategory();
        $client->request('GET', '/login');
        $client->submitForm('Sign In', [
            '_username' => $credentials['email'],
            '_password' => $credentials['password'],
        ]);

        $client->waitFor('a[href*="category=' . $categorySlug . '"]', 10);
        $selector = 'a[href*="category=' . $categorySlug . '"]';
        $client->executeScript('document.querySelector(' . json_encode($selector) . ')?.click()');
        $client->waitFor('a.is-selected[href*="category=' . $categorySlug . '"]', 10);
        self::assertStringContainsString('category=' . $categorySlug, $client->getCurrentURL());
    }

    public function testThemeToggle(): void
    {
        $client = self::createPantherClient();
        $credentials = $this->createPantherUser();
        $client->request('GET', '/login');
        $client->submitForm('Sign In', [
            '_username' => $credentials['email'],
            '_password' => $credentials['password'],
        ]);
        self::assertSelectorExists('.virus-globe');

        $client->executeScript("document.querySelector('[data-mode-toggle]').click()");
        $client->getWebDriver()->wait(5, 100)->until(static fn ($driver): bool => $driver->executeScript("return getComputedStyle(document.querySelector('#feed-overview-title')).color") === 'rgb(11, 27, 51)');
        self::assertSame('light', $client->executeScript('return document.documentElement.dataset.mode'));
        self::assertSame('light', $client->executeScript("return localStorage.getItem('virus-mode')"));
        self::assertSame('rgb(11, 27, 51)', $client->executeScript("return getComputedStyle(document.querySelector('#feed-overview-title')).color"));

        // Verify persistence across navigation
        $client->request('GET', '/sources');
        self::assertSame('light', $client->executeScript('return document.documentElement.dataset.mode'));
        $client->executeScript("document.querySelector('[data-mode-toggle]').click()");
        $client->getWebDriver()->wait(5, 100)->until(static fn ($driver): bool => $driver->executeScript("return getComputedStyle(document.querySelector('.page-heading h1')).color") === 'rgb(237, 243, 252)');
        self::assertSame('dark', $client->executeScript('return document.documentElement.dataset.mode'));
        self::assertSame('rgb(237, 243, 252)', $client->executeScript("return getComputedStyle(document.querySelector('.page-heading h1')).color"));
    }
}
