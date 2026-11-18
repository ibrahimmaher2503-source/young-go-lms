<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Payments;

/**
 * A provider transaction whose authenticity was proven (signature or direct provider lookup). `attemptReference` is our
 * `payment_attempts.reference`. `payload` is already sanitised: no card data, no secrets.
 */
final readonly class VerifiedPaymentEvent
{
    /** @param  array<string, scalar|null>  $payload */
    public function __construct(
        public string $gateway,
        public string $eventId,
        public ?string $attemptReference,
        public ?string $providerOrderId,
        public int $amountMinor,
        public string $currency,
        public bool $success,
        public bool $pending,
        public bool $refunded,
        public bool $voided,
        public array $payload = [],
    ) {}
}
