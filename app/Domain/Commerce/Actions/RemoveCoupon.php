<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Commerce\Coupon;
use App\Domain\Commerce\CouponRedemption;
use App\Domain\Commerce\CouponRedemptionStatus;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderItem;
use App\Domain\Commerce\OrderStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Takes a reserved coupon off the payer's pending order: the use is given back and the order returns to its full price. */
final class RemoveCoupon
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(Order $order, User $payer): Order
    {
        return DB::transaction(function () use ($order, $payer): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            if ($payer->getAttribute('status') !== 'active' || $locked->payer_id !== $payer->getKey() || $locked->status !== OrderStatus::Pending) {
                throw ValidationException::withMessages(['coupon' => __('commerce.coupon_order_invalid')]);
            }
            $redemption = CouponRedemption::query()->where('order_id', $locked->getKey())->where('status', CouponRedemptionStatus::Reserved->value)->first();
            if (! $redemption instanceof CouponRedemption) {
                throw ValidationException::withMessages(['coupon' => __('commerce.coupon_not_applied')]);
            }

            Coupon::query()->whereKey($redemption->coupon_id)->where('redemptions_count', '>', 0)->decrement('redemptions_count');
            $before = ['discount_minor' => $locked->discount_minor, 'total_minor' => $locked->total_minor];
            $redemption->delete();

            foreach (OrderItem::query()->where('order_id', $locked->getKey())->get() as $item) {
                $item->discount_minor = 0;
                $item->allocated_minor = $item->unit_price_minor;
                $item->save();
            }
            $locked->discount_minor = 0;
            $locked->total_minor = $locked->subtotal_minor;
            $locked->save();

            if ($this->audit->record('coupon.removed', $locked, (int) $payer->getKey(), $before, ['discount_minor' => 0, 'total_minor' => $locked->total_minor], ['coupon_id' => $redemption->coupon_id]) === null) {
                throw new RuntimeException('Unable to record coupon audit.');
            }

            return $locked;
        });
    }
}
