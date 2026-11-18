<?php

declare(strict_types=1);

namespace App\Domain\Live;

use App\Models\LiveSession;
use Carbon\CarbonInterface;

/**
 * Whether a session can be joined right now. Cancelled and finished sessions never can; an opened or scheduled
 * session can only be joined from the start of its configured window until it ends. Time comes from the server.
 */
final class JoinWindow
{
    public const string UPCOMING = 'upcoming';

    public const string JOINABLE = 'joinable';

    public const string ENDED = 'ended';

    public const string CANCELLED = 'cancelled';

    public static function state(LiveSession $session, ?CarbonInterface $now = null): string
    {
        $now ??= now();
        if ($session->status === LiveSession::CANCELLED) {
            return self::CANCELLED;
        }
        if ($session->status === LiveSession::COMPLETED || $now->gte($session->ends_at)) {
            return self::ENDED;
        }
        if ($now->gte(self::opensAt($session))) {
            return self::JOINABLE;
        }

        return self::UPCOMING;
    }

    /** When the join button appears for a scheduled session. */
    public static function opensAt(LiveSession $session): CarbonInterface
    {
        return $session->starts_at->copy()->subMinutes($session->join_window_minutes);
    }
}
