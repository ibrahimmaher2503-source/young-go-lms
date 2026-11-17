<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

/** Reserved while the order is pending (counts toward the caps), redeemed once it is paid, released if it fails or is cancelled. */
enum CouponRedemptionStatus: string
{
    case Reserved = 'reserved';
    case Redeemed = 'redeemed';
    case Released = 'released';
}
