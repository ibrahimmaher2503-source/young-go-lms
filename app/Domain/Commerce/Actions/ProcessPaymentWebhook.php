<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Commerce\Contracts\PaymentGateway;
use App\Domain\Commerce\Events\PaymentFlagged;
use App\Domain\Commerce\Events\PaymentVerified;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\PaymentAttempt;
use App\Domain\Commerce\PaymentAttemptStatus;
use App\Domain\Commerce\PaymentEvent;
use App\Domain\Commerce\PaymentEventOutcome;
use App\Domain\Commerce\Payments\InvalidPaymentSignature;
use App\Domain\Commerce\Payments\PaymentPayloadScrubber;
use App\Domain\Commerce\Payments\PaymentWebhookResult;
use App\Domain\Commerce\Payments\VerifiedPaymentEvent;
use App\Domain\Entitlements\EntitlementSource;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * The single fulfilment path for online payments (T-P8-03, YG-GAP-PAY-002). The signature is verified through the gateway
 * contract before anything is read or written; an invalid one throws and changes nothing. A verified callback is stored once
 * (unique gateway + transaction id + state), so a replay is a no-op. Then, in one transaction with the attempt and order
 * locked:
 *  - success on a pending order whose amount and currency equal the server order: order paid (TransitionOrder), course
 *    lines granted (bundle and plan lines too) through GrantOrderEntitlements, attempt succeeded, `PaymentVerified` after commit;
 *  - success that cannot be applied safely (amount or currency differs, order already paid or closed, refunded first,
 *    market closed, unknown reference) is stored and flagged for finance, with no state change;
 *  - failure: the attempt is closed as failed, the order stays pending so the payer can pay again; a later success on that
 *    order is still accepted (a failed attempt is not a closed order);
 *  - pending, out-of-order and already-handled events change nothing; refunds and voids are flagged (refunds: T-P8-06).
 */
