<?php

declare(strict_types=1);

namespace App\User\Controller;

use App\User\Entity\AuthToken;
use App\User\Service\AuthTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class VerifyEmailController
{
    public function __construct(private readonly ControllerHelper $controller, private readonly EntityManagerInterface $em, private readonly AuthTokenService $tokens) {}

    #[Route('/verify-email', name: 'app_verify_email', methods: ['GET'])]
    public function __invoke(Request $request): Response
    {
        $authToken = $this->tokens->consume($request->query->getString('token'), 'verify');
        if (!$authToken instanceof AuthToken) {
            $this->controller->addFlash('error', 'This verification link is invalid or expired.');
        } else {
            $authToken->getUser()->setVerified(true);
            $this->em->flush();
            $this->controller->addFlash('success', 'Email verified. You can sign in now.');
        }
        return $this->controller->redirectToRoute('app_login');
    }
}
