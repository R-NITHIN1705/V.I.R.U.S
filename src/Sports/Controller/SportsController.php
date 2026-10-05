<?php

declare(strict_types=1);

namespace App\Sports\Controller;

use App\Article\Repository\ArticleRepositoryInterface;
use App\Sports\Service\SportsService;
use App\Sports\Service\CricketDataService;
use App\Shared\Repository\CategoryRepositoryInterface;
use App\Topic\Service\TopicHubCatalog;
use App\Topic\Service\TmdbTrendingService;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class SportsController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly ArticleRepositoryInterface $articles,
        private readonly CategoryRepositoryInterface $categories,
        private readonly SportsService $sports,
        private readonly CricketDataService $cricket,
        private readonly TmdbTrendingService $tmdb,
        private readonly TopicHubCatalog $topicHubs,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/explore/{slug}', name: 'app_topic_hub', methods: ['GET'])]
    public function __invoke(string $slug, Request $request): Response
    {
        $hub = $this->topicHubs->find($slug);
        if ($hub === null) {
            throw new NotFoundHttpException('Topic hub not found.');
        }

        $requestedSubtopic = $request->query->getString('subtopic');
        $selectedSubtopic = $requestedSubtopic !== '' && $hub->hasSubtopic($requestedSubtopic)
            ? $requestedSubtopic
            : null;
        $feed = $hub->slug === 'sports' && ($selectedSubtopic === null || $selectedSubtopic === 'football') ? $this->sports->getFeed() : null;
        $cricketFeed = $hub->slug === 'sports' && $selectedSubtopic === 'cricket' ? $this->cricket->getFeed() : null;
        $movieFeed = $hub->slug === 'movies' ? $this->tmdb->getTrendingMovies() : null;
        $articleCategorySlug = $hub->articleCategorySlug;
        $category = $articleCategorySlug !== null ? $this->categories->findBySlug($articleCategorySlug) : null;

        return $this->controller->render('components/topic_hub/page.html.twig', [
            'hub' => $hub,
            'selectedSubtopic' => $selectedSubtopic,
            'feed' => $feed,
            'cricketFeed' => $cricketFeed,
            'movieFeed' => $movieFeed,
            'articles' => $category && $articleCategorySlug !== null ? $this->articles->findPaginated($articleCategorySlug, null, 1, 12) : [],
            // The current product has no dedicated trending/deep-dive data source.
            'trendingArticles' => [],
            'deepDiveArticles' => [],
        ]);
    }

    /** Preserve the old Sports URL while sending visitors to its hub route. */
    #[Route('/sports', name: 'app_sports', methods: ['GET'])]
    public function legacy(): RedirectResponse
    {
        return new RedirectResponse($this->urlGenerator->generate('app_topic_hub', ['slug' => 'sports']), Response::HTTP_MOVED_PERMANENTLY);
    }
}
