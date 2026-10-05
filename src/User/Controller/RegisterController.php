<?php

declare(strict_types=1);

namespace App\User\Controller;

use App\User\Entity\User;
use App\User\Service\AuthTokenService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\ControllerHelper;

final class RegisterController
{
    public function __construct(private readonly ControllerHelper $controller, private readonly EntityManagerInterface $em, private readonly UserPasswordHasherInterface $hasher, private readonly AuthTokenService $tokens) {}

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function __invoke(Request $request): Response
    {
        $data = ['name' => '', 'email' => ''];
        $errors = [];
        if ($request->isMethod('POST')) {
            $data['name'] = trim($request->request->getString('name'));
            $data['email'] = mb_strtolower(trim($request->request->getString('email')));
            $password = $request->request->getString('password');
            $confirm = $request->request->getString('confirm_password');
            if (!$this->controller->isCsrfTokenValid('register', $request->request->getString('_csrf_token'))) $errors[] = 'Your form expired. Please try again.';
            if ($data['name'] === '' || mb_strlen($data['name']) > 120) $errors[] = 'Enter your name (up to 120 characters).';
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($data['email']) > 180) $errors[] = 'Enter a valid email address.';
            if (strlen($password) < 12 || strlen($password) > 4096) $errors[] = 'Use a password between 12 and 4096 characters.';
            if ($password !== $confirm) $errors[] = 'The passwords do not match.';
            if ($data['email'] !== '' && $this->em->getRepository(User::class)->findOneBy(['email' => $data['email']])) $errors[] = 'An account with that email already exists.';
            if (!$errors) {
                $user = new User($data['email'], '');
                $user->setFullName($data['name']);
                $user->setVerified(false);
                $user->setPassword($this->hasher->hashPassword($user, $password));
                $this->em->persist($user);
                $this->em->flush();
                $this->tokens->send($user, 'verify');
                $this->controller->addFlash('success', 'Account created. Check your email for a verification link before signing in.');
                return $this->controller->redirectToRoute('app_login');
            }
        }
        return $this->controller->render('security/register.html.twig', ['data' => $data, 'errors' => $errors]);
    }
}
