<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Payments;

/** What a gateway needs to refund one transaction. Built from server data only; `reference` is our unique refund reference (idempotency key). */
final readonly class RefundIntent
{
    public function __construct(
        public string $reference,
        public string $orderReference,
        public string $providerTransactionId,
        public int $amountMinor,
        public string $currency,
    ) {}
}