final class ProcessPaymentWebhook
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PaymentGateway $gateway,
        private readonly TransitionOrder $transition,
        private readonly GrantOrderEntitlements $grants,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws InvalidPaymentSignature
     */
    public function __invoke(array $payload, ?string $signature): PaymentWebhookResult
    {
        $verified = $this->gateway->verifyCallback($payload, $signature); // nothing below runs for an unproven callback
        if ($verified->eventId === '') {
            throw new InvalidPaymentSignature('Payment callback carries no transaction id.');
        }

        $state = $this->state($verified);
        $key = $verified->eventId.':'.$state;
        $existing = $this->find($verified->gateway, $key);
        if ($existing !== null) {
            return new PaymentWebhookResult($existing, true);
        }

        try {
            $event = DB::transaction(fn (): PaymentEvent => $this->apply($verified, $payload, $key, $state));
        } catch (UniqueConstraintViolationException) {
            // A concurrent delivery of the same callback won the race.
            return new PaymentWebhookResult($this->find($verified->gateway, $key) ?? throw new RuntimeException('Payment event vanished.'), true);
        }

        return new PaymentWebhookResult($event, false);
    }

    private function find(string $gateway, string $key): ?PaymentEvent
    {
        return PaymentEvent::query()->where('gateway', $gateway)->where('event_key', $key)->first();
    }

    /** @param  array<string, mixed>  $payload */
    private function apply(VerifiedPaymentEvent $verified, array $payload, string $key, string $state): PaymentEvent
    {
        $attempt = $verified->attemptReference === null ? null
            : PaymentAttempt::query()->where('reference', $verified->attemptReference)->lockForUpdate()->first();
        $order = $attempt === null ? null : Order::query()->lockForUpdate()->find($attempt->order_id);

        $event = PaymentEvent::query()->create([
            'gateway' => $verified->gateway, 'event_key' => $key, 'provider_event_id' => $verified->eventId, 'state' => $state,
            'payment_attempt_id' => $attempt?->getKey(), 'order_id' => $order?->getKey(),
            'attempt_reference' => $verified->attemptReference === null ? null : mb_substr($verified->attemptReference, 0, 48),
            'amount_minor' => max(0, $verified->amountMinor), 'currency' => mb_substr($verified->currency, 0, 3),
            'outcome' => PaymentEventOutcome::Ignored->value, 'payload' => $this->storedPayload($verified, $payload), 'processed_at' => now(),
        ]);

        [$outcome, $reason] = $attempt === null || $order === null
            ? [PaymentEventOutcome::Unmatched, 'unmatched_reference']
            : $this->decide($verified, $state, $event, $attempt, $order);

        $event->forceFill(['outcome' => $outcome->value, 'reason' => $reason])->save();

        if ($outcome === PaymentEventOutcome::Fulfilled && $order !== null) {
            $this->record('payment.verified', $event, ['status' => OrderStatus::Pending->value], ['status' => OrderStatus::Paid->value]);
            DB::afterCommit(static fn () => event(new PaymentVerified($order, $event)));
        } elseif ($outcome === PaymentEventOutcome::AttemptFailed) {
            $this->record('payment.attempt_failed', $event, ['status' => PaymentAttemptStatus::Created->value], ['status' => PaymentAttemptStatus::Failed->value]);
        } elseif ($outcome->needsReview()) {
            $this->record('payment.flagged', $event, null, ['outcome' => $outcome->value, 'reason' => $reason]);
            DB::afterCommit(static fn () => event(new PaymentFlagged($event)));
        }

        return $event;
    }

    /** @return array{0: PaymentEventOutcome, 1: string|null} */
    private function decide(VerifiedPaymentEvent $verified, string $state, PaymentEvent $event, PaymentAttempt $attempt, Order $order): array
    {
        if ($attempt->gateway !== $verified->gateway) {
            return [PaymentEventOutcome::Flagged, 'gateway_mismatch'];
        }

        return match ($state) {
            // Refunds are T-P8-06; finance handles a refund or void by hand until then, so it must not pass silently.
            'refunded', 'voided' => [PaymentEventOutcome::Flagged, 'refund_or_void_received'],
            'pending' => [PaymentEventOutcome::Ignored, 'pending'],
            'failed' => $this->failure($attempt, $order),
            default => $this->success($verified, $event, $attempt, $order),
        };
    }

    /** @return array{0: PaymentEventOutcome, 1: string|null} */
    private function failure(PaymentAttempt $attempt, Order $order): array
    {
        if ($order->status !== OrderStatus::Pending) {
            return [PaymentEventOutcome::Ignored, 'order_not_pending'];
        }
        if (! $attempt->status->isOpen()) {
            // Out of order: the attempt already succeeded, failed, expired or was cancelled.
            return [PaymentEventOutcome::Ignored, 'attempt_'.$attempt->status->value];
        }

        $attempt->forceFill(['status' => PaymentAttemptStatus::Failed, 'failure_code' => 'declined', 'active_order_id' => null, 'signature_valid' => true])->save();

        return [PaymentEventOutcome::AttemptFailed, 'declined'];
    }

    /** @return array{0: PaymentEventOutcome, 1: string|null} */
    private function success(VerifiedPaymentEvent $verified, PaymentEvent $event, PaymentAttempt $attempt, Order $order): array
    {
        $refundedFirst = PaymentEvent::query()->where('gateway', $verified->gateway)->where('provider_event_id', $verified->eventId)
            ->whereIn('state', ['refunded', 'voided'])->whereKeyNot($event->getKey())->exists();

        $reason = match (true) {
            $refundedFirst => 'refunded_before_success',
            $order->status === OrderStatus::Paid => 'duplicate_payment',
            $order->status !== OrderStatus::Pending => 'order_closed',
            $verified->amountMinor !== $order->total_minor || strtoupper($verified->currency) !== strtoupper($order->currency) => 'amount_mismatch',
            ! $order->isInActiveMarket() => 'market_unavailable',
            default => null,
        };
        if ($reason !== null) {
            return [PaymentEventOutcome::Flagged, $reason];
        }

        $attempt->forceFill([
            'status' => PaymentAttemptStatus::Succeeded, 'active_order_id' => null, 'signature_valid' => true, 'failure_code' => null,
            'provider_order_id' => $attempt->provider_order_id ?? $verified->providerOrderId,
        ])->save();
        ($this->transition)($order, OrderStatus::Paid);
        ($this->grants)($order, EntitlementSource::Purchase);

        return [PaymentEventOutcome::Fulfilled, null];
    }

    private function state(VerifiedPaymentEvent $verified): string
    {
        return match (true) {
            $verified->refunded => 'refunded',
            $verified->voided => 'voided',
            $verified->pending => 'pending',
            $verified->success => 'success',
            default => 'failed',
        };
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<array-key, mixed>
     */
    private function storedPayload(VerifiedPaymentEvent $verified, array $raw): array
    {
        // The adapter already reduced its payload to non-sensitive fields; otherwise scrub the raw one.
        return (new PaymentPayloadScrubber)->scrub($verified->payload !== [] ? $verified->payload : $raw);
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>  $after
     */
    private function record(string $audit, PaymentEvent $event, ?array $before, array $after): void
    {
        $recorded = $this->audit->record($audit, $event, null, $before, $after + [
            'gateway' => $event->gateway, 'reference' => $event->attempt_reference, 'order_id' => $event->order_id,
            'amount_minor' => $event->amount_minor, 'currency' => $event->currency, 'state' => $event->state,
        ]);
        if ($recorded === null) {
            throw new RuntimeException('Unable to record payment audit.');
        }
    }
}
