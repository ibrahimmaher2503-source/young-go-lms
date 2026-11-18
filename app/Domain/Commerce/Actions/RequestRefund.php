<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\PaymentEvent;
use App\Domain\Commerce\PaymentEventOutcome;
use App\Domain\Commerce\Refund;
use App\Domain\Commerce\RefundMethod;
use App\Domain\Commerce\RefundStatus;
use App\Domain\Shared\Actions\RecordReviewDecision;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Staff (payment.refund) ask to refund part or all of a paid order. The order is locked and the refundable balance is the order
 * total minus every refund that is not rejected or failed, so concurrent requests can never over-refund. How the money goes back
 * is fixed here from how the order was paid: a verified gateway payment is refunded through that gateway, a transfer-proof
 * (manual) payment is refunded manually. Nothing about the order, its attempts or its payment events is changed.
 */
final class RequestRefund
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, Order $order, int $amountMinor, string $reason): Refund
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('payment.refund')) {
            throw new AuthorizationException;
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < RecordReviewDecision::REASON_MIN || mb_strlen($reason) > RecordReviewDecision::REASON_MAX) {
            throw ValidationException::withMessages(['reason' => __('refunds.reason_invalid', ['min' => RecordReviewDecision::REASON_MIN, 'max' => RecordReviewDecision::REASON_MAX])]);
        }
        if ($amountMinor <= 0) {
            throw ValidationException::withMessages(['amount' => __('refunds.amount_invalid')]);
        }

        return DB::transaction(function () use ($actor, $order, $amountMinor, $reason): Refund {
            $locked = Order::query()->lockForUpdate()->findOrFail($order->getKey());
            if (! in_array($locked->status, [OrderStatus::Paid, OrderStatus::PartiallyRefunded], true)) {
                throw ValidationException::withMessages(['order' => __('refunds.order_not_refundable')]);
            }

            $held = (int) Refund::query()->where('order_id', $locked->getKey())->whereNotIn('status', [RefundStatus::Rejected->value, RefundStatus::Failed->value])->sum('amount_minor');
            if ($held + $amountMinor > $locked->total_minor) {
                throw ValidationException::withMessages(['amount' => __('refunds.amount_exceeds')]);
            }

            $payment = PaymentEvent::query()->where('order_id', $locked->getKey())->where('outcome', PaymentEventOutcome::Fulfilled->value)->orderBy('id')->first();
            $method = $payment === null ? RefundMethod::Manual : RefundMethod::Gateway;
            $sequence = Refund::query()->where('order_id', $locked->getKey())->count() + 1;

            $refund = Refund::query()->create([
                'reference' => $locked->reference.'-R'.$sequence, 'order_id' => $locked->getKey(), 'amount_minor' => $amountMinor, 'currency' => $locked->currency,
                'reason' => $reason, 'method' => $method->value, 'status' => RefundStatus::Requested->value,
                'gateway' => $payment?->gateway, 'provider_transaction_id' => $payment?->provider_event_id, 'requested_by' => $actor->getKey(),
            ]);

            $recorded = $this->audit->record('refund.requested', $refund, (int) $actor->getKey(), null, [
                'order_id' => $locked->getKey(), 'amount_minor' => $amountMinor, 'currency' => $locked->currency, 'method' => $method->value,
            ], ['reason' => $reason]);
            if ($recorded === null) {
                throw new RuntimeException('Unable to record refund audit.');
            }

            return $refund;
        });
    }
}
