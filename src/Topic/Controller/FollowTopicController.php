<?php

declare(strict_types=1);

namespace App\Topic\Controller;

use App\Topic\Service\TopicHubCatalog;
use App\User\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

final class FollowTopicController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly TopicHubCatalog $topicHubs,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/topics/{slug}/follow', name: 'app_topic_follow', methods: ['POST'])]
    public function __invoke(string $slug, Request $request): Response
    {
        $user = $this->controller->getUser();
        if (! $user instanceof User) {
            return $this->controller->redirectToRoute('app_login');
        }

        $hub = $this->topicHubs->find($slug);
        if ($hub === null) {
            throw new NotFoundHttpException('Topic hub not found.');
        }

        if (! $this->controller->isCsrfTokenValid('topic-follow-' . $hub->slug, $request->request->getString('_token'))) {
            $this->controller->addFlash('error', 'Your topic preference could not be saved. Please retry.');

            return $this->controller->redirectToRoute('app_explore');
        }

        $action = $request->request->getString('action');
        if ($action === 'follow') {
            $user->followTopic($hub->slug);
            $this->controller->addFlash('success', $hub->title . ' added to Your topics.');
        } elseif ($action === 'unfollow') {
            $user->unfollowTopic($hub->slug);
            $this->controller->addFlash('success', $hub->title . ' removed from Your topics.');
        } else {
            return new Response('Invalid topic action.', Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->controller->redirectToRoute(
            'app_explore',
            ['_fragment' => 'your-topics'],
            Response::HTTP_SEE_OTHER,
        );
    }
}
