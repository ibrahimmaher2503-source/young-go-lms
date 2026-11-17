<?php

declare(strict_types=1);

namespace App\Domain\Live\Actions;

use App\Domain\Live\LiveSessionNotices;
use App\Domain\Live\Notifications\LiveSessionNotice;
use App\Models\LiveSession;
use Illuminate\Support\Facades\Config;

/**
 * Sends the reminders that are due. For every configured offset (minutes before the start) a session gets one reminder
 * once it is within that offset. A session scheduled later than its offset point (for example created 30 minutes before
 * a 24 hour reminder) skips that offset, so nobody is told "tomorrow" about something that starts soon.
 * Safe to run as often as wanted: the dedupe key makes each reminder go out once per person.
 */
final class SendLiveReminders
{
    public function __construct(private readonly LiveSessionNotices $notices) {}

    /** @return int notifications sent in this run */
    public function __invoke(): int
    {
        /** @var list<int> $offsets */
        $offsets = array_values(array_unique(array_map('intval', (array) Config::get('live.reminder_offsets_minutes', []))));
        rsort($offsets);
        $sent = 0;

        foreach ($offsets as $offset) {
            if ($offset <= 0) {
                continue;
            }
            $sessions = LiveSession::query()->whereIn('status', [LiveSession::SCHEDULED, LiveSession::OPEN])->whereNull('archived_at')
                ->where('starts_at', '>', now())->where('starts_at', '<=', now()->addMinutes($offset))->with(['course', 'cohort'])->get();

            foreach ($sessions as $session) {
                if ($session->created_at !== null && $session->created_at->gt($session->starts_at->copy()->subMinutes($offset))) {
                    continue;
                }
                $sent += $this->notices->send($session, LiveSessionNotice::REMINDER, (string) $offset.'m:'.$session->starts_at->getTimestamp());
            }
        }

        return $sent;
    }
}
