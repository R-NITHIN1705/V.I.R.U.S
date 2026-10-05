<?php

declare(strict_types=1);

namespace App\Topic\Service;

use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/** Source-backed trending movie metadata from TMDB; never a box-office feed. */
final readonly class TmdbTrendingService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheItemPoolInterface $cache,
        private string $readAccessToken,
        private bool $enabled,
    ) {
    }

    /** @return array{state: string, movies: list<array<string, mixed>>, providerName: string} */
    public function getTrendingMovies(): array
    {
        $empty = ['state' => 'not_configured', 'movies' => [], 'providerName' => 'TMDB'];
        if (! $this->enabled || trim($this->readAccessToken) === '') {
            return $empty;
        }

        $item = $this->cache->getItem('topic.movies.tmdb.trending.v1');
        if ($item->isHit()) {
            return ['state' => 'available', 'movies' => $item->get(), 'providerName' => 'TMDB'];
        }

        try {
            $response = $this->httpClient->request('GET', 'https://api.themoviedb.org/3/trending/movie/week', [
                'headers' => ['Authorization' => 'Bearer ' . $this->readAccessToken, 'Accept' => 'application/json'],
                'query' => ['language' => 'en-US'],
                'timeout' => 12,
            ]);
            if ($response->getStatusCode() !== 200) {
                return ['state' => 'unavailable', 'movies' => [], 'providerName' => 'TMDB'];
            }

            $payload = $response->toArray(false);
            if (! is_array($payload['results'] ?? null)) {
                return ['state' => 'unavailable', 'movies' => [], 'providerName' => 'TMDB'];
            }

            $movies = [];
            foreach ($payload['results'] as $movie) {
                if (! is_array($movie)) {
                    continue;
                }
                $title = $movie['title'] ?? $movie['name'] ?? null;
                if (! is_string($title) || trim($title) === '') {
                    continue;
                }
                $rating = $movie['vote_average'] ?? null;
                $voteCount = $movie['vote_count'] ?? null;
                $movies[] = [
                    'id' => is_int($movie['id'] ?? null) ? $movie['id'] : null,
                    'title' => $title,
                    'overview' => is_string($movie['overview'] ?? null) ? $movie['overview'] : '',
                    'releaseDate' => is_string($movie['release_date'] ?? null) ? $movie['release_date'] : '',
                    'posterPath' => is_string($movie['poster_path'] ?? null) && str_starts_with($movie['poster_path'], '/') ? $movie['poster_path'] : null,
                    'rating' => is_numeric($rating) && (int) $voteCount > 0 ? (float) $rating : null,
                    'voteCount' => is_numeric($voteCount) ? (int) $voteCount : 0,
                ];
            }

            $item->set($movies);
            $item->expiresAfter(900);
            $this->cache->save($item);

            return ['state' => 'available', 'movies' => $movies, 'providerName' => 'TMDB'];
        } catch (Throwable) {
            return ['state' => 'unavailable', 'movies' => [], 'providerName' => 'TMDB'];
        }
    }
}
