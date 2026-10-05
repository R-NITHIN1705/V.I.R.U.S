<?php

declare(strict_types=1);

namespace App\Shared\Search\ValueObject;

final readonly class NewsSearchProviderResponse
{
    /**
     * @param list<NewsSearchResult> $results
     */
    public function __construct(
        public array $results = [],
        public ?string $notice = null,
    ) {
    }
}
