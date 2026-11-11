<?php

namespace App\Services;

use DateTimeImmutable;

class NotificationSchedule
{
    public static function nextDailyRun(DateTimeImmutable $now, ?string $scheduled): DateTimeImmutable
    {
        $due = $scheduled ? new DateTimeImmutable($scheduled, $now->getTimezone()) : $now;
        $earliest = $due > $now ? $due : $now;
        $next = $earliest->setTime(18, 0);

        return ($next < $earliest || $next <= $now) ? $next->modify('+1 day') : $next;
    }
}
