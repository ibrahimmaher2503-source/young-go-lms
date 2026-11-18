<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Contracts;

use App\Domain\Commerce\Payments\CheckoutIntent;
use App\Domain\Commerce\Payments\CheckoutSession;
use App\Domain\Commerce\Payments\InvalidPaymentSignature;
use App\Domain\Commerce\Payments\PaymentGatewayException;
use App\Domain\Commerce\Payments\VerifiedPaymentEvent;

/**
 * One payment provider behind a stable contract (Arch 10.3). Amounts always come from the server-side order via
 * `CheckoutIntent`; nothing a browser sends reaches a gateway. Refunds join the contract in T-P8-06.
 */
interface PaymentGateway
{
    /** Short stable key stored on `payment_attempts.gateway`. */
    public function key(): string;

    /** False when credentials are missing: callers refuse before creating any attempt row and no network call happens. */
    public function isConfigured(): bool;

    /**
     * Payment methods this gateway can serve right now, a subset of the configured method keys.
     *
     * @return list<string>
     */
    public function supportedMethods(): array;

    /** @throws PaymentGatewayException */
    public function createCheckout(CheckoutIntent $intent): CheckoutSession;

    /**
     * Verifies a provider callback signature and normalises the payload. Pure computation, no network.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws InvalidPaymentSignature
     */
    public function verifyCallback(array $payload, ?string $signature): VerifiedPaymentEvent;

    /**
     * Asks the provider for the authoritative state of one transaction (reconciliation, T-P8-11).
     *
     * @throws PaymentGatewayException
     */
    public function fetchTransaction(string $transactionId): VerifiedPaymentEvent;
}
