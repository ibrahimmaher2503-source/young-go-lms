<?php

declare(strict_types=1);

namespace App\Domain\Support\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\GuardianAccess;
use App\Domain\Identity\Models\ViewAsSession;
use App\Domain\Support\ViewAsEligibility;
use App\Domain\Support\ViewAsRefusedException;
use App\Domain\Support\ViewAsState;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;

/**
 * Starts a read-only, time-limited, audited "view as learner" session (ED-027, YG-GAP-OPS-002). The staff member stays
 * signed in as themselves: no credentials are used, issued or swapped. Every refusal is audited too.
 */
final class StartViewAs
{
    public function __construct(private readonly ViewAsEligibility $eligibility, private readonly AuditLogger $audit, private readonly StopViewAs $stop) {}

    /** @throws ViewAsRefusedException|ValidationException */
    public function __invoke(User $actor, User $learner, string $reason): ViewAsSession
    {
        $reason = trim($reason);
        $min = config()->integer('support.view_as.reason_min');
        if (mb_strlen($reason) < $min || mb_strlen($reason) > config()->integer('support.view_as.reason_max')) {
            throw ValidationException::withMessages(['reason' => __('support.reason_invalid', ['min' => $min])]);
        }

        // A session of this person that ran out without being closed does not count as "already active".
        ViewAsSession::query()->open()->where('staff_id', $actor->getKey())->where('expires_at', '<=', now())->get()
            ->each(fn (ViewAsSession $stale) => ($this->stop)($stale, ViewAsSession::END_EXPIRED));

        $refusal = $this->eligibility->refusal($actor, $learner);
        if ($refusal !== null) {
            $this->audit->record('support.view_as_refused', $learner, (int) $actor->getKey(), null, null, ['refusal' => $refusal->value]);

            throw new ViewAsRefusedException($refusal);
        }

        $minor = GuardianAccess::isMinor($learner);
        $minutes = config()->integer($minor ? 'support.view_as.minor_ttl_minutes' : 'support.view_as.ttl_minutes');

        $session = DB::transaction(function () use ($actor, $learner, $reason, $minutes, $minor): ViewAsSession {
            $session = ViewAsSession::query()->create([
                'staff_id' => $actor->getKey(),
                'learner_id' => $learner->getKey(),
                'reason' => $reason,
                'started_at' => now(),
                'expires_at' => now()->addMinutes($minutes),
            ]);

            $this->audit->record('support.view_as_started', $session, (int) $actor->getKey(), null, null, [
                'learner_id' => $learner->getKey(), 'reason' => $reason, 'minor' => $minor, 'expires_at' => $session->expires_at->toIso8601String(),
            ]);

            return $session;
        });

        Session::put(ViewAsState::SESSION_KEY, (int) $session->getKey());

        return $session;
    }
}
