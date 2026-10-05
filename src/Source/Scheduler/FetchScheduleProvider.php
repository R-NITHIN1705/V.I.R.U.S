<?php

declare(strict_types=1);

namespace App\Source\Scheduler;

use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('fetch')]
final class FetchScheduleProvider implements ScheduleProviderInterface
{
    public function __construct(
    ) {
    }

    public function getSchedule(): Schedule
    {
        return (new Schedule())->add(RecurringMessage::every('210 seconds', new \App\Source\Message\FetchAllEnabledSourcesMessage()));
    }
}
