<?php

declare(strict_types=1);

namespace App\Domain\InstructorFinance\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\InstructorFinance\LedgerEntry;
use App\Domain\InstructorFinance\LedgerStatus;
use App\Domain\InstructorFinance\Settlement;
use App\Domain\InstructorFinance\SettlementEntry;
use App\Domain\InstructorFinance\SettlementStatus;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Cancels a draft or approved batch before any payout was recorded; its entries return to `accrued`. Reason 5..500 chars. Idempotent. Needs `settlement.manage`. Audited as `settlement.cancelled`. */
final class CancelSettlement
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, Settlement $settlement, string $reason): Settlement
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('settlement.manage')) {
            throw new AuthorizationException;
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => __('settlements.reason_invalid')]);
        }

        return DB::transaction(function () use ($actor, $settlement, $reason): Settlement {
            $locked = Settlement::query()->whereKey($settlement->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status === SettlementStatus::Cancelled) {
                return $locked;
            }
            if (! in_array($locked->status, [SettlementStatus::Draft, SettlementStatus::Approved], true) || $locked->items()->whereNotNull('paid_at')->exists()) {
                throw ValidationException::withMessages(['settlement' => __('settlements.state_invalid')]);
            }

            $before = $locked->status->value;
            $ids = SettlementEntry::query()->whereIn('item_id', $locked->items()->select('id'))->pluck('ledger_entry_id')->all();
            $moved = LedgerEntry::query()->whereIn('id', $ids)->whereIn('status', [LedgerStatus::Pending->value, LedgerStatus::Approved->value])->update(['status' => LedgerStatus::Accrued->value]);
            if ($moved !== count($ids)) {
                throw ValidationException::withMessages(['settlement' => __('settlements.state_invalid')]);
            }

            $locked->forceFill(['status' => SettlementStatus::Cancelled->value, 'cancelled_by' => $actor->getKey(), 'cancelled_at' => now(), 'cancel_reason' => $reason])->save();

            if ($this->audit->record('settlement.cancelled', $locked, (int) $actor->getKey(), ['status' => $before], ['status' => SettlementStatus::Cancelled->value, 'entries' => $moved], ['reason' => $reason]) === null) {
                throw new RuntimeException('Unable to record settlement audit.');
            }

            return $locked;
        });
    }
}
