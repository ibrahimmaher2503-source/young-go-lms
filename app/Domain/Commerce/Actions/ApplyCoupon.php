<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Commerce\Coupon;
use App\Domain\Commerce\CouponRedemption;
use App\Domain\Commerce\CouponRedemptionStatus;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Applies a coupon to the payer's pending order and reserves one use. The client sends only the code; the discount is
 * computed on the server. The reservation moves the coupon counter with a guarded atomic update under a coupon row lock,
 * so concurrent checkouts can never pass the cap. The use is confirmed when the order is paid (`RedeemCoupon`) and given
 * back if it fails or is cancelled (`ReleaseCoupon`). One coupon per order.
 */
final class ApplyCoupon
{
    public function __construct(private readonly ValidateCoupon $validate, private readonly AuditLogger $audit) {}

    public function __invoke(Order $order, User $payer, string $code): Order
    {
        return DB::transaction(function () use ($order, $payer, $code): Order {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            $quote = ($this->validate)($code, $payer, $locked, true);

            $reserved = Coupon::query()->whereKey($quote->coupon->getKey())
                ->where(static fn (Builder $q) => $q->whereNull('max_redemptions')->orWhereColumn('redemptions_count', '<', 'max_redemptions'))
                ->increment('redemptions_count');
            if ($reserved === 0) {
                throw ValidationException::withMessages(['coupon' => __('commerce.coupon_exhausted')]);
            }

            CouponRedemption::query()->create([
                'coupon_id' => $quote->coupon->getKey(),
                'order_id' => $locked->getKey(),
                'user_id' => $payer->getKey(),
                'discount_minor' => $quote->discountMinor,
                'currency' => $locked->currency,
                'status' => CouponRedemptionStatus::Reserved,
                'reserved_at' => now(),
            ]);

            foreach ($quote->allocations as $itemId => $share) {
                $item = OrderItem::query()->findOrFail($itemId);
                $item->discount_minor = $share;
                $item->allocated_minor = $item->unit_price_minor - $share;
                $item->save();
            }
            $before = ['discount_minor' => $locked->discount_minor, 'total_minor' => $locked->total_minor];
            $locked->discount_minor = $quote->discountMinor;
            $locked->total_minor = $locked->subtotal_minor - $quote->discountMinor;
            $locked->save();

            $recorded = $this->audit->record('coupon.applied', $locked, (int) $payer->getKey(), $before, [
                'discount_minor' => $locked->discount_minor, 'total_minor' => $locked->total_minor,
            ], ['coupon_id' => $quote->coupon->getKey(), 'code' => $quote->coupon->code]);
            if ($recorded === null) {
                throw new RuntimeException('Unable to record coupon audit.');
            }

            return $locked;
        });
    }
}
