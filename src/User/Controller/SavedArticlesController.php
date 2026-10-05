<?php

declare(strict_types=1);

namespace App\User\Controller;

use App\User\Entity\User;
use App\User\Repository\UserArticleBookmarkRepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SavedArticlesController
{
    public function __construct(private readonly ControllerHelper $controller, private readonly UserArticleBookmarkRepositoryInterface $bookmarks) {}

    #[Route('/saved', name: 'app_saved', methods: ['GET'])]
    public function __invoke(): Response
    {
        $user = $this->controller->getUser();
        if (!$user instanceof User) return $this->controller->redirectToRoute('app_login');
        return $this->controller->render('user/saved.html.twig', ['bookmarks' => $this->bookmarks->findByUser($user)]);
    }
}
