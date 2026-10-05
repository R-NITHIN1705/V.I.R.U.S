<?php

declare(strict_types=1);

namespace App\Topic\ValueObject;

/** @phpstan-type HubSubtopic array{slug: string, title: string} */
/** @phpstan-type HubSection array{key: string, title: string, kind: string} */
final readonly class TopicHub
{
    /**
     * @param list<array{slug: string, title: string}> $subtopics
     * @param list<array<string, mixed>> $sections
     * @param list<string> $providerCapabilities
     */
    public function __construct(
        public string $slug,
        public string $title,
        public string $description,
        public ?string $heroImage,
        public ?string $heroImageAlt,
        public ?string $heroImageProvenance,
        public string $accentColor,
        public array $subtopics,
        public array $sections,
        public array $providerCapabilities,
        public ?string $articleCategorySlug = null,
        public string $articleEmptyMessage = 'No stories are available for this topic yet. Connect a relevant publisher source to populate this feed.',
        public string $icon = 'globe',
    ) {
    }

    public function hasSubtopic(string $slug): bool
    {
        foreach ($this->subtopics as $subtopic) {
            if ($subtopic['slug'] === $slug) {
                return true;
            }
        }

        return false;
    }

    public function subtopicTitle(string $slug): ?string
    {
        foreach ($this->subtopics as $subtopic) {
            if ($subtopic['slug'] === $slug) {
                return $subtopic['title'];
            }
        }

        return null;
    }
}
