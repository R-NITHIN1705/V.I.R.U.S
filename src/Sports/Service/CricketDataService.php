<?php

declare(strict_types=1);

namespace App\Sports\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/** Read-only current-match feed from CricketData (cricapi.com). */
final readonly class CricketDataService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheItemPoolInterface $cache,
        private string $apiKey,
        private bool $enabled,
    ) {
    }

    /** @return array{state: string, events: array{live: list<array<string, mixed>>, upcoming: list<array<string, mixed>>, results: list<array<string, mixed>>}, providerName: string} */
    public function getFeed(): array
    {
        $empty = ['live' => [], 'upcoming' => [], 'results' => []];
        if (! $this->enabled || trim($this->apiKey) === '') {
            return ['state' => 'not_configured', 'events' => $empty, 'providerName' => 'CricketData'];
        }

        $item = $this->cache->getItem('sports.cricket.current_matches.v1');
        if ($item->isHit()) {
            return ['state' => 'available', 'events' => $item->get(), 'providerName' => 'CricketData'];
        }

        try {
            $response = $this->httpClient->request('GET', 'https://api.cricapi.com/v1/currentMatches', [
                'query' => ['apikey' => $this->apiKey, 'offset' => 0],
                'timeout' => 10,
            ]);
            if ($response->getStatusCode() !== 200) {
                return ['state' => 'unavailable', 'events' => $empty, 'providerName' => 'CricketData'];
            }

            $payload = $response->toArray(false);
            if (($payload['status'] ?? null) !== 'success' || ! is_array($payload['data'] ?? null)) {
                return ['state' => 'unavailable', 'events' => $empty, 'providerName' => 'CricketData'];
            }

            $events = $this->mapMatches($payload['data']);
            $item->set($events);
            $item->expiresAfter(60);
            $this->cache->save($item);

            return ['state' => 'available', 'events' => $events, 'providerName' => 'CricketData'];
        } catch (Throwable) {
            return ['state' => 'unavailable', 'events' => $empty, 'providerName' => 'CricketData'];
        }
    }

    /** @param list<mixed> $matches @return array{live: list<array<string, mixed>>, upcoming: list<array<string, mixed>>, results: list<array<string, mixed>>} */
    private function mapMatches(array $matches): array
    {
        $events = ['live' => [], 'upcoming' => [], 'results' => []];
        foreach ($matches as $match) {
            if (! is_array($match) || ! is_string($match['name'] ?? null)) {
                continue;
            }

            $started = (bool) ($match['matchStarted'] ?? false);
            $ended = (bool) ($match['matchEnded'] ?? false);
            $bucket = $ended ? 'results' : ($started ? 'live' : 'upcoming');
            $teams = is_array($match['teams'] ?? null) ? array_values(array_filter($match['teams'], 'is_string')) : [];
            $scores = is_array($match['score'] ?? null) ? $match['score'] : [];
            $scoreLines = [];
            foreach ($scores as $score) {
                if (! is_array($score)) {
                    continue;
                }
                $runs = $score['r'] ?? null;
                $wickets = $score['w'] ?? null;
                $overs = $score['o'] ?? null;
                if (is_numeric($runs)) {
                    $line = (string) $runs;
                    if (is_numeric($wickets)) {
                        $line .= '/' . (string) $wickets;
                    }
                    if (is_numeric($overs)) {
                        $line .= ' (' . (string) $overs . ' overs)';
                    }
                    $scoreLines[] = $line;
                }
            }

            $events[$bucket][] = [
                'name' => $match['name'],
                'teams' => $teams,
                'scoreLines' => $scoreLines,
                'status' => is_string($match['status'] ?? null) ? $match['status'] : '',
                'date' => is_string($match['dateTimeGMT'] ?? null) ? $match['dateTimeGMT'] : (is_string($match['date'] ?? null) ? $match['date'] : ''),
                'venue' => is_string($match['venue'] ?? null) ? $match['venue'] : '',
                'matchType' => is_string($match['matchType'] ?? null) ? $match['matchType'] : '',
            ];
        }

        return $events;
    }
}
