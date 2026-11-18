<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Actions;

use App\Domain\Commerce\ManualPaymentProof;
use App\Domain\Commerce\ManualPaymentStatus;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\PaymentAttempt;
use Illuminate\Validation\ValidationException;

/**
 * Cancels pending orders nobody is paying (T-P8-12, the timeout T-P8-05 deferred). The cancel goes through `TransitionOrder`,
 * which gives a reserved coupon use back. An order with an open gateway session or a transfer proof under review is never
 * touched: a late callback or an approval must still be able to pay it. Returns how many orders were cancelled.
 */
final class ExpireAbandonedOrders
{
    public function __construct(private readonly TransitionOrder $transition) {}

    public function __invoke(): int
    {
        $cutoff = now()->subHours(max(1, (int) config('payments.order_ttl_hours', 24)));
        $cancelled = 0;

        Order::query()->where('status', OrderStatus::Pending->value)->where('created_at', '<', $cutoff)->orderBy('id')
            ->chunkById(100, function ($orders) use (&$cancelled): void {
                foreach ($orders as $order) {
                    $busy = PaymentAttempt::query()->where('order_id', $order->getKey())->whereNotNull('active_order_id')->exists()
                        || ManualPaymentProof::query()->where('order_id', $order->getKey())->where('status', ManualPaymentStatus::Pending->value)->exists();
                    if ($busy) {
                        continue;
                    }
                    try {
                        ($this->transition)($order, OrderStatus::Cancelled);
                        $cancelled++;
                    } catch (ValidationException) {
                        // The order moved (paid) between the read and the lock: leave it.
                    }
                }
            });

        return $cancelled;
    }
}
