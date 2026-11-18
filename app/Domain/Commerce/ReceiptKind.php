<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

enum ReceiptKind: string
{
    case Sale = 'sale';
    /** A refund receipt (credit note) that points at the sale it reverses. */
    case Refund = 'refund';
}
