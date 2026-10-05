<?php

declare(strict_types=1);

namespace App\Tests\Unit\Shared\Search;

use App\Shared\Search\Service\GdeltSearchProvider;
use App\Shared\Search\ValueObject\NewsSearchQuery;
use App\Shared\Search\ValueObject\NewsSearchResult;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

#[CoversClass(GdeltSearchProvider::class)]
#[CoversClass(NewsSearchResult::class)]
final class GdeltSearchProviderTest extends TestCase
{
    public function testNormalizesAndCachesRecentResults(): void
    {
        $calls = 0;
        $client = new MockHttpClient(function (string $method, string $url, array $options) use (&$calls): MockResponse {
            $calls++;
            self::assertSame('GET', $method);
            self::assertSame('https://api.gdeltproject.org/api/v2/doc/doc', strtok($url, '?'));
            self::assertSame('ArtList', $options['query']['mode']);
            self::assertSame('json', $options['query']['format']);
            self::assertSame('3months', $options['query']['timespan']);

            return new MockResponse(json_encode([
                'articles' => [[
                    'title' => 'A global story',
                    'url' => 'https://publisher.example/story',
                    'domain' => 'publisher.example',
                    'seendate' => '20260928T090000Z',
                    'language' => 'English',
                    'sourcecountry' => 'US',
                    'socialimage' => 'https://publisher.example/image.jpg',
                ]],
            ], JSON_THROW_ON_ERROR));
        });
        $logHandler = new TestHandler();
        $logger = new Logger('test');
        $logger->pushHandler($logHandler);
        $provider = new GdeltSearchProvider($client, new ArrayAdapter(), $logger, true, 6);
        $query = new NewsSearchQuery('climate news');

        $first = $provider->search($query);
        $second = $provider->search($query);

        if ($first->notice !== null) {
            self::fail((string) ($logHandler->getRecords()[0]['context']['error'] ?? $first->notice));
        }
        self::assertCount(1, $first->results);
        self::assertSame('A global story', $first->results[0]->title);
        self::assertSame('publisher.example', $first->results[0]->source);
        self::assertSame('US', $first->results[0]->country);
        self::assertSame('English', $first->results[0]->language);
        self::assertSame('2026-09-28 09:00:00', $first->results[0]->publishedAt?->format('Y-m-d H:i:s'));
        self::assertSame('https://publisher.example/image.jpg', $first->results[0]->image);
        self::assertSame('GDELT', $first->results[0]->provider);
        self::assertCount(1, $second->results);
        self::assertSame(1, $calls);
    }

    public function testDisabledProviderShowsHonestConfigurationNotice(): void
    {
        $client = new MockHttpClient(static function (): never {
            self::fail('Disabled GDELT search must not call the provider.');
        });
        $provider = new GdeltSearchProvider($client, new ArrayAdapter(), new NullLogger(), false, 10);

        $response = $provider->search(new NewsSearchQuery('election'));

        self::assertSame([], $response->results);
        self::assertStringContainsString('not configured', (string) $response->notice);
        self::assertStringContainsString('connected sources', (string) $response->notice);
    }

    public function testHistoricalSearchExplainsRollingWindowAndDoesNotCallProvider(): void
    {
        $client = new MockHttpClient(static function (): MockResponse {
            self::fail('GDELT should not be called for dates outside its recent article window.');
        });
        $provider = new GdeltSearchProvider($client, new ArrayAdapter(), new NullLogger(), true, 6);
        $response = $provider->search(new NewsSearchQuery('pandemic', new \DateTimeImmutable('2020-01-01'), new \DateTimeImmutable('2020-12-31')));

        self::assertSame([], $response->results);
        self::assertStringContainsString('recent three months', (string) $response->notice);
    }

