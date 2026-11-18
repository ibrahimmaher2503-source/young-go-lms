<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Payments;

use App\Domain\Commerce\PaymentEvent;

/** What happened to one verified callback. `duplicate` means it was already stored earlier and nothing changed. */
final readonly class PaymentWebhookResult
{
    public function __construct(public PaymentEvent $event, public bool $duplicate) {}
}
