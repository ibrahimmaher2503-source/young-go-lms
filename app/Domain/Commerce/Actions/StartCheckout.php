<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Commerce\Contracts\PaymentGateway;
use App\Domain\Commerce\Events\PaymentCheckoutStarted;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\PaymentAttempt;
use App\Domain\Commerce\PaymentAttemptStatus;
use App\Domain\Commerce\Payments\CheckoutIntent;
use App\Domain\Commerce\Payments\PaymentGatewayException;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Creates (or returns) the gateway checkout for the payer's own pending order (T-P8-02, YG-GAP-PAY-001). The amount,
 * currency and line names come from the locked server order only; the caller supplies at most a method key. Idempotent: an
 * order has one open attempt, and asking again returns the same live session. Nothing here marks an order paid; only the
 * verified callback (T-P8-03) may do that. With no gateway configured it refuses before any row is written.
 */
final class StartCheckout
{
    public function __construct(private readonly AuditLogger $audit, private readonly PaymentGateway $gateway) {}

    public function __invoke(User $payer, Order $order, ?string $method = null): PaymentAttempt
    {
        if (! $this->gateway->isConfigured()) {
            $this->refuse('gateway_unavailable');
        }
        if ($payer->getAttribute('status') !== 'active' || (int) $order->payer_id !== (int) $payer->getKey()) {
            $this->refuse('order_invalid');
        }

        $methods = $this->gateway->supportedMethods();
        if ($method !== null) {
            $methods = in_array($method, $methods, true) ? [$method] : [];
        }
        if ($methods === []) {
            $this->refuse('method_unavailable');
        }

        try {
            [$attempt, $intent] = DB::transaction(fn (): array => $this->reserve($payer, $order, $methods));
        } catch (UniqueConstraintViolationException) {
            $this->refuse('checkout_in_progress'); // a concurrent request took the open slot
        }

        if ($intent === null) {
            return $attempt; // idempotent: the live session for this order
        }

        try {
            $session = $this->gateway->createCheckout($intent);
        } catch (PaymentGatewayException $failure) {
            $this->markFailed($attempt, $failure->failureCode);
            $this->refuse('checkout_failed');
        }

        return DB::transaction(function () use ($attempt, $session): PaymentAttempt {
            $locked = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->getKey());
            $locked->forceFill([
                'status' => PaymentAttemptStatus::Created,
                'provider_intention_id' => $session->providerIntentId,
                'provider_order_id' => $session->providerOrderId,
                'checkout_url' => $session->redirectUrl,
            ])->save();
            $this->record('payment.checkout_created', $locked, null, ['status' => PaymentAttemptStatus::Created->value]);
            DB::afterCommit(static fn () => event(new PaymentCheckoutStarted($locked)));

            return $locked;
        });
    }

    /**
     * @param  list<string>  $methods
     * @return array{0: PaymentAttempt, 1: CheckoutIntent|null}
     */
    private function reserve(User $payer, Order $order, array $methods): array
    {
        $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());
        if ((int) $locked->payer_id !== (int) $payer->getKey() || $locked->status !== OrderStatus::Pending || $locked->total_minor <= 0) {
            $this->refuse('order_invalid');
        }
        if (! $locked->isInActiveMarket()) {
            $this->refuse('market_unavailable');
        }

        $open = PaymentAttempt::query()->where('order_id', $locked->getKey())->whereNotNull('active_order_id')->lockForUpdate()->first();
        if ($open !== null) {
            $reusable = $open->status === PaymentAttemptStatus::Created && $open->gateway === $this->gateway->key()
                && $open->amount_minor === $locked->total_minor && $open->currency === $locked->currency && $open->expires_at->isFuture();
            if ($reusable) {
                return [$open, null];
            }
            if ($open->status === PaymentAttemptStatus::Initiated && $open->expires_at->isFuture()) {
                $this->refuse('checkout_in_progress');
            }
            // Expired, or the order total changed since (coupon): close it and start a fresh session.
            $open->forceFill(['status' => $open->expires_at->isPast() ? PaymentAttemptStatus::Expired : PaymentAttemptStatus::Cancelled, 'active_order_id' => null])->save();
            $this->record('payment.checkout_closed', $open, ['status' => PaymentAttemptStatus::Created->value], ['status' => $open->status->value]);
        }

        $sequence = (int) PaymentAttempt::query()->where('order_id', $locked->getKey())->max('sequence') + 1;
        $attempt = PaymentAttempt::query()->create([
            'order_id' => $locked->getKey(), 'gateway' => $this->gateway->key(), 'sequence' => $sequence,
            'reference' => $locked->reference.'-A'.$sequence, 'status' => PaymentAttemptStatus::Initiated,
            'active_order_id' => $locked->getKey(), 'amount_minor' => $locked->total_minor, 'currency' => $locked->currency,
            'expires_at' => now()->addMinutes(max(1, (int) config('payments.attempt_ttl_minutes', 30))),
        ]);

        return [$attempt, $this->intent($attempt, $locked, $payer, $methods)];
    }

    /** @param  list<string>  $methods */
    private function intent(PaymentAttempt $attempt, Order $order, User $payer, array $methods): CheckoutIntent
    {
        $items = [];
        $sum = 0;
        foreach ($order->items()->orderBy('id')->get() as $item) {
            $amount = $item->allocated_minor ?? ($item->unit_price_minor - $item->discount_minor);
            $items[] = ['name' => $item->title_en ?? $item->title_ar ?? 'Course', 'amount_minor' => $amount, 'quantity' => 1];
            $sum += $amount;
        }
        if ($items === [] || $sum !== $order->total_minor) {
            // The gateway needs lines that add up to the charge exactly: fall back to one line for the whole order.
            $items = [['name' => $order->reference, 'amount_minor' => $order->total_minor, 'quantity' => 1]];
        }

        $path = static fn (string $key): string => url((string) config($key));

        return new CheckoutIntent(
            $attempt->reference, $order->reference, $order->total_minor, $order->currency, $items, $methods,
            (string) $payer->getAttribute('name'), (string) $payer->getAttribute('email'),
            $path('payments.callback_path'), $path('payments.return_path').'?'.http_build_query(['order' => $order->reference]),
        );
    }

    private function markFailed(PaymentAttempt $attempt, string $code): void
    {
        DB::transaction(function () use ($attempt, $code): void {
            $locked = PaymentAttempt::query()->lockForUpdate()->findOrFail($attempt->getKey());
            $locked->forceFill(['status' => PaymentAttemptStatus::Failed, 'failure_code' => mb_substr($code, 0, 48), 'active_order_id' => null])->save();
            $this->record('payment.checkout_failed', $locked, ['status' => PaymentAttemptStatus::Initiated->value], ['status' => PaymentAttemptStatus::Failed->value, 'failure_code' => $locked->failure_code]);
        });
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>  $after
     */
    private function record(string $event, PaymentAttempt $attempt, ?array $before, array $after): void
    {
        $recorded = $this->audit->record($event, $attempt, null, $before, $after + [
            'reference' => $attempt->reference, 'gateway' => $attempt->gateway, 'amount_minor' => $attempt->amount_minor, 'currency' => $attempt->currency,
        ]);
        if ($recorded === null) {
            throw new RuntimeException('Unable to record payment audit.');
        }
    }

    private function refuse(string $key): never
    {
        throw ValidationException::withMessages(['payment' => __('payments.'.$key)]);
    }
}
