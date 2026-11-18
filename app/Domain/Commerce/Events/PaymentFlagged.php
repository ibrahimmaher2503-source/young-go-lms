<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Events;

use App\Domain\Commerce\PaymentEvent;

/** A verified callback was stored but not applied (amount mismatch, duplicate payment, closed order...). Finance reviews it. Dispatched once, after commit. */
final readonly class PaymentFlagged
{
    public function __construct(public PaymentEvent $event) {}
}
