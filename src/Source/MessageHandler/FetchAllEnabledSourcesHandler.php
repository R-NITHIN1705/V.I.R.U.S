<?php

declare(strict_types=1);

namespace App\Source\MessageHandler;

use App\Source\Message\FetchAllEnabledSourcesMessage;
use App\Source\Message\FetchSourceMessage;
use App\Source\Repository\SourceRepositoryInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class FetchAllEnabledSourcesHandler
{
    public function __construct(private SourceRepositoryInterface $sources, private MessageBusInterface $bus, private LoggerInterface $logger) {}

    public function __invoke(FetchAllEnabledSourcesMessage $message): void
    {
        foreach ($this->sources->findEnabled() as $source) {
            if (($id = $source->getId()) === null) continue;
            try {
                $this->bus->dispatch(new FetchSourceMessage($id));
            } catch (\Throwable $e) {
                $this->logger->error('Unable to queue feed update for {source}: {error}', ['source' => $source->getName(), 'error' => $e->getMessage()]);
            }
        }
    }
}
