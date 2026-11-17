<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Commerce\Coupon;
use App\Domain\Commerce\CouponRedemption;
use App\Domain\Commerce\CouponRedemptionStatus;
use App\Domain\Commerce\Order;
use RuntimeException;

/**
 * Gives a reserved coupon use back when its order fails or is cancelled, so the cap frees up. Called by `TransitionOrder`
 * inside its transaction. Only a reserved use is released; a redeemed use stays (refunds do not restore coupon uses).
 */
final class ReleaseCoupon
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(Order $order): bool
    {
        $redemption = CouponRedemption::query()->where('order_id', $order->getKey())->lockForUpdate()->first();
        if (! $redemption instanceof CouponRedemption || $redemption->status !== CouponRedemptionStatus::Reserved) {
            return false;
        }

        $redemption->status = CouponRedemptionStatus::Released;
        $redemption->released_at = now();
        $redemption->save();
        Coupon::query()->whereKey($redemption->coupon_id)->where('redemptions_count', '>', 0)->decrement('redemptions_count');

        $recorded = $this->audit->record('coupon.released', $order, null, ['status' => CouponRedemptionStatus::Reserved->value], ['status' => CouponRedemptionStatus::Released->value], [
            'coupon_id' => $redemption->coupon_id,
        ]);
        if ($recorded === null) {
            throw new RuntimeException('Unable to record coupon audit.');
        }

        return true;
    }
}