    public function testUsesConfiguredBaseUrlAndPreservesGlobalSearchFilters(): void
    {
        $client = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            self::assertSame('http://api.gdeltproject.org/api/v2/doc/doc', strtok($url, '?'));
            self::assertStringContainsString('sourcecountry:', $options['query']['query']);
            self::assertStringContainsString('domainis:example.com', $options['query']['query']);
            self::assertArrayHasKey('startdatetime', $options['query']);
            self::assertArrayHasKey('enddatetime', $options['query']);
            self::assertArrayNotHasKey('timespan', $options['query']);

            return new MockResponse('{"articles":[]}');
        });
        $provider = new GdeltSearchProvider($client, new ArrayAdapter(), new NullLogger(), true, 10, 'http://api.gdeltproject.org');
        $query = new NewsSearchQuery(
            'climate',
            new \DateTimeImmutable('-2 days'),
            new \DateTimeImmutable('now'),
            'US',
            null,
            null,
            null,
            'example.com',
        );

        $response = $provider->search($query);

        self::assertSame([], $response->results);
        self::assertNull($response->notice);
    }

    public function testPartialHistoricalRangeSearchesTheRecentOverlap(): void
    {
        $client = new MockHttpClient(static function (string $method, string $url, array $options): MockResponse {
            $expectedStart = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
                ->modify('-3 months')->setTime(0, 0)->format('YmdHis');
            self::assertSame($expectedStart, $options['query']['startdatetime']);

            return new MockResponse('{"articles":[]}');
        });
        $provider = new GdeltSearchProvider($client, new ArrayAdapter(), new NullLogger(), true, 6);
        $response = $provider->search(new NewsSearchQuery(
            'climate',
            new \DateTimeImmutable('2020-01-01', new \DateTimeZone('UTC')),
            new \DateTimeImmutable('now', new \DateTimeZone('UTC')),
        ));

        self::assertStringContainsString('Older dates', (string) $response->notice);
    }

    public function testProviderFailureReturnsUsefulFallbackNotice(): void
    {
        $client = new MockHttpClient([
            new MockResponse('upstream unavailable', [
                'http_code' => 503,
            ])]);
        $logHandler = new TestHandler();
        $logger = new Logger('test');
        $logger->pushHandler($logHandler);
        $provider = new GdeltSearchProvider($client, new ArrayAdapter(), $logger, true, 10);
        $response = $provider->search(new NewsSearchQuery('election', category: 'politics'));

        self::assertSame([], $response->results);
        self::assertStringContainsString('temporarily unavailable', (string) $response->notice);
        $context = $logHandler->getRecords()[0]['context'];
        self::assertSame('upstream_error', $context['category']);
        self::assertSame('election', $context['query']);
        self::assertSame('politics', $context['search_category']);
        self::assertIsNumeric($context['duration_ms']);
    }

    public function testRateLimitIsHandledAndLoggedWithoutParsingItsBody(): void
    {
        $client = new MockHttpClient([
            new MockResponse('rate limited', [
                'http_code' => 429,
            ])]);
        $logHandler = new TestHandler();
        $logger = new Logger('test');
        $logger->pushHandler($logHandler);
        $provider = new GdeltSearchProvider($client, new ArrayAdapter(), $logger, true, 10);

        $response = $provider->search(new NewsSearchQuery('election'));

        self::assertSame([], $response->results);
        self::assertStringContainsString('temporarily unavailable', (string) $response->notice);
        self::assertSame('rate_limited', $logHandler->getRecords()[0]['context']['category']);
    }

    public function testMissingEndpointIsLoggedAsEndpointNotFound(): void
    {
        $client = new MockHttpClient([
            new MockResponse('not found', [
                'http_code' => 404,
            ])]);
        $logHandler = new TestHandler();
        $logger = new Logger('test');
        $logger->pushHandler($logHandler);
        $provider = new GdeltSearchProvider($client, new ArrayAdapter(), $logger, true, 10);

        $response = $provider->search(new NewsSearchQuery('world news'));

        self::assertSame([], $response->results);
        self::assertStringContainsString('temporarily unavailable', (string) $response->notice);
        self::assertSame('endpoint_not_found', $logHandler->getRecords()[0]['context']['category']);
    }

    public function testMalformedJsonFallsBackAndEmptyArticleListIsAValidNoResultsResponse(): void
    {
        $client = new MockHttpClient([
            new MockResponse('<html>upstream error</html>'),
            new MockResponse('{"articles":[]}'),
        ]);
        $logger = new Logger('test');
        $logHandler = new TestHandler();
        $logger->pushHandler($logHandler);
        $provider = new GdeltSearchProvider($client, new ArrayAdapter(), $logger, true, 10);

        $failed = $provider->search(new NewsSearchQuery('earthquake'));
        $empty = $provider->search(new NewsSearchQuery('climate'));

        self::assertSame([], $failed->results);
        self::assertStringContainsString('temporarily unavailable', (string) $failed->notice);
        self::assertSame('malformed_json', $logHandler->getRecords()[0]['context']['category']);
        self::assertSame([], $empty->results);
        self::assertNull($empty->notice);
    }

    public function testTransportFailureIsCategorizedAndReturnsFriendlyFallback(): void
    {
        $client = new MockHttpClient(static function (): never {
            throw new TransportException('Idle timeout reached for "http://api.gdeltproject.org".');
        });
        $logHandler = new TestHandler();
        $logger = new Logger('test');
        $logger->pushHandler($logHandler);
        $provider = new GdeltSearchProvider($client, new ArrayAdapter(), $logger, true, 10);

        $response = $provider->search(new NewsSearchQuery('weather'));

        self::assertSame([], $response->results);
        self::assertStringContainsString('temporarily unavailable', (string) $response->notice);
        self::assertSame('timeout', $logHandler->getRecords()[0]['context']['category']);
    }

    #[DataProvider('transportFailureCategories')]
    public function testTransportFailuresHaveDistinctLogCategories(string $message, string $expectedCategory): void
    {
        $client = new MockHttpClient(static function () use ($message): never {
            throw new TransportException($message);
        });
        $logHandler = new TestHandler();
        $logger = new Logger('test');
        $logger->pushHandler($logHandler);
        $provider = new GdeltSearchProvider($client, new ArrayAdapter(), $logger, true, 10);

        $response = $provider->search(new NewsSearchQuery('flooding'));

        self::assertSame([], $response->results);
        self::assertStringContainsString('temporarily unavailable', (string) $response->notice);
        self::assertSame($expectedCategory, $logHandler->getRecords()[0]['context']['category']);
    }

    public static function transportFailureCategories(): iterable
    {
        yield 'DNS lookup' => ['Could not resolve host: api.gdeltproject.org', 'dns_error'];
        yield 'connection refused' => ['Failed to connect: Connection refused', 'connection_error'];
        yield 'TLS verification' => ['SSL certificate problem: unable to get local issuer certificate', 'tls_error'];
    }

    public function testRejectsUnsafeResultUrlsAndImages(): void
    {
        self::assertNull(NewsSearchResult::fromGdelt([
            'title' => 'Bad',
            'url' => 'javascript:alert(1)',
        ]));
        $result = NewsSearchResult::fromGdelt([
            'title' => 'Safe story',
            'url' => 'https://news.example/story',
            'domain' => 'unrelated.example',
            'socialimage' => 'data:text/html,bad',
        ]);
        self::assertInstanceOf(NewsSearchResult::class, $result);
        self::assertSame('news.example', $result->source);
        self::assertNull($result->image);
    }
}
