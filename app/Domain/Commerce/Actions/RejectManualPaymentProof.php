<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Commerce\Events\ManualPaymentRejected;
use App\Domain\Commerce\ManualPaymentProof;
use App\Domain\Commerce\ManualPaymentStatus;
use App\Domain\Shared\Actions\RecordReviewDecision;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Finance rejects a pending proof with a written reason. The order stays pending and the proof's reference and order slot
 * are released so the payer can correct and resubmit. Rejecting a rejected proof is a no-op; an approved one cannot be rejected.
 */
final class RejectManualPaymentProof
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, ManualPaymentProof $proof, string $reason): ManualPaymentProof
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('payment.approve_manual') || ! $actor->can('manual_entitlement.grant')) {
            throw new AuthorizationException;
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < RecordReviewDecision::REASON_MIN || mb_strlen($reason) > RecordReviewDecision::REASON_MAX) {
            throw ValidationException::withMessages(['reason' => __('manual_payment.reason_invalid', ['min' => RecordReviewDecision::REASON_MIN, 'max' => RecordReviewDecision::REASON_MAX])]);
        }

        return DB::transaction(function () use ($actor, $proof, $reason): ManualPaymentProof {
            $locked = ManualPaymentProof::query()->lockForUpdate()->findOrFail($proof->getKey());
            if ($locked->status === ManualPaymentStatus::Rejected) {
                return $locked;
            }
            if ($locked->status !== ManualPaymentStatus::Pending) {
                throw ValidationException::withMessages(['proof' => __('manual_payment.not_pending')]);
            }

            $locked->forceFill([
                'status' => ManualPaymentStatus::Rejected->value, 'reference_key' => null, 'active_order_id' => null,
                'reviewed_by' => $actor->getKey(), 'reviewed_at' => now(), 'review_note' => $reason,
            ])->save();

            $recorded = $this->audit->record('manual_payment.rejected', $locked, (int) $actor->getKey(), ['status' => ManualPaymentStatus::Pending->value], ['status' => ManualPaymentStatus::Rejected->value], ['reason' => $reason]);
            if ($recorded === null) {
                throw new RuntimeException('Unable to record manual payment audit.');
            }

            DB::afterCommit(static fn () => event(new ManualPaymentRejected($locked)));

            return $locked;
        });
    }
}
