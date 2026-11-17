<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Queries;

use App\Domain\Commerce\Coupon;
use App\Domain\Commerce\CouponRedemption;
use App\Domain\Commerce\CouponRedemptionStatus;

/** Usage report numbers for coupons: confirmed uses, reserved uses and the money given away, per coupon or overall. */
final class CouponUsage
{
    /** @return array{redeemed: int, reserved: int, released: int, discount_minor: array<string, int>} discount by currency, confirmed uses only */
    public static function for(?Coupon $coupon = null): array
    {
        $rows = CouponRedemption::query()->when($coupon !== null, static fn ($q) => $q->where('coupon_id', $coupon?->getKey()))
            ->get(['status', 'discount_minor', 'currency']);

        $report = ['redeemed' => 0, 'reserved' => 0, 'released' => 0, 'discount_minor' => []];
        foreach ($rows as $row) {
            $report[$row->status->value]++;
            if ($row->status === CouponRedemptionStatus::Redeemed) {
                $report['discount_minor'][$row->currency] = ($report['discount_minor'][$row->currency] ?? 0) + $row->discount_minor;
            }
        }

        return $report;
    }
}
