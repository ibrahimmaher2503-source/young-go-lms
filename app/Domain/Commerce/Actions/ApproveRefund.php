<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Commerce\Refund;
use App\Domain\Commerce\RefundStatus;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Finance (payment.refund) approves a requested refund. No money moves yet; ExecuteRefund does that. Approving an approved refund is a no-op. */
final class ApproveRefund
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, Refund $refund, ?string $note = null): Refund
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('payment.refund')) {
            throw new AuthorizationException;
        }
        $note = $note === null || trim($note) === '' ? null : mb_substr(trim($note), 0, 1000);

        return DB::transaction(function () use ($actor, $refund, $note): Refund {
            $locked = Refund::query()->lockForUpdate()->findOrFail($refund->getKey());
            if ($locked->status === RefundStatus::Approved) {
                return $locked;
            }
            if ($locked->status !== RefundStatus::Requested) {
                throw ValidationException::withMessages(['refund' => __('refunds.not_requested')]);
            }

            $locked->forceFill(['status' => RefundStatus::Approved->value, 'approved_by' => $actor->getKey(), 'approved_at' => now(), 'review_note' => $note])->save();

            $recorded = $this->audit->record('refund.approved', $locked, (int) $actor->getKey(), ['status' => RefundStatus::Requested->value], ['status' => RefundStatus::Approved->value], ['note' => $note]);
            if ($recorded === null) {
                throw new RuntimeException('Unable to record refund audit.');
            }

            return $locked;
        });
    }
}
