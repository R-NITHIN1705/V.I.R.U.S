<?php

declare(strict_types=1);

namespace App\Sports\Service;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class ApiSportsProvider implements SportsProviderInterface
{
    public function __construct(private HttpClientInterface $httpClient, private string $apiKey, private string $baseUrl = 'https://v3.football.api-sports.io')
    {
    }

    public function getEvents(): array
    {
        return [
            'live' => $this->fetchFixtures(['live' => 'all'], 'live'),
            'upcoming' => $this->fetchFixtures(['next' => 20], 'upcoming'),
            'results' => $this->fetchFixtures(['last' => 20], 'results'),
        ];
    }

    /** @param array<string, int|string> $query @return list<array<string, mixed>> */
    private function fetchFixtures(array $query, string $bucket): array
    {
        $response = $this->httpClient->request('GET', rtrim($this->baseUrl, '/') . '/fixtures', [
            'query' => $query,
            'headers' => ['x-apisports-key' => $this->apiKey],
            'timeout' => 5,
        ]);
        $status = $response->getStatusCode();
        if ($status === 401 || $status === 403) {
            throw new \RuntimeException('Sports provider credentials were rejected.');
        }
        if ($status === 429 || $status >= 500) {
            throw new \RuntimeException('Sports provider is temporarily unavailable.');
        }
        if ($status !== 200) {
            throw new \RuntimeException('Sports provider returned an unsuccessful response.');
        }

        $payload = $response->toArray(false);
        if (!isset($payload['response']) || !is_array($payload['response'])) {
            throw new \UnexpectedValueException('Sports provider response was malformed.');
        }

        $events = [];
        foreach ($payload['response'] as $event) {
            if (!is_array($event) || !isset($event['fixture'], $event['teams'], $event['league'])) {
                continue;
            }
            $fixture = $event['fixture'];
            $teams = $event['teams'];
            $statusText = (string) ($fixture['status']['long'] ?? '');
            $events[] = [
                'league' => (string) ($event['league']['name'] ?? ''),
                'home' => (string) ($teams['home']['name'] ?? ''),
                'away' => (string) ($teams['away']['name'] ?? ''),
                'homeLogo' => $this->safeImageUrl($teams['home']['logo'] ?? null),
                'awayLogo' => $this->safeImageUrl($teams['away']['logo'] ?? null),
                'date' => (string) ($fixture['date'] ?? ''),
                'venue' => (string) ($fixture['venue']['name'] ?? ''),
                'status' => $statusText,
                'state' => $bucket,
                'homeScore' => $event['goals']['home'] ?? null,
                'awayScore' => $event['goals']['away'] ?? null,
                'elapsed' => $fixture['status']['elapsed'] ?? null,
            ];
        }

        return $events;
    }

    private function safeImageUrl(mixed $url): ?string
    {
        return is_string($url) && preg_match('#^https://#i', $url) === 1 ? $url : null;
    }
}
