<?php

declare(strict_types=1);

namespace App\Article\Twig;

use App\Article\Entity\Article;
use App\Article\ValueObject\ImageType;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class ArticleImageExtension extends AbstractExtension
{
    private const string PLACEHOLDER = '/images/article-placeholder.svg';

    /**
     * @return list<TwigFunction>
     */
    public function getFunctions(): array
    {
        return [new TwigFunction('article_image', $this->resolve(...))];
    }

    /**
     * @return array{
     *     url: string,
     *     alt: string,
     *     type: string,
     *     badge: string,
     *     fallbackUrl: string,
     *     fallbackAlt: string,
     *     fallbackType: string,
     *     fallbackBadge: string,
     *     placeholderUrl: string,
     *     placeholderAlt: string,
     *     placeholderType: string,
     *     placeholderBadge: string
     * }
     */
    public function resolve(Article $article): array
    {
        $topic = trim($article->getCategory()?->getName() ?? 'World news');
        $slug = strtolower($article->getCategory()?->getSlug() ?? 'world');
        $assetSlug = match (true) {
            $slug === 'politics' || str_starts_with($slug, 'politics-') => 'politics',
            $slug === 'business' || str_starts_with($slug, 'business-') => 'business',
            $slug === 'science' || str_starts_with($slug, 'science-') => 'science',
            $slug === 'sports' || str_starts_with($slug, 'sports-') => 'sports',
            in_array($slug, ['tech', 'technology'], true) || str_starts_with($slug, 'tech-') || str_starts_with($slug, 'technology-') => 'tech',
            default => 'world',
        };
        $generatedUrl = '/images/editorial/' . $assetSlug . '.jpg';
        $generatedAlt = 'Editorial illustration representing ' . $topic;
        $generatedBadge = 'AI-generated illustration';
        $articleImageUrl = $article->getImageUrl();
        $type = $article->getImageType();

        if ($articleImageUrl === null) {
            $type = ImageType::GeneratedIllustration;
            $articleImageUrl = $generatedUrl;
            $alt = $generatedAlt;
            $badge = $generatedBadge;
        } else {
            $alt = trim((string) $article->getImageAlt()) ?: $article->getTitle();
            $badge = $this->badge($type, $article->getImageAttribution());
        }

        $fallbackUrl = $type === ImageType::GeneratedIllustration ? self::PLACEHOLDER : $generatedUrl;
        $fallbackType = $type === ImageType::GeneratedIllustration ? ImageType::Placeholder : ImageType::GeneratedIllustration;
        $fallbackAlt = $fallbackType === ImageType::Placeholder ? 'Editorial image unavailable' : $generatedAlt;
        $fallbackBadge = $this->badge($fallbackType, null);

        return [
            'url' => $articleImageUrl,
            'alt' => $alt,
            'type' => $type->value,
            'badge' => $badge,
            'fallbackUrl' => $fallbackUrl,
            'fallbackAlt' => $fallbackAlt,
            'fallbackType' => $fallbackType->value,
            'fallbackBadge' => $fallbackBadge,
            'placeholderUrl' => self::PLACEHOLDER,
            'placeholderAlt' => 'Editorial image unavailable',
            'placeholderType' => ImageType::Placeholder->value,
            'placeholderBadge' => $this->badge(ImageType::Placeholder, null),
        ];
    }

    private function badge(ImageType $type, ?string $attribution): string
    {
        $attribution = trim((string) $attribution);

        return match ($type) {
            ImageType::Source => 'Photo: ' . ($attribution !== '' ? $attribution : 'Publisher'),
            ImageType::Provider => 'Provider: ' . ($attribution !== '' ? $attribution : 'Image provider'),
            ImageType::LicensedStock => 'Licensed stock: ' . ($attribution !== '' ? $attribution : 'Provider'),
            ImageType::GeneratedIllustration => 'AI-generated illustration',
            ImageType::Placeholder => 'Image unavailable',
        };
    }
}
