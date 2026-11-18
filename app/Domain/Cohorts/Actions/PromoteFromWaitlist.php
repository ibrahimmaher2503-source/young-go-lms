<?php

declare(strict_types=1);

namespace App\Domain\Cohorts\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Cohorts\Cohort;
use App\Domain\Cohorts\CohortAccess;
use App\Domain\Cohorts\CohortFullException;
use App\Domain\Cohorts\CohortMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Seats the learner who has waited longest (queue time, then id). Needs a free seat; nobody can be promoted out of order. */
final class PromoteFromWaitlist
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, Cohort $cohort): CohortMember
    {
        CohortAccess::authorize($actor, $cohort->course);

        return DB::transaction(function () use ($actor, $cohort): CohortMember {
            $locked = Cohort::query()->whereKey($cohort->getKey())->lockForUpdate()->firstOrFail();
            if (! $locked->enrollmentIsOpen()) {
                throw ValidationException::withMessages(['cohort' => __('cohorts.closed')]);
            }

            $next = CohortMember::query()->where('cohort_id', $locked->getKey())->where('status', CohortMember::WAITLISTED)->orderBy('queued_at')->orderBy('id')->first();
            if ($next === null) {
                throw ValidationException::withMessages(['cohort' => __('cohorts.waitlist_empty')]);
            }
            if ($locked->isFull()) {
                throw new CohortFullException;
            }

            $next->update(['status' => CohortMember::SEATED, 'seated_at' => now(), 'queued_at' => null]);
            if ($this->audit->record('cohort.promoted', $locked, (int) $actor->getKey(), null, ['learner_id' => $next->learner_id]) === null) {
                throw new RuntimeException('Unable to record cohort audit.');
            }

            return $next;
        });
    }
}
