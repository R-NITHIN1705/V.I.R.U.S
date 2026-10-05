<?php

declare(strict_types=1);

namespace App\Sports\Service;

interface SportsProviderInterface
{
    /** @return array{live: list<array<string, mixed>>, upcoming: list<array<string, mixed>>, results: list<array<string, mixed>>} */
    public function getEvents(): array;
}
