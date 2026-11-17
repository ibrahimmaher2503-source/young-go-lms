<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderStatus;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * The only way to change an order's totals after it is paid: staff with `payment.approve_manual` set the order discount
 * with a written reason, and the before and after amounts are audited. The subtotal and the line snapshots stay as they were.
 */
final class AdjustOrderTotal
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(Order $order, User $actor, int $discountMinor, string $reason): Order
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('payment.approve_manual')) {
            throw new AuthorizationException;
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => __('commerce.reason_invalid')]);
        }

        return DB::transaction(function () use ($order, $actor, $discountMinor, $reason): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            if (in_array($locked->status, [OrderStatus::Failed, OrderStatus::Cancelled], true)) {
                throw ValidationException::withMessages(['order' => __('commerce.order_closed')]);
            }
            if ($discountMinor < 0 || $discountMinor > $locked->subtotal_minor) {
                throw ValidationException::withMessages(['discount' => __('commerce.discount_invalid')]);
            }

            $before = ['discount_minor' => $locked->discount_minor, 'total_minor' => $locked->total_minor];
            $after = ['discount_minor' => $discountMinor, 'total_minor' => $locked->subtotal_minor - $discountMinor];

            $locked->withTotalsUnlocked(static function (Order $o) use ($after): void {
                $o->discount_minor = $after['discount_minor'];
                $o->total_minor = $after['total_minor'];
                $o->save();
            });

            if ($this->audit->record('order.adjusted', $locked, (int) $actor->getKey(), $before, $after, ['reason' => $reason]) === null) {
                throw new RuntimeException('Unable to record order audit.');
            }

            return $locked;
        });
    }
}
