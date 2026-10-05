<?php

declare(strict_types=1);

namespace App\Tests\E2E;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\Component\Panther\PantherTestCase;

#[CoversNothing]
final class SearchE2ETest extends PantherTestCase
{
    use CreatesPantherUser;

    public function testSearchPage(): void
    {
        $client = self::createPantherClient();
        $credentials = $this->createPantherUser();
        $client->request('GET', '/login');
        $client->submitForm('Sign In', [
            '_username' => $credentials['email'],
            '_password' => $credentials['password'],
        ]);

        $client->request('GET', '/search?q=nonexistent_xyz');
        self::assertSelectorTextContains('h1', "Search the world's news");
        self::assertSelectorTextContains('body', 'No stories found');
    }
}
