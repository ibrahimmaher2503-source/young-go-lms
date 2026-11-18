<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Payments;

use App\Domain\Commerce\Contracts\PaymentGateway;
use Carbon\CarbonImmutable;
use LogicException;

/**
 * Deterministic in-memory gateway for local and test runs only. It records every call, can be told to fail, and signs and
 * verifies callbacks with a fixed fake key so T-P8-03 tests can build valid and tampered callbacks.
 */
final class FakePaymentGateway implements PaymentGateway
{
    public const string SIGNING_KEY = 'fake-gateway-signing-key';

    /** @var list<array{method: string, args: array<string, mixed>}> */
    public array $calls = [];

    private ?PaymentGatewayException $nextFailure = null;

    /** @var array<string, VerifiedPaymentEvent> */
    private array $transactions = [];

    private int $counter = 0;

    public function __construct()
    {
        if (! app()->environment('local', 'testing')) {
            throw new LogicException('The fake payment gateway is only available in local and testing environments.');
        }
    }

    public function key(): string
    {
        return 'fake';
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function supportedMethods(): array
    {
        return array_values(array_map('strval', (array) config('payments.methods', [])));
    }

    /** Makes the next gateway call (create or fetch) fail once. */
    public function failNext(string $message = 'Fake gateway failure.', string $code = 'gateway_error'): self
    {
        $this->nextFailure = new PaymentGatewayException($message, $code);

        return $this;
    }

    /** Programs what `fetchTransaction` returns for an id. */
    public function queueTransaction(VerifiedPaymentEvent $event): self
    {
        $this->transactions[$event->eventId] = $event;

        return $this;
    }

    public function createCheckout(CheckoutIntent $intent): CheckoutSession
    {
        $this->calls[] = ['method' => 'createCheckout', 'args' => [
            'reference' => $intent->reference, 'order_reference' => $intent->orderReference, 'amount_minor' => $intent->amountMinor,
            'currency' => $intent->currency, 'methods' => $intent->methods, 'items' => $intent->items,
        ]];
        $this->throwIfFailing();
        $this->counter++;
        $id = 'fake_intent_'.$intent->reference;

        return new CheckoutSession('fake', $id, 'fake_order_'.$this->counter, 'https://fake-gateway.test/checkout/'.$id);
    }

    public function verifyCallback(array $payload, ?string $signature): VerifiedPaymentEvent
    {
        $this->calls[] = ['method' => 'verifyCallback', 'args' => ['payload' => $payload]];
        if ($signature === null || ! hash_equals(self::sign($payload), $signature)) {
            throw new InvalidPaymentSignature;
        }

        return new VerifiedPaymentEvent(
            'fake', self::text($payload, 'id') ?? '', self::text($payload, 'reference'), self::text($payload, 'order_id'),
            (int) self::text($payload, 'amount_minor'), self::text($payload, 'currency') ?? '', (bool) ($payload['success'] ?? false),
            (bool) ($payload['pending'] ?? false), (bool) ($payload['refunded'] ?? false), (bool) ($payload['voided'] ?? false),
        );
    }

    public function fetchTransaction(string $transactionId): VerifiedPaymentEvent
    {
        $this->calls[] = ['method' => 'fetchTransaction', 'args' => ['transaction_id' => $transactionId]];
        $this->throwIfFailing();

        return $this->transactions[$transactionId] ?? throw new PaymentGatewayException('Unknown fake transaction.', 'not_found');
    }

    public function listTransactions(CarbonImmutable $from, CarbonImmutable $to): array
    {
        $this->calls[] = ['method' => 'listTransactions', 'args' => ['from' => $from->toIso8601String(), 'to' => $to->toIso8601String()]];
        $this->throwIfFailing();

        // The fake keeps no creation time: every queued transaction counts as inside the window.
        return array_values($this->transactions);
    }

    public function supportsRefunds(): bool
    {
        return true;
    }

    public function refund(RefundIntent $intent): RefundResult
    {
        $this->calls[] = ['method' => 'refund', 'args' => [
            'reference' => $intent->reference, 'order_reference' => $intent->orderReference, 'transaction_id' => $intent->providerTransactionId,
            'amount_minor' => $intent->amountMinor, 'currency' => $intent->currency,
        ]];
        $this->throwIfFailing();

        return new RefundResult('fake', 'fake_refund_'.$intent->reference);
    }

    /**
     * Signature a valid fake callback carries.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function sign(array $payload): string
    {
        ksort($payload);

        return hash_hmac('sha256', (string) json_encode($payload), self::SIGNING_KEY);
    }

    /** @param  array<string, mixed>  $payload */
    private static function text(array $payload, string $key): ?string
    {
        return isset($payload[$key]) && is_scalar($payload[$key]) ? (string) $payload[$key] : null;
    }

    private function throwIfFailing(): void
    {
        if ($this->nextFailure !== null) {
            $failure = $this->nextFailure;
            $this->nextFailure = null;

            throw $failure;
        }
    }
}
