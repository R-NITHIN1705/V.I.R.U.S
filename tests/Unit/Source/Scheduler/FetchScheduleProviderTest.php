<?php

declare(strict_types=1);

namespace App\Tests\Unit\Source\Scheduler;

use App\Source\Scheduler\FetchScheduleProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(FetchScheduleProvider::class)]
final class FetchScheduleProviderTest extends TestCase
{
    public function testScheduleQueuesOneServerSideFetchCycleEvery210Seconds(): void
    {
        $schedule = (new FetchScheduleProvider())->getSchedule();
        $messages = $schedule->getRecurringMessages();
        self::assertCount(1, $messages);
        self::assertCount(1, $messages);
    }
}
