<?php

declare(strict_types=1);

namespace App\Domain\Live;

use App\Models\LiveSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use DateTimeZone;

/** Shows a session time in the viewer timezone (their profile, else the one the scheduler used). Storage stays UTC. */
final class LiveSessionTime
{
    public static function zoneFor(LiveSession $session, ?User $viewer): string
    {
        $zone = $viewer?->getAttribute('timezone');

        return is_string($zone) && $zone !== '' && in_array($zone, DateTimeZone::listIdentifiers(), true) ? $zone : $session->timezone;
    }

    public static function startsAt(LiveSession $session, ?User $viewer): CarbonImmutable
    {
        return CarbonImmutable::instance($session->starts_at)->setTimezone(self::zoneFor($session, $viewer));
    }

    public static function endsAt(LiveSession $session, ?User $viewer): CarbonImmutable
    {
        return CarbonImmutable::instance($session->ends_at)->setTimezone(self::zoneFor($session, $viewer));
    }

    public static function label(LiveSession $session, ?User $viewer): string
    {
        $start = self::startsAt($session, $viewer);

        return $start->isoFormat('LLL').' – '.self::endsAt($session, $viewer)->isoFormat('LT').' ('.$start->getTimezone()->getName().')';
    }
}
