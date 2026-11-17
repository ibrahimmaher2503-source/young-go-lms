<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Events;

use App\Domain\Commerce\ManualPaymentProof;

/** A payer submitted a transfer proof. Dispatched after commit; consumers must be idempotent by proof id. */
final readonly class ManualPaymentProofSubmitted
{
    public function __construct(public ManualPaymentProof $proof) {}
}
