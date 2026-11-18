<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Commerce\Contracts\PaymentGateway;
use App\Domain\Commerce\Events\PaymentReconciliationCompleted;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\PaymentAttempt;
use App\Domain\Commerce\PaymentAttemptStatus;
use App\Domain\Commerce\PaymentReconciliation;
use App\Domain\Commerce\PaymentReconciliationMismatch;
use App\Domain\Commerce\Payments\PaymentGatewayException;
use App\Domain\Commerce\Payments\VerifiedPaymentEvent;
use App\Domain\Commerce\ReconciliationMismatchType;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Daily reconciliation of our payment attempts and orders against the gateway's own transaction listing (T-P8-11,
 * YG-GAP-PAY-003). It only reports: it records a run and the mismatches it finds, and never changes an order, attempt or
 * entitlement. Findings are idempotent per (gateway, mismatch_key), so a re-run neither duplicates nor reopens one that
 * finance already reviewed. A gateway failure is thrown before anything is written.
 */
final class ReconcilePayments
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PaymentGateway $gateway,
    ) {}

    /** @throws PaymentGatewayException */
    public function __invoke(CarbonImmutable $day): PaymentReconciliation
    {
        $from = $day->startOfDay();
        $to = $day->endOfDay();
        $gatewayKey = $this->gateway->key();

        $transactions = $this->gateway->listTransactions($from, $to);

        $references = [];
        foreach ($transactions as $transaction) {
            if ($transaction->attemptReference !== null && $transaction->attemptReference !== '') {
                $references[$transaction->attemptReference] = true;
            }
        }
        $attempts = $this->attempts($gatewayKey, array_keys($references));
        $orders = $this->orders($attempts);

        [$findings, $matched, $paidReferences] = $this->compare($transactions, $attempts, $orders);

        // Payments we recorded as succeeded in the window that the gateway listing does not show at all.
        $succeededLocally = PaymentAttempt::query()->where('gateway', $gatewayKey)->where('status', PaymentAttemptStatus::Succeeded->value)
            ->whereBetween('updated_at', [$from, $to])->get();
        foreach ($succeededLocally as $attempt) {
            if (! isset($paidReferences[$attempt->reference]) && ! isset($references[$attempt->reference])) {
                $findings[] = $this->finding(ReconciliationMismatchType::MissingAtGateway, $attempt->reference, null, $attempt, null, 'not_in_gateway_listing');
            }
        }
        $localAttempts = PaymentAttempt::query()->where('gateway', $gatewayKey)->whereBetween('created_at', [$from, $to])->count();

        $run = DB::transaction(function () use ($gatewayKey, $day, $from, $to, $transactions, $localAttempts, $matched, $findings): PaymentReconciliation {
            $run = PaymentReconciliation::query()->updateOrCreate(
                ['gateway' => $gatewayKey, 'run_date' => $day->toDateString()],
                [
                    'window_start' => $from, 'window_end' => $to, 'transactions_seen' => count($transactions), 'local_attempts' => $localAttempts,
                    'matched' => $matched, 'mismatched' => count($findings), 'completed_at' => now(),
                ],
            );

            foreach ($findings as $finding) {
                $mismatch = PaymentReconciliationMismatch::query()->firstOrCreate(
                    ['gateway' => $gatewayKey, 'mismatch_key' => $finding['mismatch_key']],
                    ['payment_reconciliation_id' => $run->getKey()] + $finding['attributes'],
                );
                if ($mismatch->wasRecentlyCreated) {
                    $this->record('payment.reconciliation_mismatch_found', $mismatch, null, [
                        'type' => $mismatch->type->value, 'reason' => $mismatch->reason, 'gateway' => $gatewayKey,
                        'reference' => $mismatch->attempt_reference, 'transaction_id' => $mismatch->provider_transaction_id,
                        'gateway_amount_minor' => $mismatch->gateway_amount_minor, 'local_amount_minor' => $mismatch->local_amount_minor,
                    ]);
                }
            }

            $this->record('payment.reconciliation_completed', $run, null, [
                'gateway' => $gatewayKey, 'run_date' => $day->toDateString(), 'transactions_seen' => $run->transactions_seen,
                'matched' => $run->matched, 'mismatched' => $run->mismatched,
            ]);

            return $run;
        });

        event(new PaymentReconciliationCompleted($run));

        return $run;
    }

    /**
     * @param  list<VerifiedPaymentEvent>  $transactions
     * @param  array<string, PaymentAttempt>  $attempts
     * @param  array<int, Order>  $orders
     * @return array{0: list<array{mismatch_key: string, attributes: array<string, mixed>}>, 1: int, 2: array<string, true>}
     */
    private function compare(array $transactions, array $attempts, array $orders): array
    {
        $findings = [];
        $matched = 0;
        $paid = [];
        $failedByReference = [];

        foreach ($transactions as $transaction) {
            $attempt = $transaction->attemptReference === null ? null : ($attempts[$transaction->attemptReference] ?? null);
            if ($transaction->pending) {
                continue; // not settled yet: tomorrow's run sees the final state
            }
            if ($attempt === null) {
                $findings[] = $this->finding(ReconciliationMismatchType::UnmatchedTransaction, $transaction->eventId, $transaction, null, null, 'no_attempt_for_reference');

                continue;
            }

            $order = $orders[$attempt->order_id] ?? null;
            if ($transaction->refunded || $transaction->voided) {
                $findings[] = $this->finding(ReconciliationMismatchType::RefundedAtGateway, $transaction->eventId, $transaction, $attempt, $order, $transaction->refunded ? 'refunded' : 'voided');
            } elseif ($transaction->success) {
                $paid[$attempt->reference] = true;
                $reason = match (true) {
                    $transaction->amountMinor !== $attempt->amount_minor || strtoupper($transaction->currency) !== strtoupper($attempt->currency) => 'amount_or_currency_differs',
                    $attempt->status !== PaymentAttemptStatus::Succeeded || $order === null || $order->status !== OrderStatus::Paid => 'paid_at_gateway_not_locally',
                    default => null,
                };
                if ($reason === null) {
                    $matched++;
                } else {
                    $type = $reason === 'amount_or_currency_differs' ? ReconciliationMismatchType::AmountMismatch : ReconciliationMismatchType::StatusMismatch;
                    $findings[] = $this->finding($type, $transaction->eventId, $transaction, $attempt, $order, $reason);
                }
            } else {
                $failedByReference[$attempt->reference][] = $transaction;
            }
        }

        // A declined try is normal beside a later success on the same attempt; it is a finding only when we show paid and the gateway never did.
        foreach ($failedByReference as $reference => $failed) {
            $attempt = $attempts[$reference];
            if (isset($paid[$reference]) || $attempt->status !== PaymentAttemptStatus::Succeeded) {
                $matched += count($failed);

                continue;
            }
            foreach ($failed as $transaction) {
                $findings[] = $this->finding(ReconciliationMismatchType::StatusMismatch, $transaction->eventId, $transaction, $attempt, $orders[$attempt->order_id] ?? null, 'failed_at_gateway_paid_locally');
            }
        }

        return [$findings, $matched, $paid];
    }

    /** @return array{mismatch_key: string, attributes: array<string, mixed>} */
    private function finding(ReconciliationMismatchType $type, string $subjectId, ?VerifiedPaymentEvent $transaction, ?PaymentAttempt $attempt, ?Order $order, string $reason): array
    {
        $reference = $attempt !== null ? $attempt->reference : $transaction?->attemptReference;
        $currency = $transaction !== null ? $transaction->currency : ($attempt !== null ? $attempt->currency : '');

        return [
            'mismatch_key' => mb_substr($type->value.':'.$subjectId, 0, 140),
            'attributes' => [
                'gateway' => $this->gateway->key(),
                'type' => $type->value,
                'provider_transaction_id' => $transaction === null ? null : mb_substr($transaction->eventId, 0, 96),
                'attempt_reference' => $reference === null ? null : mb_substr($reference, 0, 48),
                'payment_attempt_id' => $attempt?->getKey(),
                'order_id' => $order?->getKey() ?? $attempt?->order_id,
                'gateway_amount_minor' => $transaction === null ? null : max(0, $transaction->amountMinor),
                'local_amount_minor' => $attempt?->amount_minor,
                'currency' => mb_substr($currency, 0, 3) ?: null,
                'reason' => $reason,
            ],
        ];
    }

    /**
     * @param  list<string>  $references
     * @return array<string, PaymentAttempt>
     */
    private function attempts(string $gateway, array $references): array
    {
        $found = [];
        foreach (array_chunk($references, 400) as $chunk) {
            foreach (PaymentAttempt::query()->where('gateway', $gateway)->whereIn('reference', $chunk)->get() as $attempt) {
                $found[$attempt->reference] = $attempt;
            }
        }

        return $found;
    }

    /**
     * @param  array<string, PaymentAttempt>  $attempts
     * @return array<int, Order>
     */
    private function orders(array $attempts): array
    {
        $found = [];
        $ids = array_values(array_unique(array_map(static fn (PaymentAttempt $attempt): int => $attempt->order_id, $attempts)));
        foreach (array_chunk($ids, 400) as $chunk) {
            foreach (Order::query()->whereIn('id', $chunk)->get() as $order) {
                $found[$order->id] = $order;
            }
        }

        return $found;
    }

    /** @param  array<string, mixed>  $after */
    private function record(string $event, PaymentReconciliation|PaymentReconciliationMismatch $subject, ?int $actorId, array $after): void
    {
        if ($this->audit->record($event, $subject, $actorId, null, $after) === null) {
            throw new RuntimeException('Unable to record reconciliation audit.');
        }
    }
}
