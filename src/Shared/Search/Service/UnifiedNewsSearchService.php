<?php

declare(strict_types=1);

namespace App\Shared\Search\Service;

use App\Article\Entity\Article;
use App\Article\Repository\ArticleRepositoryInterface;
use App\Shared\Search\ValueObject\NewsSearchQuery;
use App\Shared\Search\ValueObject\NewsSearchResult;
use App\Shared\Search\ValueObject\UnifiedSearchResponse;
use App\Source\Repository\SourceRepositoryInterface;
use Psr\Log\LoggerInterface;

final readonly class UnifiedNewsSearchService
{
    public function __construct(
        private ArticleSearchServiceInterface $articleSearch,
        private ArticleRepositoryInterface $articles,
        private SourceRepositoryInterface $sources,
        private NewsSearchProviderInterface $externalNews,
        private LoggerInterface $logger,
    ) {
    }

    public function search(NewsSearchQuery $query): UnifiedSearchResponse
    {
        if (trim($query->query) === '') {
            return new UnifiedSearchResponse([], []);
        }

        $sourceDomain = null;
        if ($query->sourceId !== null) {
            $source = $this->sources->findById($query->sourceId);
            if ($source !== null) {
                $sourceUrl = $source->getSiteUrl() ?: $source->getFeedUrl();
                $sourceDomain = strtolower((string) parse_url($sourceUrl, PHP_URL_HOST));
                $sourceDomain = str_starts_with($sourceDomain, 'www.') ? substr($sourceDomain, 4) : $sourceDomain;
            }
        }
        $externalQuery = new NewsSearchQuery(
            $query->query,
            $query->from,
            $query->to,
            $query->country,
            $query->language,
            $query->category,
            $query->sourceId,
            $sourceDomain,
        );

        $localArticles = [];
        $localFailure = false;
        try {
            $ids = $this->articleSearch->search($query->query, $query->category, 250);
            if ($ids !== []) {
                $localArticles = $this->articles->findByIds($ids);
            }
        } catch (\Throwable $e) {
            $localFailure = true;
            $this->logger->warning('Local article search failed: {error}', [
                'error' => $e->getMessage(),
            ]);
        }

        $localArticles = array_values(array_filter(
            $localArticles,
            fn (Article $article): bool => $this->matches($article, $query),
        ));
        $externalResponse = $this->externalNews->search($externalQuery);
        $localUrls = [];
        $localHeadlines = [];
        foreach ($localArticles as $article) {
            $localUrls[$this->normalizeUrl($article->getUrl())] = true;
            $headline = $this->normalizeHeadline($article->getTitle());
            if ($headline !== '') {
                $date = $article->getPublishedAt() ?? $article->getFetchedAt();
                $localHeadlines[$headline][] = $date->getTimestamp();
            }
        }
        $externalResults = array_values(array_filter(
            $externalResponse->results,
            fn (NewsSearchResult $result): bool => ! isset($localUrls[$this->normalizeUrl($result->url)])
                && ! $this->matchesLocalHeadline($result, $localHeadlines)
                && ($query->category === null || $query->category === '')
                && ($query->sourceId === null || ($sourceDomain !== null && $this->hostMatches($result->url, $sourceDomain))),
        ));

        $notice = $externalResponse->notice;
        if ($query->category !== null && $query->category !== '' && $externalResponse->results !== []) {
            $notice ??= 'Global results do not yet have reliable topic labels, so this topic filter applies to connected sources only.';
        }
        if ($localFailure) {
            $notice = $notice ?? 'Your connected-source search is temporarily unavailable. Global results are shown where available.';
        }

        return new UnifiedSearchResponse(array_slice($localArticles, 0, 50), array_slice($externalResults, 0, 75), $notice);
    }

    private function matches(Article $article, NewsSearchQuery $query): bool
    {
        if ($query->sourceId !== null && $article->getSource()->getId() !== $query->sourceId) {
            return false;
        }
        if ($query->country !== null && strtoupper((string) $article->getSource()->getCountry()) !== $query->country) {
            return false;
        }
        if ($query->language !== null && strtolower((string) $article->getSource()->getLanguage()) !== strtolower($query->language)) {
            return false;
        }
        if ($query->category !== null && $article->getCategory()?->getSlug() !== $query->category) {
            return false;
        }
        $publishedAt = $article->getPublishedAt() ?? $article->getFetchedAt();

        return ($query->from === null || $publishedAt >= $query->from)
            && ($query->to === null || $publishedAt <= $query->to);
    }

    private function normalizeUrl(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return strtolower(rtrim($url, '/'));
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        $host = str_starts_with($host, 'www.') ? substr($host, 4) : $host;
        $path = rtrim((string) ($parts['path'] ?? '/'), '/');

        return $host . $path;
    }

    private function hostMatches(string $url, string $expectedDomain): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = str_starts_with($host, 'www.') ? substr($host, 4) : $host;
        $expectedDomain = strtolower($expectedDomain);

        return $host === $expectedDomain || str_ends_with($host, '.' . $expectedDomain);
    }

    /**
     * @param array<string, list<int>> $localHeadlines
     */
    private function matchesLocalHeadline(NewsSearchResult $result, array $localHeadlines): bool
    {
        $headline = $this->normalizeHeadline($result->title);
        if ($headline === '' || $result->publishedAt === null || ! isset($localHeadlines[$headline])) {
            return false;
        }

        foreach ($localHeadlines[$headline] as $publishedAt) {
            if (abs($result->publishedAt->getTimestamp() - $publishedAt) <= 259200) {
                return true;
            }
        }

        return false;
    }

    private function normalizeHeadline(string $headline): string
    {
        $normalized = preg_replace('/[^\pL\pN]+/u', ' ', strtolower(trim($headline)));

        return trim($normalized ?? '');
    }
}
