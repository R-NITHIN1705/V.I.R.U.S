<?php

declare(strict_types=1);

namespace App\Source\Controller;

use App\Article\Entity\Article;
use App\Notification\Entity\NotificationLog;
use App\Source\Entity\Source;
use App\Source\Repository\SourceRepositoryInterface;
use App\User\Entity\User;
use App\User\Entity\UserArticleBookmark;
use App\User\Entity\UserArticleRead;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class DeleteSourceController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly SourceRepositoryInterface $sourceRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    #[Route('/sources/{id}/delete', name: 'app_sources_delete', methods: ['POST'])]
    public function __invoke(Request $request, int $id): Response
    {
        $user = $this->controller->getUser();

        if (! $user instanceof User) {
            return new RedirectResponse(
                $this->urlGenerator->generate('app_login')
            );
        }

        $isHtmx = $request->headers->has('HX-Request');

        $token = $request->headers->get('X-CSRF-Token')
            ?? $request->request->getString('_token');

        if (! $this->controller->isCsrfTokenValid('delete_source', $token)) {
            if ($isHtmx) {
                return new Response(
                    'Invalid CSRF token.',
                    Response::HTTP_FORBIDDEN
                );
            }

            $this->controller->addFlash(
                'error',
                'Invalid CSRF token.'
            );

            return new RedirectResponse(
                $this->urlGenerator->generate('app_sources')
            );
        }

        $source = $this->sourceRepository->findById($id);

        if (! $source instanceof Source) {
            if ($isHtmx) {
                return new Response(
                    'Source not found.',
                    Response::HTTP_NOT_FOUND
                );
            }

            $this->controller->addFlash(
                'error',
                'Source not found.'
            );

            return new RedirectResponse(
                $this->urlGenerator->generate('app_sources')
            );
        }

        /*
         * Disable the source immediately.
         *
         * This prevents new Fetch All operations from selecting
         * this source after this point.
         */
        $source->setEnabled(false);

        /*
         * Find all articles belonging to this source.
         */
        $articles = $this->entityManager
            ->getRepository(Article::class)
            ->createQueryBuilder('a')
            ->where('a.source = :source')
            ->setParameter('source', $source)
            ->getQuery()
            ->getResult();

        /*
         * Remove dependent records first because their
         * article_id columns are NOT NULL foreign keys.
         */
        foreach ($articles as $article) {
            $reads = $this->entityManager
                ->getRepository(UserArticleRead::class)
                ->findBy(['article' => $article]);

            foreach ($reads as $read) {
                $this->entityManager->remove($read);
            }

            $bookmarks = $this->entityManager
                ->getRepository(UserArticleBookmark::class)
                ->findBy(['article' => $article]);

            foreach ($bookmarks as $bookmark) {
                $this->entityManager->remove($bookmark);
            }

            $notifications = $this->entityManager
                ->getRepository(NotificationLog::class)
                ->findBy(['article' => $article]);

            foreach ($notifications as $notification) {
                $this->entityManager->remove($notification);
            }

            /*
             * Remove through Doctrine so ArticleIndexListener
             * can remove the article from the search index.
             */
            $this->entityManager->remove($article);
        }

        /*
         * Finally remove the source itself.
         */
        $this->entityManager->remove($source);

        /*
         * One transaction/flush for the whole operation.
         */
        $this->entityManager->flush();

        if ($isHtmx) {
            return new Response('');
        }

        $this->controller->addFlash(
            'success',
            'Source deleted.'
        );

        return new RedirectResponse(
            $this->urlGenerator->generate('app_sources')
        );
    }
}
