<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

/** What an order line sells (ED-012). Only courses can be priced and sold until bundles (T-P8-07) and plans exist. */
enum ProductType: string
{
    case Course = 'course';
    case Bundle = 'bundle';
    case Plan = 'plan';

    public function isSellable(): bool
    {
        return $this === self::Course;
    }
}
