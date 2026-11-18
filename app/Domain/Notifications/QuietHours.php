<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use Carbon\CarbonInterface;
use DateTimeZone;
use Illuminate\Support\Facades\Config;

/** The window (config phone_channels.quiet_hours) in which no phone message is sent, read in the recipient's timezone. It may wrap midnight. */
final class QuietHours
{
    public static function contains(CarbonInterface $now, ?string $timezone): bool
    {
        $window = Config::array('phone_channels.quiet_hours');
        $start = self::minutes($window['start'] ?? null);
        $end = self::minutes($window['end'] ?? null);
        if ($start === null || $end === null || $start === $end) {
            return false;
        }

        $zone = is_string($timezone) && in_array($timezone, DateTimeZone::listIdentifiers(), true) ? $timezone : 'UTC';
        $local = $now->copy()->setTimezone($zone);
        $minute = $local->hour * 60 + $local->minute;

        return $start < $end ? $minute >= $start && $minute < $end : $minute >= $start || $minute < $end;
    }

    private static function minutes(mixed $time): ?int
    {
        if (! is_string($time) || preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $m) !== 1) {
            return null;
        }

        return (int) $m[1] * 60 + (int) $m[2];
    }
}
