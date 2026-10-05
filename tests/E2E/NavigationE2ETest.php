<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Panther\PantherTestCase;

#[CoversNothing]
final class NavigationE2ETest extends PantherTestCase
{
    use CreatesPantherUser;

    public function testAllNavLinksWork(): void
    {
        $client = self::createPantherClient();
        $credentials = $this->createPantherUser();
        $client->request('GET', '/login');
        $client->submitForm('Sign In', [
            '_username' => $credentials['email'],
            '_password' => $credentials['password'],
        ]);

        $client->request('GET', '/sources');
        self::assertSelectorNotExists('a[href="/stats/ai"]');
        $client->executeScript("document.querySelector('[data-mode-toggle]').click()");
        self::assertSame('light', $client->executeScript('return document.documentElement.dataset.mode'));
        self::assertSelectorNotExists('a[href="/stats/ai"]');
        $client->executeScript("document.querySelector('[data-mode-toggle]').click()");
        self::assertSame('dark', $client->executeScript('return document.documentElement.dataset.mode'));
        self::assertSelectorNotExists('a[href="/stats/ai"]');

        $pages = ['/sources', '/alerts', '/notifications', '/digests', '/search', '/stats/ai', '/settings'];
        foreach ($pages as $page) {
            $client->request('GET', $page);
            self::assertSelectorExists('.navbar', sprintf('Page %s should have navbar', $page));
            self::assertSelectorNotExists('a[href="/stats/ai"]', sprintf('AI Stats must stay out of navigation on %s', $page));
        }
    }
}
