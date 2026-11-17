<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Commerce\CouponRedemption;
use App\Domain\Commerce\CouponRedemptionStatus;
use App\Domain\Commerce\Order;
use RuntimeException;

/**
 * Records the coupon use when the order is paid. Called by `TransitionOrder` inside its transaction, so the order and
 * the redemption move together. An order with no coupon, or a use already confirmed, is a no-op, so a retried payment
 * callback is harmless. The cap was taken at apply time, so a paid order is never refused here.
 */
final class RedeemCoupon
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(Order $order): ?CouponRedemption
    {
        $redemption = CouponRedemption::query()->where('order_id', $order->getKey())->lockForUpdate()->first();
        if (! $redemption instanceof CouponRedemption || $redemption->status !== CouponRedemptionStatus::Reserved) {
            return $redemption;
        }

        $redemption->status = CouponRedemptionStatus::Redeemed;
        $redemption->redeemed_at = now();
        $redemption->save();

        $recorded = $this->audit->record('coupon.redeemed', $order, null, ['status' => CouponRedemptionStatus::Reserved->value], ['status' => CouponRedemptionStatus::Redeemed->value], [
            'coupon_id' => $redemption->coupon_id, 'discount_minor' => $redemption->discount_minor,
        ]);
        if ($recorded === null) {
            throw new RuntimeException('Unable to record coupon audit.');
        }

        return $redemption;
    }
}
