<?php

declare(strict_types=1);

namespace App\Domain\Cohorts\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Cohorts\Cohort;
use App\Domain\Cohorts\CohortFullException;
use App\Domain\Cohorts\CohortSeatHold;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Holds one seat for an unpaid order line (checkout). The cohort row is locked while seats and live holds are counted, so
 * two buyers cannot take the last seat; the loser gets CohortFullException. Idempotent per order line: asking again for the
 * same line renews its hold (a hold that expired is taken again only if there is still room). A learner who already has a
 * seat needs no hold and gets null. The hold lasts `commerce.seat_hold_minutes`. This action never seats anyone.
 */
final class HoldCohortSeat
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(Cohort $cohort, User $learner, int $orderItemId): ?CohortSeatHold
    {
        return DB::transaction(function () use ($cohort, $learner, $orderItemId): ?CohortSeatHold {
            $locked = Cohort::query()->whereKey($cohort->getKey())->lockForUpdate()->firstOrFail();
            if (! $locked->enrollmentIsOpen()) {
                throw ValidationException::withMessages(['cohort' => __('cohorts.closed')]);
            }
            if ($locked->hasSeat($learner)) {
                return null;
            }

            $hold = CohortSeatHold::query()->where('order_item_id', $orderItemId)->lockForUpdate()->first();
            if ($hold instanceof CohortSeatHold && $hold->status === CohortSeatHold::CONVERTED) {
                return $hold;
            }
            if ($locked->isFull($learner)) {
                throw new CohortFullException;
            }

            $hold ??= new CohortSeatHold(['order_item_id' => $orderItemId]);
            $hold->fill([
                'cohort_id' => $locked->getKey(), 'learner_id' => $learner->getKey(), 'status' => CohortSeatHold::HELD,
                'expires_at' => now()->addMinutes(CohortSeatHold::holdMinutes()), 'released_at' => null, 'release_reason' => null,
            ])->save();

            if ($this->audit->record('cohort.seat_held', $locked, null, null, [
                'learner_id' => $learner->getKey(), 'order_item_id' => $orderItemId, 'expires_at' => $hold->expires_at->toIso8601String(),
            ]) === null) {
                throw new RuntimeException('Unable to record cohort audit.');
            }

            return $hold;
        });
    }
}
