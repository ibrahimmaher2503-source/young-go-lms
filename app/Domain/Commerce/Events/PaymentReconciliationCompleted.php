<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Events;

use App\Domain\Commerce\PaymentReconciliation;

/** A daily reconciliation run finished (T-P8-11). Report only; listeners must not change order state. */
final readonly class PaymentReconciliationCompleted
{
    public function __construct(public PaymentReconciliation $run) {}
}
