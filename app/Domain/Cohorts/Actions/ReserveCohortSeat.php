<?php

declare(strict_types=1);

namespace App\Domain\Cohorts\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Cohorts\Cohort;
use App\Domain\Cohorts\CohortFullException;
use App\Domain\Cohorts\CohortMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Takes one seat in a cohort for a learner (seats held for unpaid orders count as taken, the learner's own hold does not). The cohort row is locked while seats are counted, so two people asking for
 * the last seat cannot both get it; the loser gets CohortFullException. Idempotent for a learner who already has the seat.
 * Callers (checkout, manual grants) decide who may ask; this action never grants course access by itself.
 */
final class ReserveCohortSeat
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(Cohort $cohort, User $learner, ?User $actor = null): CohortMember
    {
        return DB::transaction(function () use ($cohort, $learner, $actor): CohortMember {
            $locked = Cohort::query()->whereKey($cohort->getKey())->lockForUpdate()->firstOrFail();
            if (! $locked->enrollmentIsOpen()) {
                throw ValidationException::withMessages(['cohort' => __('cohorts.closed')]);
            }

            $member = CohortMember::query()->where('cohort_id', $locked->getKey())->where('learner_id', $learner->getKey())->first();
            if ($member?->status === CohortMember::SEATED) {
                return $member;
            }
            if ($locked->isFull($learner)) {
                throw new CohortFullException;
            }

            $member ??= new CohortMember(['cohort_id' => $locked->getKey(), 'learner_id' => $learner->getKey()]);
            $member->fill(['status' => CohortMember::SEATED, 'seated_at' => now(), 'queued_at' => null, 'removed_at' => null])->save();

            if ($this->audit->record('cohort.seat_reserved', $locked, $actor === null ? null : (int) $actor->getKey(), null, ['learner_id' => $learner->getKey()]) === null) {
                throw new RuntimeException('Unable to record cohort audit.');
            }

            return $member;
        });
    }
}
