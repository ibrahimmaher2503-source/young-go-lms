<?php

declare(strict_types=1);

namespace App\Domain\Cohorts\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Cohorts\Cohort;
use App\Domain\Cohorts\CohortSeatHold;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Gives held seats back (order cancelled or failed, hold expired). Only a `held` hold is released; a converted one is a real
 * seat and stays. `$onlyExpired` makes the expiry job safe against a payment that renewed or converted the hold meanwhile.
 * Returns how many holds were released.
 */
final class ReleaseSeatHolds
{
    public const string EXPIRED = 'expired';

    public const string CANCELLED = 'cancelled';

    public const string FAILED = 'failed';

    public function __construct(private readonly AuditLogger $audit) {}

    /** @param  list<int>  $orderItemIds */
    public function __invoke(array $orderItemIds, string $reason, bool $onlyExpired = false): int
    {
        if ($orderItemIds === []) {
            return 0;
        }

        return DB::transaction(function () use ($orderItemIds, $reason, $onlyExpired): int {
            $released = 0;
            $holds = CohortSeatHold::query()->whereIn('order_item_id', $orderItemIds)->where('status', CohortSeatHold::HELD)->orderBy('id')->lockForUpdate()->get();

            foreach ($holds as $hold) {
                if ($onlyExpired && $hold->expires_at->isFuture()) {
                    continue;
                }
                $hold->forceFill(['status' => CohortSeatHold::RELEASED, 'released_at' => now(), 'release_reason' => $reason])->save();
                $cohort = Cohort::query()->findOrFail($hold->cohort_id);
                if ($this->audit->record('cohort.seat_released', $cohort, null, ['status' => CohortSeatHold::HELD], ['status' => CohortSeatHold::RELEASED], [
                    'learner_id' => $hold->learner_id, 'order_item_id' => $hold->order_item_id, 'reason' => $reason,
                ]) === null) {
                    throw new RuntimeException('Unable to record cohort audit.');
                }
                $released++;
            }

            return $released;
        });
    }
}
