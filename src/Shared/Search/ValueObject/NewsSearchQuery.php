<?php

declare(strict_types=1);

namespace App\Shared\Search\ValueObject;

final readonly class NewsSearchQuery
{
    public function __construct(
        public string $query,
        public ?\DateTimeImmutable $from = null,
        public ?\DateTimeImmutable $to = null,
        public ?string $country = null,
        public ?string $language = null,
        public ?string $category = null,
        public ?int $sourceId = null,
        public ?string $sourceDomain = null,
    ) {
    }
}
