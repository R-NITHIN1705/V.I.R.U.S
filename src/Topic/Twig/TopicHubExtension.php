<?php

declare(strict_types=1);

namespace App\Topic\Twig;

use App\Topic\Service\TopicHubCatalog;
use Twig\TwigFunction;
use Twig\Extension\AbstractExtension;

final class TopicHubExtension extends AbstractExtension
{
    public function __construct(private readonly TopicHubCatalog $catalog)
    {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('topic_hub_route', $this->routeForCategory(...)),
            new TwigFunction('topic_hub_title', $this->titleForCategory(...)),
        ];
    }

    /** @return array{slug: string, subtopic?: string}|null */
    public function routeForCategory(?string $categorySlug): ?array
    {
        return $categorySlug !== null ? $this->catalog->routeForCategorySlug($categorySlug) : null;
    }

    public function titleForCategory(?string $categorySlug): ?string
    {
        $route = $this->routeForCategory($categorySlug);

        return $route !== null ? $this->catalog->find($route['slug'])?->title : null;
    }
}
