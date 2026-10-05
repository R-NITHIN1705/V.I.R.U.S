<?php

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

return static function (ContainerConfigurator $container): void {
    $container->extension('ai', [
        'platform' => [
            'openrouter' => [
                'api_key' => '%env(OPENROUTER_API_KEY)%',
            ],
            'generic' => [
                'groq' => [
                    'base_url' => '%env(GROQ_BASE_URL)%',
                    'api_key' => '%env(default::GROQ_API_KEY)%',
                    'model_catalog' => 'ai.platform.model_catalog.groq',
                    'supports_completions' => true,
                    'supports_embeddings' => false,
                    'completions_path' => '/chat/completions',
                ],
            ],
            'failover' => [
                'default' => [
                    'platforms' => [
                        'ai.platform.openrouter',
                    ],
                    'rate_limiter' => 'limiter.ai_failover',
                ],
            ],
        ],
    ]);

    // Rate limiter for AI failover platform
    $container->extension('framework', [
        'rate_limiter' => [
            'ai_failover' => [
                'policy' => 'sliding_window',
                'limit' => 20,
                'interval' => '1 minute',
            ],
        ],
    ]);
};
