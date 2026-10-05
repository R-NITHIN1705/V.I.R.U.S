<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use App\Article\Entity\Article;
use App\Shared\Repository\CategoryRepositoryInterface;
use App\Shared\Search\Service\UnifiedNewsSearchService;
use App\Shared\Search\ValueObject\NewsSearchQuery;
use App\Shared\Search\ValueObject\NewsSearchResult;
use App\Shared\Search\ValueObject\UnifiedSearchResponse;
use App\Source\Repository\SourceRepositoryInterface;
use App\User\Entity\User;
use App\User\Repository\UserArticleBookmarkRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SearchController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly UnifiedNewsSearchService $searchService,
        private readonly CategoryRepositoryInterface $categories,
        private readonly SourceRepositoryInterface $sources,
        private readonly UserArticleBookmarkRepositoryInterface $bookmarks,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('/search', name: 'app_search')]
    public function __invoke(
        Request $request,
    ): Response {
        $query = $this->queryString($request->query->get('q', ''));
        $category = $this->queryString($request->query->get('category', ''));
        $country = strtoupper($this->queryString($request->query->get('country', '')));
        $language = strtolower($this->queryString($request->query->get('language', '')));
        $sourceValue = $request->query->get('source');
        $sourceIdValue = is_scalar($sourceValue) ? filter_var($sourceValue, FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => 1,
            ],
        ]) : false;
        $sourceId = is_int($sourceIdValue) ? $sourceIdValue : null;
        $period = $this->queryString($request->query->get('period', 'recent')) ?: 'recent';
        $rawDateFrom = $this->queryString($request->query->get('date_from', ''));
        $rawDateTo = $this->queryString($request->query->get('date_to', ''));
        $dateFrom = $this->parseDate($rawDateFrom);
        $dateTo = $this->parseDate($rawDateTo, endOfDay: true);
        $notice = null;
        $invalidFilters = false;

        if (strlen($query) > 256) {
            $query = '';
            $notice = 'Search terms must be 256 characters or fewer.';
            $invalidFilters = true;
        }
        if ($country !== '' && preg_match('/^[A-Z]{2}$/', $country) !== 1) {
            $country = '';
            $notice = 'Use a two-letter ISO country code, such as IN, US, or GB.';
            $invalidFilters = true;
        }
        if ($language !== '' && preg_match('/^[a-z]{2,3}$/', $language) !== 1) {
            $language = '';
            $notice = 'Language must be a two or three-letter language code.';
            $invalidFilters = true;
        }
        if (($rawDateFrom !== '' && $dateFrom === null) || ($rawDateTo !== '' && $dateTo === null)) {
            $notice = 'Enter dates using the calendar controls.';
            $invalidFilters = true;
        }

        $yearValue = $request->query->get('year');
        [$dateFrom, $dateTo] = $this->resolvePeriod($period, $dateFrom, $dateTo, $yearValue);
        if ($period === 'year' && $dateFrom === null) {
            $notice = 'Choose a year from 1979 through the current year.';
            $invalidFilters = true;
        }
        if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
            $dateFrom = null;
            $dateTo = null;
            $notice = 'The start date must be before the end date.';
            $invalidFilters = true;
        }

        $searchResponse = $invalidFilters ? new UnifiedSearchResponse([], []) : $this->searchService->search(new NewsSearchQuery(
            $query,
            $dateFrom,
            $dateTo,
            $country !== '' ? $country : null,
            $language !== '' ? $language : null,
            $category !== '' ? $category : null,
            $sourceId,
        ));
        $notice ??= $searchResponse->notice;
        $user = $this->controller->getUser();
        $bookmarkedArticleIds = $user instanceof User && $searchResponse->localArticles !== []
            ? $this->bookmarks->getBookmarkedArticleIds($user, array_map(static fn ($article): int => (int) $article->getId(), $searchResponse->localArticles))
            : [];
        $historical = $dateFrom !== null && $dateFrom < $this->clock->now()->modify('-3 months');
        $history = $historical ? $this->groupHistory($searchResponse->localArticles, $searchResponse->externalResults) : [];

        $template = $request->headers->has('HX-Request')
            ? 'search/_results.html.twig'
            : 'search/index.html.twig';

        return $this->controller->render($template, [
            'query' => $query,
            'localArticles' => $searchResponse->localArticles,
            'externalResults' => $searchResponse->externalResults,
            'bookmarkedArticleIds' => $bookmarkedArticleIds,
            'categories' => $this->categories->findAllOrderedByWeight(),
            'sources' => $this->sources->findEnabled(),
            'filters' => [
                'period' => $period,
                'date_from' => $rawDateFrom,
                'date_to' => $rawDateTo,
                'year' => is_scalar($yearValue) ? (string) $yearValue : '',
                'country' => $country,
                'language' => $language,
                'category' => $category,
                'source' => $sourceId,
            ],
            'notice' => $notice,
            'historical' => $historical,
            'history' => $history,
        ]);
    }

    private function parseDate(string $value, bool $endOfDay = false): ?\DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (! $date instanceof \DateTimeImmutable || $date->format('Y-m-d') !== $value) {
            return null;
        }

        return $endOfDay ? $date->setTime(23, 59, 59) : $date;
    }

    private function queryString(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    /**
     * @return array{?\DateTimeImmutable, ?\DateTimeImmutable}
     */
    private function resolvePeriod(string $period, ?\DateTimeImmutable $from, ?\DateTimeImmutable $to, mixed $year): array
    {
        $today = new \DateTimeImmutable('today');
        return match ($period) {
            'today' => [$today, $today->setTime(23, 59, 59)],
            'yesterday' => [$today->modify('-1 day'), $today->modify('-1 day')->setTime(23, 59, 59)],
            'week' => [$today->modify('monday this week'), $today->setTime(23, 59, 59)],
            'month' => [$today->modify('first day of this month'), $today->setTime(23, 59, 59)],
            'year' => $this->yearRange($year),
            'custom' => [$from, $to],
            default => [null, null],
        };
    }

    /**
     * @return array{?\DateTimeImmutable, ?\DateTimeImmutable}
     */
    private function yearRange(mixed $year): array
    {
        $year = filter_var($year, FILTER_VALIDATE_INT);
        if (! is_int($year) || $year < 1979 || $year > (int) date('Y')) {
            return [null, null];
        }

        return [new \DateTimeImmutable(sprintf('%04d-01-01 00:00:00', $year)), new \DateTimeImmutable(sprintf('%04d-12-31 23:59:59', $year))];
    }

    /** @param list<Article> $articles @param list<\App\Shared\Search\ValueObject\NewsSearchResult> $externalResults
     * @return array<string, array<string, array<string, list<array{article?: Article, external?: NewsSearchResult}>>>>
     */
    private function groupHistory(array $articles, array $externalResults): array
    {
        $groups = [];
        foreach ($articles as $article) {
            $date = $article->getPublishedAt() ?? $article->getFetchedAt();
            $groups[$date->format('Y')][$date->format('m')][$date->format('d')][] = [
                'article' => $article,
            ];
        }
        foreach ($externalResults as $result) {
            $date = $result->publishedAt ?? new \DateTimeImmutable('now');
            $groups[$date->format('Y')][$date->format('m')][$date->format('d')][] = [
                'external' => $result,
            ];
        }
        krsort($groups);
        foreach ($groups as &$months) {
            krsort($months);
            foreach ($months as &$days) {
                krsort($days);
            }
            unset($days);
        }
        unset($months);

        return $groups;
    }
}
