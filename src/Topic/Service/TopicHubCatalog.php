<?php

declare(strict_types=1);

namespace App\Topic\Service;

use App\Topic\ValueObject\TopicHub;

/** Reusable catalog for topic hub configuration; further hubs can be added as data. */
final class TopicHubCatalog
{
    /** @var array<string, TopicHub> */
    private array $hubs;

    public function __construct()
    {
        $this->hubs = [
            'sports' => new TopicHub(
                slug: 'sports',
                title: 'Sports',
                description: 'Live scores, fixtures and sports reporting from connected providers and publishers.',
                heroImage: '/images/editorial/sports.jpg',
                heroImageAlt: 'Editorial illustration of a football in a stadium',
                heroImageProvenance: 'AI-generated illustration',
                accentColor: '#3b82f6',
                subtopics: [
                    ['slug' => 'cricket', 'title' => 'Cricket'],
                    ['slug' => 'football', 'title' => 'Football'],
                    ['slug' => 'tennis', 'title' => 'Tennis'],
                    ['slug' => 'f1', 'title' => 'F1'],
                    ['slug' => 'basketball', 'title' => 'Basketball'],
                    ['slug' => 'badminton', 'title' => 'Badminton'],
                    ['slug' => 'hockey', 'title' => 'Hockey'],
                    ['slug' => 'kabaddi', 'title' => 'Kabaddi'],
                ],
                sections: [
                    ['key' => 'live', 'title' => 'Live Now', 'kind' => 'sports_events'],
                    ['key' => 'upcoming', 'title' => 'Upcoming', 'kind' => 'sports_events'],
                    ['key' => 'results', 'title' => 'Recent Results', 'kind' => 'sports_events'],
                    ['key' => 'trending', 'title' => 'Trending', 'kind' => 'trending'],
                    ['key' => 'latest', 'title' => 'Latest Sports News', 'kind' => 'latest'],
                    ['key' => 'deep-dive', 'title' => 'Deep Dive', 'kind' => 'deep_dive'],
                    ['key' => 'related', 'title' => 'Related Topics', 'kind' => 'related_topics'],
                ],
                // Football and Cricket providers are distinct; Sports news comes from publisher feeds.
                providerCapabilities: ['football_live_scores', 'football_fixtures', 'football_results', 'cricket_live_scores', 'cricket_fixtures', 'cricket_results', 'sports_news'],
                articleCategorySlug: 'sports', icon: 'sports',
            ),
            'technology' => new TopicHub(
                slug: 'technology', title: 'Technology',
                description: 'Reporting on technology, products, policy and the people building them.',
                heroImage: '/images/editorial/tech.jpg', heroImageAlt: 'Editorial illustration representing technology',
                heroImageProvenance: 'AI-generated illustration', accentColor: '#3b82f6',
                subtopics: [
                    ['slug' => 'ai', 'title' => 'AI'], ['slug' => 'cybersecurity', 'title' => 'Cybersecurity'],
                    ['slug' => 'gadgets', 'title' => 'Gadgets'], ['slug' => 'software', 'title' => 'Software'],
                    ['slug' => 'startups', 'title' => 'Startups'], ['slug' => 'policy', 'title' => 'Policy'],
                ],
                sections: [
                    ['key' => 'latest', 'title' => 'Latest Technology News', 'kind' => 'latest'],
                    ['key' => 'product-policy', 'title' => 'Product & Policy Updates', 'kind' => 'provider_empty', 'emptyMessage' => 'No verified product-release or policy tracker is connected yet. Technology reporting remains available below.'],
                    ['key' => 'trending', 'title' => 'Trending', 'kind' => 'trending', 'emptyMessage' => 'Technology trend ranking data is not configured.'],
                    ['key' => 'deep-dive', 'title' => 'Deep Dive', 'kind' => 'deep_dive'],
                    ['key' => 'related', 'title' => 'Related Topics', 'kind' => 'related_topics'],
                ],
                providerCapabilities: ['publisher_news'], articleCategorySlug: 'tech', icon: 'technology',
            ),
            'business' => new TopicHub(
                slug: 'business', title: 'Business',
                description: 'Business journalism, with market information kept separate and labeled by its source and update timing.',
                heroImage: '/images/editorial/business.jpg', heroImageAlt: 'Editorial illustration representing business and markets',
                heroImageProvenance: 'AI-generated illustration', accentColor: '#0f766e',
                subtopics: [
                    ['slug' => 'markets', 'title' => 'Markets'], ['slug' => 'economy', 'title' => 'Economy'],
                    ['slug' => 'companies', 'title' => 'Companies'], ['slug' => 'startups', 'title' => 'Startups'],
                    ['slug' => 'trade', 'title' => 'Trade'], ['slug' => 'personal-finance', 'title' => 'Personal Finance'],
                ],
                sections: [
                    ['key' => 'market-data', 'title' => 'Market Data', 'kind' => 'market_data', 'providerLabel' => 'Market data provider', 'emptyMessage' => 'Market data is not configured. No prices or real-time claims are shown.'],
                    ['key' => 'latest', 'title' => 'Latest Business Reporting', 'kind' => 'latest'],
                    ['key' => 'trending', 'title' => 'Business Stories in Focus', 'kind' => 'trending', 'emptyMessage' => 'No source-backed business trend ranking is available yet.'],
                    ['key' => 'deep-dive', 'title' => 'Deep Dive', 'kind' => 'deep_dive'],
                    ['key' => 'related', 'title' => 'Related Topics', 'kind' => 'related_topics'],
                ],
                providerCapabilities: ['publisher_news'], articleCategorySlug: 'business', icon: 'business',
            ),
            'science' => new TopicHub(
                slug: 'science', title: 'Science',
                description: 'Research, discoveries and science policy, grounded in reporting from connected publishers.',
                heroImage: '/images/editorial/science.jpg', heroImageAlt: 'Editorial illustration representing scientific research',
                heroImageProvenance: 'AI-generated illustration', accentColor: '#0e7490',
                subtopics: [
                    ['slug' => 'climate', 'title' => 'Climate'], ['slug' => 'health-research', 'title' => 'Health Research'],
                    ['slug' => 'physics', 'title' => 'Physics'], ['slug' => 'biology', 'title' => 'Biology'],
                    ['slug' => 'environment', 'title' => 'Environment'], ['slug' => 'archaeology', 'title' => 'Archaeology'],
                ],
                sections: [
                    ['key' => 'latest', 'title' => 'Latest Science News', 'kind' => 'latest'],
                    ['key' => 'research', 'title' => 'Research & Discovery', 'kind' => 'provider_empty', 'emptyMessage' => 'No research-index or paper provider is connected. Publisher reporting is listed separately.'],
                    ['key' => 'trending', 'title' => 'Trending', 'kind' => 'trending'],
                    ['key' => 'deep-dive', 'title' => 'Deep Dive', 'kind' => 'deep_dive'],
                    ['key' => 'related', 'title' => 'Related Topics', 'kind' => 'related_topics'],
                ],
                providerCapabilities: ['publisher_news'], articleCategorySlug: 'science', icon: 'science',
            ),
            'politics' => new TopicHub(
                slug: 'politics', title: 'Politics',
                description: 'Political coverage with source-grounded labels. This hub does not rank politicians or make persuasive recommendations.',
                heroImage: '/images/editorial/politics.jpg', heroImageAlt: 'Editorial illustration representing civic institutions',
                heroImageProvenance: 'AI-generated illustration', accentColor: '#7c3aed',
                subtopics: [
                    ['slug' => 'world', 'title' => 'World Politics'], ['slug' => 'india', 'title' => 'India'],
                    ['slug' => 'elections', 'title' => 'Elections'], ['slug' => 'parliament', 'title' => 'Parliament'],
                    ['slug' => 'policy', 'title' => 'Policy'], ['slug' => 'diplomacy', 'title' => 'Diplomacy'],
                ],
                sections: [
                    ['key' => 'coverage-labels', 'title' => 'Coverage Labels', 'kind' => 'label_guide', 'labels' => [
                        ['title' => 'Fact', 'description' => 'A verifiable fact supported by cited evidence.'],
                        ['title' => 'Official statement', 'description' => 'A statement attributed to an identified official or institution.'],
                        ['title' => 'Claim', 'description' => 'An assertion whose supporting evidence is not established here.'],
                        ['title' => 'Analysis', 'description' => 'Interpretation presented as analysis, not as a factual report.'],
                    ], 'emptyMessage' => 'Stories will receive one of these labels only when source material supports it.'],
                    ['key' => 'latest', 'title' => 'Latest Political Coverage', 'kind' => 'classified_latest'],
                    ['key' => 'deep-dive', 'title' => 'Context & Analysis', 'kind' => 'deep_dive'],
                    ['key' => 'related', 'title' => 'Related Topics', 'kind' => 'related_topics'],
                ],
                providerCapabilities: [], articleCategorySlug: 'politics',
                articleEmptyMessage: 'Political stories are not displayed until Fact, Official statement, Claim, or Analysis labels can be supported by the source material.', icon: 'politics',
            ),
            'movies' => new TopicHub(
                slug: 'movies', title: 'Movies & Entertainment',
                description: 'Film releases, filmmaking and entertainment reporting. Box-office figures and ratings appear only with a named source.',
                heroImage: null, heroImageAlt: null, heroImageProvenance: null, accentColor: '#be185d',
                subtopics: [
                    ['slug' => 'film-releases', 'title' => 'Film Releases'], ['slug' => 'box-office', 'title' => 'Box Office'],
                    ['slug' => 'awards', 'title' => 'Awards'], ['slug' => 'reviews', 'title' => 'Reviews'],
                    ['slug' => 'filmmaking', 'title' => 'Filmmaking'], ['slug' => 'culture', 'title' => 'Culture'],
                ],
                sections: [
                    ['key' => 'tmdb-trending', 'title' => 'Trending Movies', 'kind' => 'tmdb_trending'],
                    ['key' => 'latest', 'title' => 'Latest Film & Entertainment News', 'kind' => 'latest'],
                    ['key' => 'box-office', 'title' => 'Box Office', 'kind' => 'provider_empty', 'emptyMessage' => 'Box-office data is not configured. No figures are displayed.'],
                    ['key' => 'ratings', 'title' => 'Ratings', 'kind' => 'provider_empty', 'emptyMessage' => 'No ratings provider is configured. Ratings will appear only with a named source.'],
                    ['key' => 'trending', 'title' => 'Trending', 'kind' => 'trending'],
                    ['key' => 'related', 'title' => 'Related Topics', 'kind' => 'related_topics'],
                ],
                providerCapabilities: ['tmdb_trending_movies'], articleCategorySlug: null,
                articleEmptyMessage: 'No film or entertainment news source is connected yet.', icon: 'movies',
            ),
            'space' => new TopicHub(
                slug: 'space', title: 'Space',
                description: 'Spaceflight, astronomy and space policy from dedicated news and mission sources.',
                heroImage: null, heroImageAlt: null, heroImageProvenance: null, accentColor: '#2563eb',
                subtopics: [
                    ['slug' => 'missions', 'title' => 'Missions'], ['slug' => 'astronomy', 'title' => 'Astronomy'],
                    ['slug' => 'launches', 'title' => 'Launches'], ['slug' => 'satellites', 'title' => 'Satellites'],
                    ['slug' => 'planetary-science', 'title' => 'Planetary Science'], ['slug' => 'space-policy', 'title' => 'Space Policy'],
                ],
                sections: [
                    ['key' => 'launches', 'title' => 'Launch Schedule', 'kind' => 'provider_empty', 'emptyMessage' => 'No launch schedule provider is configured. No upcoming launch events are inferred.'],
                    ['key' => 'latest', 'title' => 'Latest Space News', 'kind' => 'latest'],
                    ['key' => 'missions', 'title' => 'Mission Updates', 'kind' => 'provider_empty', 'emptyMessage' => 'No mission-status provider is configured.'],
                    ['key' => 'deep-dive', 'title' => 'Deep Dive', 'kind' => 'deep_dive'],
                    ['key' => 'related', 'title' => 'Related Topics', 'kind' => 'related_topics'],
                ],
                providerCapabilities: [], articleCategorySlug: null,
                articleEmptyMessage: 'No dedicated Space news source is connected yet.', icon: 'space',
            ),
            'automotive' => new TopicHub(
                slug: 'automotive', title: 'Automotive',
                description: 'Automotive reporting with explicit source-status labels. Unverified rumors are not presented as official news.',
                heroImage: null, heroImageAlt: null, heroImageProvenance: null, accentColor: '#b45309',
                subtopics: [
                    ['slug' => 'electric-vehicles', 'title' => 'Electric Vehicles'], ['slug' => 'new-models', 'title' => 'New Models'],
                    ['slug' => 'safety', 'title' => 'Safety'], ['slug' => 'motorsport', 'title' => 'Motorsport'],
                    ['slug' => 'manufacturing', 'title' => 'Manufacturing'], ['slug' => 'policy', 'title' => 'Policy'],
                ],
                sections: [
                    ['key' => 'source-status', 'title' => 'Source Status', 'kind' => 'label_guide', 'labels' => [
                        ['title' => 'Official', 'description' => 'Confirmed in a statement or publication from the responsible organization.'],
                        ['title' => 'Reported', 'description' => 'Published by a named news source; not an official confirmation.'],
                        ['title' => 'Rumored', 'description' => 'Unconfirmed reporting or speculation, labeled as such.'],
                    ], 'emptyMessage' => 'Automotive stories are held until their source status can be labeled.'],
                    ['key' => 'latest', 'title' => 'Latest Automotive News', 'kind' => 'classified_latest'],
                    ['key' => 'deep-dive', 'title' => 'Reviews & Deep Dives', 'kind' => 'deep_dive'],
                    ['key' => 'related', 'title' => 'Related Topics', 'kind' => 'related_topics'],
                ],
                providerCapabilities: [], articleCategorySlug: null,
                articleEmptyMessage: 'Automotive articles will appear once source-status labels (Official, Reported, Rumored) are available.', icon: 'automotive',
            ),
            'tv-ott' => new TopicHub(
                slug: 'tv-ott', title: 'TV & OTT',
                description: 'Television and streaming coverage, with release and availability information shown only when sourced.',
                heroImage: null, heroImageAlt: null, heroImageProvenance: null, accentColor: '#9333ea',
                subtopics: [
                    ['slug' => 'streaming', 'title' => 'Streaming'], ['slug' => 'series', 'title' => 'Series'],
                    ['slug' => 'originals', 'title' => 'Originals'], ['slug' => 'renewals', 'title' => 'Renewals'],
                    ['slug' => 'release-dates', 'title' => 'Release Dates'], ['slug' => 'awards', 'title' => 'Awards'],
                ],
                sections: [
                    ['key' => 'releases', 'title' => 'Upcoming Releases', 'kind' => 'provider_empty', 'emptyMessage' => 'No verified TV or streaming release calendar is configured.'],
                    ['key' => 'latest', 'title' => 'Latest TV & OTT News', 'kind' => 'latest'],
                    ['key' => 'ratings', 'title' => 'Ratings', 'kind' => 'provider_empty', 'emptyMessage' => 'No ratings provider is configured; no ratings are shown.'],
                    ['key' => 'deep-dive', 'title' => 'Deep Dive', 'kind' => 'deep_dive'],
                    ['key' => 'related', 'title' => 'Related Topics', 'kind' => 'related_topics'],
                ],
                providerCapabilities: [], articleCategorySlug: null,
                articleEmptyMessage: 'No dedicated TV or OTT news source is connected yet.', icon: 'television',
            ),
            'general' => new TopicHub(
                slug: 'general', title: 'General News',
                description: 'A focused news hub for world, India, regional and human-interest reporting from real publishers.',
                heroImage: '/images/editorial/world.jpg', heroImageAlt: 'Editorial illustration representing world news',
                heroImageProvenance: 'AI-generated illustration', accentColor: '#2563eb',
                subtopics: [
                    ['slug' => 'world', 'title' => 'World'], ['slug' => 'india', 'title' => 'India'],
                    ['slug' => 'regional', 'title' => 'Regional'], ['slug' => 'human-interest', 'title' => 'Human Interest'],
                ],
                sections: [
                    ['key' => 'latest', 'title' => 'Latest General News', 'kind' => 'latest'],
                    ['key' => 'deep-dive', 'title' => 'Context & Background', 'kind' => 'deep_dive'],
                    ['key' => 'related', 'title' => 'Related Topics', 'kind' => 'related_topics'],
                ],
                providerCapabilities: ['publisher_news'], articleCategorySlug: 'world',
                articleEmptyMessage: 'No general-news stories are available. This hub only uses publisher news classified to the World category; it does not fill with unrelated content.', icon: 'globe',
            ),
        ];
    }

