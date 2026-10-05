<?php

declare(strict_types=1);

namespace App\Shared\AI\Platform;

use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;

/** Routes application AI work to Groq when configured, otherwise preserves OpenRouter. */
final readonly class ProviderRoutingPlatform implements PlatformInterface
{
    public function __construct(
        private PlatformInterface $openRouterPlatform,
        private PlatformInterface $groqPlatform,
        private string $groqModel,
        private bool $groqEnabled,
    ) {
    }

    public function invoke(string $model, object|array|string $input, array $options = []): DeferredResult
    {
        if ($this->groqEnabled) {
            return $this->groqPlatform->invoke($this->groqModel, $input, $options);
        }

        return $this->openRouterPlatform->invoke($model, $input, $options);
    }

    public function getModelCatalog(): ModelCatalogInterface
    {
        return $this->groqEnabled
            ? $this->groqPlatform->getModelCatalog()
            : $this->openRouterPlatform->getModelCatalog();
    }
}
