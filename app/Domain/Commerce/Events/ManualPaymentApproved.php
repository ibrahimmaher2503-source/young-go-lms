<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Events;

use App\Domain\Commerce\ManualPaymentProof;

/** Finance approved a proof; the order is paid and access granted. Dispatched once, after commit; consumers must be idempotent by proof id. */
final readonly class ManualPaymentApproved
{
    public function __construct(public ManualPaymentProof $proof) {}
}
