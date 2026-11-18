<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Payments;

use App\Domain\Commerce\Contracts\PaymentGateway;

/** The default outside local/testing while credentials are absent: it never talks to anyone and refuses every call. */
final class NullPaymentGateway implements PaymentGateway
{
    public function key(): string
    {
        return 'null';
    }

    public function isConfigured(): bool
    {
        return false;
    }

    public function supportedMethods(): array
    {
        return [];
    }

    public function createCheckout(CheckoutIntent $intent): CheckoutSession
    {
        throw new PaymentGatewayNotConfigured;
    }

    public function verifyCallback(array $payload, ?string $signature): VerifiedPaymentEvent
    {
        throw new InvalidPaymentSignature('No payment gateway is configured; callbacks cannot be verified.');
    }

    public function fetchTransaction(string $transactionId): VerifiedPaymentEvent
    {
        throw new PaymentGatewayNotConfigured;
    }
}
