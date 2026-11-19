<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Events;

use App\Domain\Commerce\ManualPaymentProof;

final readonly class ManualPaymentRejected
{
    public function __construct(public ManualPaymentProof $proof) {}
}
