<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Actions;

use App\Domain\Audit\AuditLogger;
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
    public function __construct(private readonly AuditLogger $audit, private readonly RedeemCoupon $redeem, private readonly ReleaseCoupon $release) {}

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

            $locked->status = $to;
            if ($to === OrderStatus::Paid && $locked->paid_at === null) {
                $locked->paid_at = now();
            }
            $locked->save();

            // A coupon use is confirmed with the payment and given back if the order fails or is cancelled.
            if ($to === OrderStatus::Paid) {
                ($this->redeem)($locked);
            } elseif ($to === OrderStatus::Failed || $to === OrderStatus::Cancelled) {
                ($this->release)($locked);
            }

            $recorded = $this->audit->record('order.status_changed', $locked, $actor === null ? null : (int) $actor->getKey(), ['status' => $from->value], ['status' => $to->value]);
            if ($recorded === null) {
                throw new RuntimeException('Unable to record order audit.');
            }

            return $locked;
        });
    }
}
