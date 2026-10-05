<?php

declare(strict_types=1);

namespace App\Article\Controller;

use App\Article\Entity\Article;
use App\Article\Repository\ArticleRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use App\User\Entity\User;
use App\User\Repository\UserArticleBookmarkRepositoryInterface;

final class StoryController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly ArticleRepositoryInterface $articleRepository,
        private readonly UserArticleBookmarkRepositoryInterface $bookmarks,
    ) {
    }

    #[Route('/stories/{id}', name: 'app_story', methods: ['GET'])]
    public function __invoke(int $id): Response
    {
        $article = $this->articleRepository->findById($id);
        if (! $article instanceof Article) {
            throw new NotFoundHttpException('Story not found.');
        }

        $user = $this->controller->getUser();
        $saved = $user instanceof User && $this->bookmarks->findByUserAndArticle($user, $article) !== null;
        return $this->controller->render('story/show.html.twig', ['article' => $article, 'isBookmarked' => $saved]);
    }
}
