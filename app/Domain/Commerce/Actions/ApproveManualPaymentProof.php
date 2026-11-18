<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Commerce\Events\ManualPaymentApproved;
use App\Domain\Commerce\ManualPaymentProof;
use App\Domain\Commerce\ManualPaymentStatus;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Entitlements\EntitlementSource;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Finance approves a transfer proof: the order moves to paid through TransitionOrder and every course line is granted with
 * source manual_payment, all in one transaction. Approving an approved proof is a no-op (no second audit, grant or event).
 * An amount that differs from the order total needs a written note (the acknowledgement). The approver needs
 * payment.approve_manual and manual_entitlement.grant (GrantEntitlement rule for manual sources). Access (courses, bundles,
 * subscription periods) is granted through GrantOrderEntitlements, the same call the Paymob fulfilment makes.
 */
final class ApproveManualPaymentProof
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly TransitionOrder $transition,
        private readonly GrantOrderEntitlements $grants,
    ) {}

    public function __invoke(User $actor, ManualPaymentProof $proof, ?string $note = null): ManualPaymentProof
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('payment.approve_manual') || ! $actor->can('manual_entitlement.grant')) {
            throw new AuthorizationException;
        }
        $note = $note === null || trim($note) === '' ? null : trim($note);
        if ($note !== null && mb_strlen($note) > 1000) {
            $this->refuse('note', 'note_invalid');
        }

        $approved = DB::transaction(function () use ($actor, $proof, $note): ?ManualPaymentProof {
            $locked = ManualPaymentProof::query()->lockForUpdate()->findOrFail($proof->getKey());
            if ($locked->status === ManualPaymentStatus::Approved) {
                return null;
            }
            if ($locked->status !== ManualPaymentStatus::Pending) {
                $this->refuse('proof', 'not_pending');
            }

            $order = Order::query()->lockForUpdate()->findOrFail($locked->order_id);
            if ($order->status !== OrderStatus::Pending) {
                $this->refuse('proof', 'order_not_payable');
            }
            $mismatch = $order->total_minor !== $locked->amount_minor || $order->currency !== $locked->currency;
            if ($mismatch && $note === null) {
                $this->refuse('note', 'amount_mismatch');
            }

            $locked->forceFill([
                'status' => ManualPaymentStatus::Approved->value, 'active_order_id' => null,
                'reviewed_by' => $actor->getKey(), 'reviewed_at' => now(), 'review_note' => $note,
            ])->save();

            ($this->transition)($order, OrderStatus::Paid, $actor);
            ($this->grants)($order, EntitlementSource::ManualPayment, $actor);

            $recorded = $this->audit->record('manual_payment.approved', $locked, (int) $actor->getKey(), ['status' => ManualPaymentStatus::Pending->value], [
                'status' => ManualPaymentStatus::Approved->value, 'order_id' => $order->getKey(), 'amount_minor' => $locked->amount_minor,
                'order_total_minor' => $order->total_minor, 'amount_mismatch' => $mismatch,
            ], ['note' => $note]);
            if ($recorded === null) {
                throw new RuntimeException('Unable to record manual payment audit.');
            }

            DB::afterCommit(static fn () => event(new ManualPaymentApproved($locked)));

            return $locked;
        });

        return $approved ?? ManualPaymentProof::query()->findOrFail($proof->getKey());
    }

    private function refuse(string $field, string $key): never
    {
        throw ValidationException::withMessages([$field => __('manual_payment.'.$key)]);
    }
}
