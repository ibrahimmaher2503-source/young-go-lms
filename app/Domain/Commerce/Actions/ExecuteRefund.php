<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Commerce\Contracts\PaymentGateway;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\Payments\PaymentGatewayException;
use App\Domain\Commerce\Payments\RefundIntent;
use App\Domain\Commerce\Refund;
use App\Domain\Commerce\RefundMethod;
use App\Domain\Commerce\RefundStatus;
use App\Domain\Entitlements\Actions\RevokeEntitlementsForSource;
use App\Domain\Entitlements\EntitlementSource;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Moves the money of an approved refund and records the reversal (T-P8-06). The original order totals, payment attempts, events
 * and proofs are never rewritten: the order only changes status (partially_refunded or refunded) through TransitionOrder.
 *  - Gateway refunds: the row is set to `processing` and committed first, the provider is called outside any transaction (the
 *    refund reference is the idempotency key), then the result is recorded. A provider failure marks the refund `failed` and
 *    changes nothing else; it never falls back to a manual record.
 *  - Manual refunds (transfer-proof orders, or a gateway that cannot refund) need the external reference of the money returned.
 * Access follows `config('refunds.revoke_access')`: only the entitlements this order granted are revoked, and only then does the
 * actor also need manual_entitlement.grant (checked before any money moves). Executing an executed refund is a no-op.
 */
final class ExecuteRefund
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly PaymentGateway $gateway,
        private readonly TransitionOrder $transition,
        private readonly RevokeEntitlementsForSource $revoke,
        private readonly IssueReceipt $receipts,
    ) {}

    public function __invoke(User $actor, Refund $refund, ?string $externalReference = null): Refund
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('payment.refund')) {
            throw new AuthorizationException;
        }
        $externalReference = $externalReference === null ? null : trim($externalReference);

        $reserved = DB::transaction(function () use ($actor, $refund, $externalReference): ?Refund {
            $locked = Refund::query()->lockForUpdate()->findOrFail($refund->getKey());
            if ($locked->status === RefundStatus::Executed) {
                return null;
            }
            if ($locked->status !== RefundStatus::Approved) {
                $this->refuse('refund', 'not_approved');
            }
            $order = Order::query()->lockForUpdate()->findOrFail($locked->order_id);
            $this->assertRefundable($actor, $locked, $order);

            if ($locked->method === RefundMethod::Gateway) {
                if (! $this->gateway->isConfigured() || ! $this->gateway->supportsRefunds() || $this->gateway->key() !== $locked->gateway || blank($locked->provider_transaction_id)) {
                    $this->refuse('refund', 'gateway_unavailable');
                }
                $locked->forceFill(['status' => RefundStatus::Processing->value])->save();
            } elseif ($externalReference === null || mb_strlen($externalReference) < 3 || mb_strlen($externalReference) > 64) {
                $this->refuse('external_reference', 'external_reference_invalid');
            }

            return $locked;
        });

        if ($reserved === null) {
            return Refund::query()->findOrFail($refund->getKey());
        }

        $providerRefundId = null;
        if ($reserved->method === RefundMethod::Gateway) {
            try {
                $providerRefundId = $this->gateway->refund(new RefundIntent(
                    $reserved->reference, (string) Order::query()->whereKey($reserved->order_id)->value('reference'),
                    (string) $reserved->provider_transaction_id, $reserved->amount_minor, $reserved->currency,
                ))->providerRefundId;
            } catch (PaymentGatewayException $failure) {
                $this->markFailed($actor, $reserved, $failure->failureCode);
                $this->refuse('refund', 'gateway_failed');
            }
        }

        return DB::transaction(function () use ($actor, $reserved, $providerRefundId, $externalReference): Refund {
            $locked = Refund::query()->lockForUpdate()->findOrFail($reserved->getKey());
            if ($locked->status === RefundStatus::Executed) {
                return $locked;
            }
            $order = Order::query()->lockForUpdate()->findOrFail($locked->order_id);
            $revoke = $this->assertRefundable($actor, $locked, $order);

            $locked->forceFill([
                'status' => RefundStatus::Executed->value, 'provider_refund_id' => $providerRefundId,
                'external_reference' => $providerRefundId === null ? $externalReference : null, 'failure_code' => null,
                'executed_by' => $actor->getKey(), 'executed_at' => now(),
            ])->save();

            $fully = $this->executedSum($order) >= $order->total_minor;
            ($this->transition)($order, $fully ? OrderStatus::Refunded : OrderStatus::PartiallyRefunded, $actor);

            $revoked = $revoke ? ($this->revoke)($actor, [EntitlementSource::Purchase, EntitlementSource::ManualPayment], 'order:'.$order->reference, 'refund '.$locked->reference) : 0;
            $this->receipts->__invoke($order, $locked);

            $recorded = $this->audit->record('refund.executed', $locked, (int) $actor->getKey(), ['status' => RefundStatus::Approved->value], [
                'status' => RefundStatus::Executed->value, 'order_id' => $order->getKey(), 'amount_minor' => $locked->amount_minor, 'currency' => $locked->currency,
                'method' => $locked->method->value, 'order_status' => $fully ? OrderStatus::Refunded->value : OrderStatus::PartiallyRefunded->value, 'entitlements_revoked' => $revoked,
            ]);
            if ($recorded === null) {
                throw new RuntimeException('Unable to record refund audit.');
            }

            return $locked;
        });
    }

    /** Checks the order can take this refund and returns whether access will be revoked (which also needs the grant permission). */
    private function assertRefundable(User $actor, Refund $refund, Order $order): bool
    {
        if (! in_array($order->status, [OrderStatus::Paid, OrderStatus::PartiallyRefunded], true)) {
            $this->refuse('order', 'order_not_refundable');
        }
        $revoke = match ((string) config('refunds.revoke_access', 'full')) {
            'any' => true,
            'none' => false,
            default => $this->executedSum($order) + $refund->amount_minor >= $order->total_minor,
        };
        if ($revoke && ! $actor->can('manual_entitlement.grant')) {
            throw new AuthorizationException;
        }

        return $revoke;
    }

    private function executedSum(Order $order): int
    {
        return (int) Refund::query()->where('order_id', $order->getKey())->where('status', RefundStatus::Executed->value)->sum('amount_minor');
    }

    private function markFailed(User $actor, Refund $refund, string $code): void
    {
        DB::transaction(function () use ($actor, $refund, $code): void {
            $locked = Refund::query()->lockForUpdate()->findOrFail($refund->getKey());
            $locked->forceFill(['status' => RefundStatus::Failed->value, 'failure_code' => mb_substr($code, 0, 48)])->save();

            $recorded = $this->audit->record('refund.failed', $locked, (int) $actor->getKey(), ['status' => RefundStatus::Processing->value], ['status' => RefundStatus::Failed->value, 'failure_code' => $locked->failure_code]);
            if ($recorded === null) {
                throw new RuntimeException('Unable to record refund audit.');
            }
        });
    }

    private function refuse(string $field, string $key): never
    {
        throw ValidationException::withMessages([$field => __('refunds.'.$key)]);
    }
}
