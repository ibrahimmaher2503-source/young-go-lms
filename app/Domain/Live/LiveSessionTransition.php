<?php

declare(strict_types=1);

namespace App\Domain\Live;

use App\Domain\Audit\AuditLogger;
use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** One audited, locked status change for a live session; the lifecycle Actions only name the allowed moves. */
final class LiveSessionTransition
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param list<string> $from statuses the session may be in */
    public function apply(User $actor, LiveSession $session, array $from, string $to, string $event): LiveSession
    {
        LiveAccess::authorize($actor, $session->course);

        return DB::transaction(function () use ($actor, $session, $from, $to, $event): LiveSession {
            $locked = LiveSession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
            if (! in_array($locked->status, $from, true)) {
                throw ValidationException::withMessages(['status' => __('live.transition_refused', ['from' => __('live.status.'.$locked->status), 'to' => __('live.status.'.$to)])]);
            }
            $before = $locked->status;
            $locked->update(['status' => $to]);
            if ($this->audit->record($event, $locked, (int) $actor->getKey(), ['status' => $before], ['status' => $to]) === null) {
                throw new RuntimeException('Unable to record live session audit.');
            }

            return $locked;
        });
    }
}
