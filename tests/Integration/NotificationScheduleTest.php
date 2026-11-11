<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Services\NotificationSchedule;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

class NotificationScheduleTest extends TestCase
{
    public function testNextRunAccountsForCurrentAndScheduledTimes(): void
    {
        $zone = new DateTimeZone('America/Guayaquil');
        $cases = [
            ['2026-11-07 17:00:00', null, '2026-11-07 18:00:00'],
            ['2026-11-07 18:00:00', null, '2026-11-08 18:00:00'],
            ['2026-11-07 17:00:00', '2026-11-09 18:00:00', '2026-11-09 18:00:00'],
            ['2026-11-07 17:00:00', '2026-11-09 18:01:00', '2026-11-10 18:00:00'],
            ['2026-11-07 19:00:00', '2026-11-06 09:00:00', '2026-11-08 18:00:00'],
        ];

        foreach ($cases as [$now, $scheduled, $expected]) {
            $actual = NotificationSchedule::nextDailyRun(new DateTimeImmutable($now, $zone), $scheduled);
            self::assertSame($expected, $actual->format('Y-m-d H:i:s'));
            self::assertSame('America/Guayaquil', $actual->getTimezone()->getName());
        }
    }
}