    public function find(string $slug): ?TopicHub
    {
        return $this->hubs[$slug] ?? null;
    }

    /** @return list<TopicHub> */
    public function all(): array
    {
        return array_values($this->hubs);
    }

    /**
     * Resolve stored category or keyword slugs into the matching hub route.
     * Subtopic slugs become a selected subtopic on that hub.
     *
     * @return array{slug: string, subtopic?: string}|null
     */
    public function routeForCategorySlug(string $categorySlug): ?array
    {
        $slug = strtolower(trim($categorySlug));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        $hubAliases = [
            'artificial-intelligence' => ['technology', 'ai'],
            'tech-news' => ['technology', null],
            'film' => ['movies', 'film-releases'],
            'films' => ['movies', 'film-releases'],
            'movie' => ['movies', 'film-releases'],
            'movies-entertainment' => ['movies', null],
            'entertainment' => ['movies', null],
            'box-office' => ['movies', 'box-office'],
            'tv' => ['tv-ott', null],
            'television' => ['tv-ott', 'series'],
            'ott' => ['tv-ott', 'streaming'],
            'streaming' => ['tv-ott', 'streaming'],
            'auto' => ['automotive', null],
            'cars' => ['automotive', null],
            'automobiles' => ['automotive', null],
            'spaceflight' => ['space', 'missions'],
            'astronomy' => ['space', 'astronomy'],
            'world-news' => ['general', 'world'],
            'general-news' => ['general', null],
            'india-news' => ['general', 'india'],
            'regional-news' => ['general', 'regional'],
            'human-interest' => ['general', 'human-interest'],
        ];

        if (isset($hubAliases[$slug])) {
            [$hubSlug, $subtopic] = $hubAliases[$slug];

            return ['slug' => $hubSlug, ...($subtopic !== null ? ['subtopic' => $subtopic] : [])];
        }

        foreach ($this->hubs as $hub) {
            if ($hub->slug === $slug || $hub->articleCategorySlug === $slug) {
                return ['slug' => $hub->slug];
            }
        }

        $subtopicMatches = [];
        foreach ($this->hubs as $hub) {
            if ($hub->hasSubtopic($slug)) {
                $subtopicMatches[] = $hub;
            }
        }

        if (\count($subtopicMatches) === 1) {
            return ['slug' => $subtopicMatches[0]->slug, 'subtopic' => $slug];
        }

        return null;
    }
}
