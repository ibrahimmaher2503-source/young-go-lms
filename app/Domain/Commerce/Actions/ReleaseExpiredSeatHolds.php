<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Actions;

use App\Domain\Cohorts\Actions\ReleaseSeatHolds;
use App\Domain\Cohorts\CohortSeatHold;
use App\Domain\Commerce\ManualPaymentProof;
use App\Domain\Commerce\ManualPaymentStatus;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderItem;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\PaymentAttempt;

/**
 * Gives back seat holds whose time ran out (`commerce.seat_hold_minutes`). The order itself is left to `ExpireAbandonedOrders`.
 * A hold on an order that is still being paid (open gateway session or a transfer proof under review) is extended instead, so a
 * slow payment never loses its seat; a hold on an order that is no longer pending is released. Returns [released, extended].
 */
final class ReleaseExpiredSeatHolds
{
    public function __construct(private readonly ReleaseSeatHolds $release) {}

    /** @return array{released: int, extended: int} */
    public function __invoke(): array
    {
        $released = 0;
        $extended = 0;

        CohortSeatHold::query()->where('status', CohortSeatHold::HELD)->where('expires_at', '<=', now())->orderBy('id')
            ->chunkById(100, function ($holds) use (&$released, &$extended): void {
                foreach ($holds as $hold) {
                    $item = OrderItem::query()->find($hold->order_item_id);
                    $order = $item === null ? null : Order::query()->find($item->order_id);
                    $busy = $order !== null && $order->status === OrderStatus::Pending && (
                        PaymentAttempt::query()->where('order_id', $order->getKey())->whereNotNull('active_order_id')->exists()
                        || ManualPaymentProof::query()->where('order_id', $order->getKey())->where('status', ManualPaymentStatus::Pending->value)->exists());

                    if ($busy) {
                        CohortSeatHold::query()->whereKey($hold->getKey())->where('status', CohortSeatHold::HELD)
                            ->update(['expires_at' => now()->addMinutes(CohortSeatHold::holdMinutes())]);
                        $extended++;

                        continue;
                    }
                    $released += ($this->release)([(int) $hold->order_item_id], ReleaseSeatHolds::EXPIRED, true);
                }
            });

        return ['released' => $released, 'extended' => $extended];
    }
}
