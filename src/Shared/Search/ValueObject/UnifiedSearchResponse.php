<?php

declare(strict_types=1);

namespace App\Shared\Search\ValueObject;

use App\Article\Entity\Article;

final readonly class UnifiedSearchResponse
{
    /**
     * @param list<Article> $localArticles @param list<NewsSearchResult> $externalResults
     */
    public function __construct(
        public array $localArticles,
        public array $externalResults,
        public ?string $notice = null,
    ) {
    }
}
