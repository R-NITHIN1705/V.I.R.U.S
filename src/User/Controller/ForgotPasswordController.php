<?php

declare(strict_types=1);

namespace App\User\Controller;

use App\User\Entity\User;
use App\User\Service\AuthTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class ForgotPasswordController
{
    public function __construct(private readonly ControllerHelper $controller, private readonly EntityManagerInterface $em, private readonly AuthTokenService $tokens) {}

    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $error = null;
        $email = '';
        if ($request->isMethod('POST')) {
            $email = mb_strtolower(trim($request->request->getString('email')));
            if (!$this->controller->isCsrfTokenValid('forgot_password', $request->request->getString('_csrf_token'))) $error = 'Your form expired. Please try again.';
            elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $error = 'Enter a valid email address.';
            else {
                $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
                if ($user instanceof User && $user->isVerified()) $this->tokens->send($user, 'reset');
                $this->controller->addFlash('success', 'If an account matches that email, a password reset link is on its way.');
                return $this->controller->redirectToRoute('app_login');
            }
        }
        return $this->controller->render('security/forgot_password.html.twig', ['email' => $email, 'error' => $error]);
    }
}
