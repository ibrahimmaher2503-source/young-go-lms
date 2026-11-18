<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

/** What an order line sells (ED-012). Courses, bundles (T-P8-07) and subscription plans (T-P8-08) can be sold. */
enum ProductType: string
{
    case Course = 'course';
    case Bundle = 'bundle';
    case Plan = 'plan';

    public function isSellable(): bool
    {
        return true;
    }
}
