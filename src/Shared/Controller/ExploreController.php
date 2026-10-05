<?php

declare(strict_types=1);

namespace App\Shared\Controller;

use App\Shared\Repository\CategoryRepositoryInterface;
use App\Source\Repository\SourceRepositoryInterface;
use App\Topic\Service\TopicHubCatalog;
use App\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ExploreController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly SourceRepositoryInterface $sourceRepository,
        private readonly TopicHubCatalog $topicHubs,
    ) {
    }

    #[Route('/explore', name: 'app_explore')]
    public function __invoke(): Response
    {
        $topicHubs = $this->topicHubs->all();
        $user = $this->controller->getUser();
        $followedTopics = $user instanceof User ? $user->getFollowedTopics() : [];
        $hubCategorySlugs = [];
        foreach ($topicHubs as $hub) {
            $hubCategorySlugs[] = $hub->slug;
            if ($hub->articleCategorySlug !== null) {
                $hubCategorySlugs[] = $hub->articleCategorySlug;
            }
        }

        $otherCategories = array_values(array_filter(
            $this->categoryRepository->findAllOrderedByWeight(),
            static fn ($category): bool => ! in_array($category->getSlug(), $hubCategorySlugs, true),
        ));

        return $this->controller->render('explore/index.html.twig', [
            'topicHubs' => $topicHubs,
            'followedTopics' => $followedTopics,
            'categories' => $otherCategories,
            'sources' => $this->sourceRepository->findEnabled(),
        ]);
    }
}
