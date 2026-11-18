<?php

declare(strict_types=1);

namespace App\Domain\Cohorts\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Cohorts\Cohort;
use App\Domain\Cohorts\CohortMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Puts a learner at the end of a full cohort waitlist. A cohort with free seats takes a reservation instead; joining twice keeps the first place. */
final class JoinWaitlist
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(Cohort $cohort, User $learner): CohortMember
    {
        return DB::transaction(function () use ($cohort, $learner): CohortMember {
            $locked = Cohort::query()->whereKey($cohort->getKey())->lockForUpdate()->firstOrFail();
            if (! $locked->enrollmentIsOpen()) {
                throw ValidationException::withMessages(['cohort' => __('cohorts.closed')]);
            }

            $member = CohortMember::query()->where('cohort_id', $locked->getKey())->where('learner_id', $learner->getKey())->first();
            if ($member !== null && in_array($member->status, [CohortMember::SEATED, CohortMember::WAITLISTED], true)) {
                return $member; // already in: nothing changes and the place is kept
            }
            if (! $locked->isFull()) {
                throw ValidationException::withMessages(['cohort' => __('cohorts.not_full')]);
            }

            $member ??= new CohortMember(['cohort_id' => $locked->getKey(), 'learner_id' => $learner->getKey()]);
            $member->fill(['status' => CohortMember::WAITLISTED, 'queued_at' => now(), 'seated_at' => null, 'removed_at' => null])->save();

            if ($this->audit->record('cohort.waitlisted', $locked, null, null, ['learner_id' => $learner->getKey()]) === null) {
                throw new RuntimeException('Unable to record cohort audit.');
            }

            return $member;
        });
    }
}
