<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Cohorts\Actions\ConvertSeatHolds;
use App\Domain\Cohorts\Actions\ReleaseSeatHolds;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Moves an order along its state machine. For trusted domain callers only (gateway webhook, manual approval, refunds);
 * the caller proves the payment, this action only guards the transition. Asking for the status the order already has is
 * a no-op, so a retried callback is harmless.
 */
final class TransitionOrder
{
    public function __construct(private readonly AuditLogger $audit, private readonly RedeemCoupon $redeem, private readonly ReleaseCoupon $release, private readonly ConvertSeatHolds $convertSeats, private readonly ReleaseSeatHolds $releaseSeats) {}

    public function __invoke(Order $order, OrderStatus $to, ?User $actor = null): Order
    {
        return DB::transaction(function () use ($order, $to, $actor): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $from = $locked->status;
            if ($from === $to) {
                return $locked;
            }
            if (! $from->canMoveTo($to)) {
                throw ValidationException::withMessages(['order' => __('commerce.transition_invalid')]);
            }
            if ($to === OrderStatus::Paid && ! $locked->isInActiveMarket()) {
                throw ValidationException::withMessages(['order' => __('commerce.market_unavailable')]);
            }

            $locked->status = $to;
            if ($to === OrderStatus::Paid && $locked->paid_at === null) {
                $locked->paid_at = now();
            }
            $locked->save();

            // A coupon use is confirmed with the payment and given back if the order fails or is cancelled.
            // A held cohort seat becomes a real seat with the payment and is given back if the order fails or is cancelled.
            if ($to === OrderStatus::Paid) {
                ($this->redeem)($locked);
                ($this->convertSeats)($this->itemIds($locked), $actor);
            } elseif ($to === OrderStatus::Failed || $to === OrderStatus::Cancelled) {
                ($this->release)($locked);
                ($this->releaseSeats)($this->itemIds($locked), $to === OrderStatus::Failed ? ReleaseSeatHolds::FAILED : ReleaseSeatHolds::CANCELLED);
            }

            $recorded = $this->audit->record('order.status_changed', $locked, $actor === null ? null : (int) $actor->getKey(), ['status' => $from->value], ['status' => $to->value]);
            if ($recorded === null) {
                throw new RuntimeException('Unable to record order audit.');
            }

            return $locked;
        });
    }

    /** @return list<int> */
    private function itemIds(Order $order): array
    {
        return array_map('intval', $order->items()->pluck('id')->all());
    }
}
