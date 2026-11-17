<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

/**
 * What a coupon would take off an order: the total and each line's share (keyed by order item id). Built only by
 * `ValidateCoupon`; shares add up to the total and never exceed a line's price.
 */
final readonly class CouponQuote
{
    /** @param  array<int, int>  $allocations */
    public function __construct(public Coupon $coupon, public int $discountMinor, public int $eligibleMinor, public array $allocations) {}
}
