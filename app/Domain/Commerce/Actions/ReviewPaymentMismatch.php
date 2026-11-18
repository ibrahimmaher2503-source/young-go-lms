<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Commerce\PaymentReconciliationMismatch;
use App\Domain\Commerce\ReconciliationMismatchStatus;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Finance marks a reconciliation mismatch as reviewed (T-P8-11). This only records that a person looked at it: it never
 * touches the order, attempt or entitlements. Reviewing an already reviewed mismatch is a no-op.
 */
final class ReviewPaymentMismatch
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, PaymentReconciliationMismatch $mismatch): PaymentReconciliationMismatch
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('payment.approve_manual')) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $mismatch): PaymentReconciliationMismatch {
            $locked = PaymentReconciliationMismatch::query()->lockForUpdate()->findOrFail($mismatch->getKey());
            if ($locked->status === ReconciliationMismatchStatus::Reviewed) {
                return $locked;
            }

            $locked->forceFill(['status' => ReconciliationMismatchStatus::Reviewed->value, 'reviewed_by' => $actor->getKey(), 'reviewed_at' => now()])->save();

            $recorded = $this->audit->record(
                'payment.reconciliation_mismatch_reviewed', $locked, (int) $actor->getKey(),
                ['status' => ReconciliationMismatchStatus::Open->value], ['status' => ReconciliationMismatchStatus::Reviewed->value],
                ['type' => $locked->type->value, 'reference' => $locked->attempt_reference, 'transaction_id' => $locked->provider_transaction_id],
            );
            if ($recorded === null) {
                throw new RuntimeException('Unable to record reconciliation audit.');
            }

            return $locked;
        });
    }
}
