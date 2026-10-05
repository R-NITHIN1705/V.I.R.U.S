<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    // https://symfony.com/doc/current/security.html
    $container->extension('security', [
        'password_hashers' => [
            'Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface' => [
                'algorithm' => 'auto',
            ],
        ],

        'providers' => [
            'app_user_provider' => [
                'entity' => [
                    'class' => 'App\User\Entity\User',
                    'property' => 'email',
                ],
            ],
        ],

        'firewalls' => [
            'dev' => [
                'pattern' => '^/(_profiler|_wdt|assets|build)/',
                'security' => false,
            ],

            'main' => [
                'lazy' => true,
                'provider' => 'app_user_provider',
                'user_checker' => App\User\Security\VerifiedUserChecker::class,
                'login_throttling' => ['max_attempts' => 5],

                'form_login' => [
                    'login_path' => 'app_login',
                    'check_path' => 'app_login',
                    'username_parameter' => '_username',
                    'password_parameter' => '_password',
                    'enable_csrf' => true,
                ],

                'logout' => [
                    'path' => 'app_logout',
                    'enable_csrf' => true,
                ],
            ],
        ],

        'access_control' => [
            ['path' => '^/health$', 'roles' => 'PUBLIC_ACCESS'],
            ['path' => '^/(login|register|forgot-password|reset-password|verify-email)$',
                'roles' => 'PUBLIC_ACCESS',
            ],
            [
                'path' => '^/',
                'roles' => 'ROLE_USER',
            ],
        ],
    ]);

    if ($container->env() === 'test') {
        // Password hashers are resource-intensive by design.
        // In tests, reduce their cost to improve performance.
        $container->extension('security', [
            'password_hashers' => [
                'Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface' => [
                    'algorithm' => 'auto',
                    'cost' => 4,
                    'time_cost' => 3,
                    'memory_cost' => 10,
                ],
            ],
        ]);
    }
};
