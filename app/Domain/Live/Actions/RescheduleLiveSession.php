<?php

declare(strict_types=1);

namespace App\Domain\Live\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Live\LiveAccess;
use App\Domain\Live\MeetingProvider;
use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Moves a session that has not started: new time (and optionally link or provider). Finished or cancelled sessions stay as they were. */
final class RescheduleLiveSession
{
    public function __construct(private readonly AuditLogger $audit, private readonly MeetingProvider $meetings) {}

    /** @param array<string, mixed> $data starts_at, ends_at (local wall-clock), timezone, optionally provider and meeting_url */
    public function __invoke(User $actor, LiveSession $session, array $data): LiveSession
    {
        LiveAccess::authorize($actor, $session->course);
        // The title is not edited here; a placeholder satisfies the shared field rules.
        $fields = ScheduleLiveSession::validated(['title' => ['en' => '-'], 'provider' => $session->provider, ...$data]);
        [$startsAt, $endsAt] = ScheduleLiveSession::utcRange($fields['starts_at'], $fields['ends_at'], $fields['timezone']);
        $url = array_key_exists('meeting_url', $data) ? $this->meetings->validateUrl($fields['provider'], $fields['meeting_url'] ?? null) : $session->meeting_url;

        return DB::transaction(function () use ($actor, $session, $fields, $startsAt, $endsAt, $url): LiveSession {
            $locked = LiveSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status !== LiveSession::SCHEDULED) {
                throw ValidationException::withMessages(['status' => __('live.not_reschedulable')]);
            }
            $before = ['starts_at' => $locked->starts_at->toIso8601String(), 'ends_at' => $locked->ends_at->toIso8601String()];
            $locked->update(['starts_at' => $startsAt, 'ends_at' => $endsAt, 'timezone' => $fields['timezone'], 'provider' => $fields['provider'], 'meeting_url' => $url]);

            if ($this->audit->record('live_session.rescheduled', $locked, (int) $actor->getKey(), $before, ['starts_at' => $startsAt->toIso8601String(), 'ends_at' => $endsAt->toIso8601String()]) === null) {
                throw new RuntimeException('Unable to record live session audit.');
            }

            return $locked;
        });
    }
}
