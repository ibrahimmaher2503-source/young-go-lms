<?php

declare(strict_types=1);

namespace App\Domain\Cohorts\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Cohorts\Cohort;
use App\Domain\Cohorts\CohortAccess;
use App\Domain\Cohorts\CohortMember;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Takes a learner off a cohort (seat or waitlist). The row stays as history; a freed seat is offered to the waitlist by staff, never silently. */
final class RemoveFromCohort
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, CohortMember $member): void
    {
        $cohort = $member->cohort;
        CohortAccess::authorize($actor, $cohort->course);

        DB::transaction(function () use ($actor, $member, $cohort): void {
            Cohort::query()->whereKey($cohort->getKey())->lockForUpdate()->firstOrFail();
            $fresh = $member->refresh();
            if ($fresh->status === CohortMember::REMOVED) {
                return;
            }
            $was = $fresh->status;
            $fresh->update(['status' => CohortMember::REMOVED, 'removed_at' => now()]);
            if ($this->audit->record('cohort.member_removed', $cohort, (int) $actor->getKey(), ['status' => $was], ['learner_id' => $fresh->learner_id]) === null) {
                throw new RuntimeException('Unable to record cohort audit.');
            }
        });
    }
}
