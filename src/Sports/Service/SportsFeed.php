<?php

declare(strict_types=1);

namespace App\Sports\Service;

final readonly class SportsFeed
{
    /** @param array{live: list<array<string, mixed>>, upcoming: list<array<string, mixed>>, results: list<array<string, mixed>>} $events */
    public function __construct(public string $state, public array $events, public string $providerName)
    {
    }
}
