<?php

declare(strict_types=1);

namespace App\Sports\Service;

use Psr\Cache\CacheItemPoolInterface;
use Throwable;

final readonly class SportsService
{
    public function __construct(
        private SportsProviderInterface $provider,
        private CacheItemPoolInterface $cache,
        private bool $enabled,
        private string $providerName = 'sports provider',
    ) {
    }

    public function getFeed(): SportsFeed
    {
        if (! $this->enabled) {
            return new SportsFeed('not_configured', ['live' => [], 'upcoming' => [], 'results' => []], $this->providerName);
        }

        $item = $this->cache->getItem('sports.events.v1');
        if ($item->isHit()) {
            return new SportsFeed('available', $item->get(), $this->providerName);
        }

        try {
            $events = $this->provider->getEvents();
            $item->set($events);
            $item->expiresAfter(45);
            $this->cache->save($item);

            return new SportsFeed('available', $events, $this->providerName);
        } catch (Throwable) {
            return new SportsFeed('unavailable', ['live' => [], 'upcoming' => [], 'results' => []], $this->providerName);
        }
    }
}
