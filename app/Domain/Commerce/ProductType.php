<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

/** What an order line sells (ED-012). Courses and bundles (T-P8-07) can be sold; plans cannot yet. */
enum ProductType: string
{
    case Course = 'course';
    case Bundle = 'bundle';
    case Plan = 'plan';

    public function isSellable(): bool
    {
        return $this === self::Course || $this === self::Bundle;
    }
}
