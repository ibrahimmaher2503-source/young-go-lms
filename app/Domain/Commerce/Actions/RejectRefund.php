<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Commerce\Refund;
use App\Domain\Commerce\RefundStatus;
use App\Domain\Shared\Actions\RecordReviewDecision;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Finance (payment.refund) rejects a requested or approved (not yet executed) refund with a written reason. Rejecting a rejected refund is a no-op; it frees the refundable balance. */
final class RejectRefund
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, Refund $refund, string $reason): Refund
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('payment.refund')) {
            throw new AuthorizationException;
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < RecordReviewDecision::REASON_MIN || mb_strlen($reason) > RecordReviewDecision::REASON_MAX) {
            throw ValidationException::withMessages(['reason' => __('refunds.reason_invalid', ['min' => RecordReviewDecision::REASON_MIN, 'max' => RecordReviewDecision::REASON_MAX])]);
        }

        return DB::transaction(function () use ($actor, $refund, $reason): Refund {
            $locked = Refund::query()->lockForUpdate()->findOrFail($refund->getKey());
            if ($locked->status === RefundStatus::Rejected) {
                return $locked;
            }
            if ($locked->status !== RefundStatus::Requested && $locked->status !== RefundStatus::Approved) {
                throw ValidationException::withMessages(['refund' => __('refunds.not_rejectable')]);
            }

            $before = $locked->status->value;
            $locked->forceFill(['status' => RefundStatus::Rejected->value, 'rejected_by' => $actor->getKey(), 'rejected_at' => now(), 'review_note' => $reason])->save();

            $recorded = $this->audit->record('refund.rejected', $locked, (int) $actor->getKey(), ['status' => $before], ['status' => RefundStatus::Rejected->value], ['reason' => $reason]);
            if ($recorded === null) {
                throw new RuntimeException('Unable to record refund audit.');
            }

            return $locked;
        });
    }
}
