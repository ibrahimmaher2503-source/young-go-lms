<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

enum CouponType: string
{
    case Percent = 'percent';
    case Fixed = 'fixed';
}
