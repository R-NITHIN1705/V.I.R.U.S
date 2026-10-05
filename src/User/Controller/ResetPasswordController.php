<?php

declare(strict_types=1);

namespace App\User\Controller;

use App\User\Entity\AuthToken;
use App\User\Service\AuthTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

final class ResetPasswordController
{
    public function __construct(private readonly ControllerHelper $controller, private readonly EntityManagerInterface $em, private readonly UserPasswordHasherInterface $hasher, private readonly AuthTokenService $tokens) {}

    #[Route('/reset-password', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $token = $request->query->getString('token');
        $errors = [];
        if ($request->isMethod('POST')) {
            $token = $request->request->getString('token');
            $password = $request->request->getString('password');
            $confirm = $request->request->getString('confirm_password');
            if (!$this->controller->isCsrfTokenValid('reset_password', $request->request->getString('_csrf_token'))) $errors[] = 'Your form expired. Please try again.';
            if (strlen($password) < 12 || strlen($password) > 4096) $errors[] = 'Use a password between 12 and 4096 characters.';
            if ($password !== $confirm) $errors[] = 'The passwords do not match.';
            if (!$errors) {
                $authToken = $this->tokens->consume($token, 'reset');
                if (!$authToken instanceof AuthToken) $errors[] = 'This link is invalid or expired. Request a new one.';
                else {
                    $user = $authToken->getUser();
                    $user->setPassword($this->hasher->hashPassword($user, $password));
                    $this->em->flush();
                    $this->controller->addFlash('success', 'Your password has been updated. You can sign in now.');
                    return $this->controller->redirectToRoute('app_login');
                }
            }
        }
        $response = $this->controller->render('security/reset_password.html.twig', ['token' => $token, 'errors' => $errors]);
        if ($token !== '') $response->headers->set('Referrer-Policy', 'no-referrer');
        return $response;
    }
}
