<?php

declare(strict_types=1);

namespace App\Shared\Search\Service;

use App\Shared\Search\ValueObject\NewsSearchProviderResponse;
use App\Shared\Search\ValueObject\NewsSearchQuery;
use App\Shared\Search\ValueObject\NewsSearchResult;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class GdeltSearchProvider implements NewsSearchProviderInterface
{
    private const int MAX_RECORDS = 75;

    private const float CONNECT_TIMEOUT_SECONDS = 5.0;

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
        private LoggerInterface $logger,
        #[Autowire('%env(bool:GDELT_ENABLED)%')]
        private bool $enabled,
        #[Autowire('%env(int:GDELT_TIMEOUT)%')]
        private int $timeout,
        #[Autowire('%env(string:GDELT_BASE_URL)%')]
        private string $baseUrl = 'https://api.gdeltproject.org',
    ) {
    }

    public function search(NewsSearchQuery $query): NewsSearchProviderResponse
    {
        $terms = trim($query->query);
        if (! $this->enabled) {
            return new NewsSearchProviderResponse([], 'Global search is not configured. Showing stories from your connected sources.');
        }
        if ($terms === '') {
            return new NewsSearchProviderResponse();
        }

        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $windowStart = $now->modify('-3 months')->setTime(0, 0);
        $requestedStart = $query->from?->setTimezone(new \DateTimeZone('UTC'));
        $requestedEnd = $query->to?->setTimezone(new \DateTimeZone('UTC'));
        if ($requestedEnd !== null && $requestedEnd < $windowStart) {
            return new NewsSearchProviderResponse([], 'GDELT’s article search currently covers the recent three months. Older dates are searched in V.I.R.U.S. sources where available.');
        }

        $searchTerms = $this->buildSearchTerms($terms, $query);
        $parameters = [
            'query' => $searchTerms,
            'mode' => 'ArtList',
            'format' => 'json',
            'maxrecords' => self::MAX_RECORDS,
            'sort' => 'HybridRel',
        ];
        if ($requestedStart !== null || $requestedEnd !== null) {
            $start = $requestedStart === null || $requestedStart < $windowStart ? $windowStart : $requestedStart;
            $end = $requestedEnd === null || $requestedEnd > $now ? $now : $requestedEnd;
            if ($start > $end) {
                return new NewsSearchProviderResponse([], 'No recent global stories fall within that date range. V.I.R.U.S. searches connected sources for older coverage.');
            }
            $parameters['startdatetime'] = $start->format('YmdHis');
            $parameters['enddatetime'] = $end->format('YmdHis');
        } else {
            $parameters['timespan'] = '3months';
        }

        $startedAt = hrtime(true);
        try {
            $endpoint = $this->endpoint();
            $cacheKey = 'gdelt.search.' . hash('sha256', json_encode([
                'endpoint' => $endpoint,
                'parameters' => $parameters,
            ], JSON_THROW_ON_ERROR));

            /** @var list<NewsSearchResult> $results */
            $results = $this->cache->get($cacheKey, function (ItemInterface $item) use ($parameters, $endpoint): array {
                $item->expiresAfter(300);
                $response = $this->httpClient->request('GET', $endpoint, [
                    'query' => $parameters,
                    'timeout' => max(1, min($this->timeout, 10)),
                    'max_duration' => max(2, min($this->timeout + 2, 12)),
                    'buffer' => true,
                    'headers' => [
                        'Accept' => 'application/json',
                    ],
                ]);
                $headersReceived = false;
                foreach ($this->httpClient->stream($response, self::CONNECT_TIMEOUT_SECONDS) as $chunk) {
                    if ($chunk->isTimeout()) {
                        throw new \RuntimeException('[connect_timeout] GDELT did not return response headers within five seconds.');
                    }
                    if ($chunk->isFirst()) {
                        $headersReceived = true;
                        break;
                    }
                }
                if (! $headersReceived) {
                    throw new \RuntimeException('[connect_timeout] GDELT did not return response headers within five seconds.');
                }
                $statusCode = $response->getStatusCode();
                if ($statusCode < 200 || $statusCode >= 300) {
                    $category = match (true) {
                        $statusCode === 404 => 'endpoint_not_found',
                        $statusCode === 429 => 'rate_limited',
                        $statusCode >= 500 => 'upstream_error',
                        default => 'upstream_rejected',
                    };
                    throw new \RuntimeException(sprintf('[%s] GDELT returned HTTP %d.', $category, $statusCode));
                }

                try {
                    $data = json_decode($response->getContent(false), true, 512, JSON_THROW_ON_ERROR);
                } catch (\JsonException $e) {
                    throw new \RuntimeException('[malformed_json] GDELT returned a non-JSON response.', previous: $e);
                }
                if (! is_array($data) || ! is_array($data['articles'] ?? null)) {
                    throw new \RuntimeException('[unexpected_payload] GDELT response did not contain an articles list.');
                }
                $articles = $data['articles'];

                $results = [];
                foreach (array_slice($articles, 0, self::MAX_RECORDS) as $row) {
                    if (! is_array($row)) {
                        continue;
                    }
                    $result = NewsSearchResult::fromGdelt($row);
                    if ($result instanceof NewsSearchResult) {
                        $results[] = $result;
                    }
                }

                return $results;
            });

            $notice = $requestedStart !== null && $requestedStart < $windowStart
                ? 'Global article search covers the recent three months. Older dates are searched in V.I.R.U.S. sources where available.'
                : null;

            return new NewsSearchProviderResponse($results, $notice);
        } catch (\Throwable $e) {
            $durationMs = round((hrtime(true) - $startedAt) / 1_000_000, 1);
            $this->logger->warning('GDELT search failed ({category}) after {duration_ms} ms.', [
                'category' => $this->failureCategory($e),
                'query' => mb_substr($terms, 0, 120),
                'search_category' => $query->category,
                'duration_ms' => $durationMs,
                'exception' => $e::class,
                'error' => $e->getMessage(),
            ]);

            return new NewsSearchProviderResponse([], 'Global search is temporarily unavailable. Showing stories from your connected sources.');
        }
    }

    private function endpoint(): string
    {
        $baseUrl = trim($this->baseUrl);
        $parts = parse_url($baseUrl);
        if (! is_array($parts)
            || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)
            || ! isset($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['query'])
            || isset($parts['fragment'])
            || ! in_array($parts['path'] ?? '', ['', '/'], true)
        ) {
            throw new \RuntimeException('[configuration_error] GDELT_BASE_URL must be an HTTP(S) origin without credentials or a path.');
        }

        return rtrim($baseUrl, '/') . '/api/v2/doc/doc';
    }

    private function failureCategory(\Throwable $e): string
    {
        if (preg_match('/^\[([a-z_]+)\]/', $e->getMessage(), $matches) === 1) {
            return $matches[1];
        }
        if ($e instanceof \JsonException) {
            return 'malformed_json';
        }
        if ($e instanceof TransportExceptionInterface) {
            $message = strtolower($e->getMessage());
            return match (true) {
                str_contains($message, 'timed out'), str_contains($message, 'timeout') => 'timeout',
                str_contains($message, 'resolve host'), str_contains($message, 'name or service not known'), str_contains($message, 'getaddrinfo') => 'dns_error',
                str_contains($message, 'ssl'), str_contains($message, 'tls'), str_contains($message, 'certificate') => 'tls_error',
                str_contains($message, 'connect'), str_contains($message, 'connection refused') => 'connection_error',
                default => 'transport_error',
            };
        }

        return 'provider_error';
    }

    private function buildSearchTerms(string $terms, NewsSearchQuery $query): string
    {
        $parts = [$terms];
        if ($query->country !== null) {
            $countryName = class_exists(\Locale::class)
                ? \Locale::getDisplayRegion('en_' . $query->country, 'en')
                : $query->country;
            $countryFilter = preg_replace('/[^a-z]/', '', strtolower((string) $countryName));
            $parts[] = 'sourcecountry:' . ($countryFilter !== '' ? $countryFilter : $query->country);
        }
        if ($query->language !== null && class_exists(\Locale::class)) {
            $language = \Locale::getDisplayLanguage($query->language, 'en');
            if (is_string($language) && $language !== '' && $language !== $query->language) {
                $parts[] = 'sourcelang:' . strtolower(str_replace(' ', '', $language));
            }
        }
        if ($query->sourceDomain !== null && preg_match('/^(?:[a-z0-9-]+\.)*[a-z0-9-]+\.[a-z]{2,}$/i', $query->sourceDomain) === 1) {
            $parts[] = 'domainis:' . $query->sourceDomain;
        }

        return implode(' ', $parts);
    }
}
