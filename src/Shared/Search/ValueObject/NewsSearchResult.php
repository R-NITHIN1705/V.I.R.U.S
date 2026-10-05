<?php

declare(strict_types=1);

namespace App\Shared\Search\ValueObject;

final readonly class NewsSearchResult
{
    public function __construct(
        public string $title,
        public string $url,
        public string $source,
        public ?\DateTimeImmutable $publishedAt,
        public ?string $language,
        public ?string $country,
        public ?string $category,
        public ?string $image,
        public ?string $snippet,
        public ?string $location,
        public string $provider,
    ) {
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromGdelt(array $row): ?self
    {
        $title = self::stringValue($row['title'] ?? null);
        $url = self::stringValue($row['url'] ?? null);
        if ($title === '' || filter_var($url, FILTER_VALIDATE_URL) === false || ! in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return null;
        }

        $date = null;
        $rawDate = self::stringValue($row['seendate'] ?? null);
        if ($rawDate !== '') {
            $date = \DateTimeImmutable::createFromFormat('!Ymd\THis\Z', $rawDate, new \DateTimeZone('UTC')) ?: null;
        }

        $image = self::stringValue($row['socialimage'] ?? null);
        if ($image === '' || filter_var($image, FILTER_VALIDATE_URL) === false || ! in_array(strtolower((string) parse_url($image, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            $image = null;
        }

        $host = strtolower((string) (parse_url($url, PHP_URL_HOST) ?: ''));
        if ($host === '') {
            return null;
        }
        $host = preg_replace('/^www\./', '', $host) ?? $host;
        $reportedDomain = strtolower(self::stringValue($row['domain'] ?? null));
        $reportedDomain = preg_replace('/^www\./', '', $reportedDomain) ?? $reportedDomain;
        $domain = preg_match('/^(?:[a-z0-9-]+\.)*[a-z0-9-]+\.[a-z]{2,}$/i', $reportedDomain) === 1
            && ($host === $reportedDomain || str_ends_with($host, '.' . $reportedDomain))
            ? $reportedDomain
            : $host;
        $country = strtoupper(self::stringValue($row['sourcecountry'] ?? null));

        return new self(
            $title,
            $url,
            $domain,
            $date,
            self::nullableString($row['language'] ?? null),
            preg_match('/^[A-Z]{2}$/', $country) === 1 ? $country : null,
            null,
            $image,
            null,
            null,
            'GDELT',
        );
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = self::stringValue($value);

        return $value !== '' ? $value : null;
    }

    private static function stringValue(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}
