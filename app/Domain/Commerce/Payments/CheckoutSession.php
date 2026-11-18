<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Payments;

/** The gateway's answer to a checkout request: where to send the payer and the provider's ids. */
final readonly class CheckoutSession
{
    public function __construct(
        public string $gateway,
        public string $providerIntentId,
        public ?string $providerOrderId,
        public string $redirectUrl,
    ) {}
}
