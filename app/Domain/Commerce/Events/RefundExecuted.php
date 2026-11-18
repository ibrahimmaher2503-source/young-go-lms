<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Events;

use App\Domain\Commerce\Refund;

/** A refund was executed (money returned, order updated). Dispatched once, after commit, by ExecuteRefund; consumers (instructor ledger, T-P8-09) must be idempotent by refund id. */
final readonly class RefundExecuted
{
    public function __construct(public Refund $refund) {}
}
