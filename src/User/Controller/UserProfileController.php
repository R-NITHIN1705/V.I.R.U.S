<?php

declare(strict_types=1);

namespace App\User\Controller;

use App\Topic\Service\TopicHubCatalog;
use App\User\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class UserProfileController
{
    public function __construct(
        private readonly ControllerHelper $controller,
        private readonly TopicHubCatalog $topicHubs,
    ) {
    }

    #[Route('/profile', name: 'app_profile', methods: ['GET'])]
    public function __invoke(): Response
    {
        $user = $this->controller->getUser();
        if (! $user instanceof User) {
            return $this->controller->redirectToRoute('app_login');
        }

        return $this->controller->render('user/profile.html.twig', [
            'profile' => $user,
            'followedTopics' => $user->getFollowedTopics(),
            'topicHubs' => $this->topicHubs->all(),
        ]);
    }
}
