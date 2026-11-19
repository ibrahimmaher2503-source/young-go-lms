<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Actions;

use App\Domain\Cohorts\Actions\HoldCohortSeat;
use App\Domain\Cohorts\Actions\ReleaseSeatHolds;
use App\Domain\Cohorts\Cohort;
use App\Domain\Cohorts\CohortFullException;
use App\Domain\Cohorts\CohortSeatHold;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Renews the seat holds of a still-pending order when its buyer comes back to checkout. Only holds that are live or lapsed by
 * expiry are renewed (a hold given back for another reason stays given back). If the seat was taken meanwhile the buyer gets the
 * `cohort_full` error and can pick another cohort or wait. Does nothing for an order that is not pending.
 */
final class RenewOrderSeatHolds
{
    public function __construct(private readonly HoldCohortSeat $hold) {}

    public function __invoke(Order $order): void
    {
        DB::transaction(function () use ($order): void {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->status !== OrderStatus::Pending) {
                return;
            }

            $holds = CohortSeatHold::query()->whereIn('order_item_id', $locked->items()->select('id'))->orderBy('cohort_id')->get()
                ->filter(static fn (CohortSeatHold $hold): bool => $hold->status === CohortSeatHold::HELD
                    || ($hold->status === CohortSeatHold::RELEASED && $hold->release_reason === ReleaseSeatHolds::EXPIRED));

            foreach ($holds as $hold) {
                $learner = User::query()->find($hold->learner_id);
                $cohort = Cohort::query()->find($hold->cohort_id);
                if ($learner === null || $cohort === null) {
                    continue;
                }
                try {
                    ($this->hold)($cohort, $learner, (int) $hold->order_item_id);
                } catch (CohortFullException) {
                    throw ValidationException::withMessages(['order' => __('commerce.cohort_full')]);
                }
            }
        });
    }
}
