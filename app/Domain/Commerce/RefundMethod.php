<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

enum RefundMethod: string
{
    /** Money goes back through the payment provider that took it. */
    case Gateway = 'gateway';
    /** Finance returns the money outside the system (transfer) and records the external reference. */
    case Manual = 'manual';
}
