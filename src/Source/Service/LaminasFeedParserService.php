<?php

declare(strict_types=1);

namespace App\Source\Service;

use Laminas\Feed\Reader\Reader;

final readonly class LaminasFeedParserService implements FeedParserServiceInterface
{
    public function parse(string $feedContent): FeedItemCollection
    {
        $feed = Reader::importString($feedContent);
        $items = [];

        foreach ($feed as $entry) {
            $title = $entry->getTitle();
            $url = $entry->getLink();

            /** @phpstan-ignore identical.alwaysFalse, voku.Identical */
            $titleEmpty = $title === null || $title === '';
            /** @phpstan-ignore identical.alwaysFalse, voku.Identical */
            $urlEmpty = $url === null || $url === '';
            if ($titleEmpty) {
                continue;
            }
            if ($urlEmpty) {
                continue;
            }

            $contentRaw = $entry->getContent() ?? '';
            if ($contentRaw === '') {
                $contentRaw = $entry->getDescription() ?? '';
            }

            $contentText = $contentRaw !== '' ? $this->stripHtml($contentRaw) : null;
            $contentRawOrNull = $contentRaw !== '' ? $contentRaw : null;
            $imageUrl = $this->extractImageUrl($entry, $contentRaw);

            $publishedAt = null;
            $dateModified = $entry->getDateModified();
            if ($dateModified instanceof \DateTimeInterface) {
                $publishedAt = \DateTimeImmutable::createFromInterface($dateModified);
            }

            $items[] = new FeedItem(
                title: $title,
                url: $url,
                contentRaw: $contentRawOrNull,
                contentText: $contentText,
                publishedAt: $publishedAt,
                imageUrl: $imageUrl,
            );
        }

        return new FeedItemCollection($items);
    }

    private function extractImageUrl(object $entry, string $content): ?string
    {
        if (method_exists($entry, 'getEnclosure')) {
            try {
                $enclosure = $entry->getEnclosure();
                if (is_object($enclosure)) {
                    $url = $enclosure->url ?? $enclosure->href ?? null;
                    if (is_string($url) && preg_match('~^https?://~i', $url)) return $url;
                }
            } catch (\Throwable) { /* Some feed formats expose no enclosure. */ }
        }
        if (preg_match('~<img\\b[^>]*?src=["\\\']([^"\\\']+)["\\\']~i', $content, $match) && preg_match('~^https?://~i', html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5))) return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5);
        return null;
    }

    private function stripHtml(string $html): string
    {
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }
}
