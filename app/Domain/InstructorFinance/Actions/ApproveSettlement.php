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

/** Finance review step: a draft batch becomes approved and its entries move `pending` -> `approved`. Idempotent (an approved batch is returned as is). Needs `settlement.manage`. Audited as `settlement.approved`. */
final class ApproveSettlement
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, Settlement $settlement): Settlement
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('settlement.manage')) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($actor, $settlement): Settlement {
            $locked = Settlement::query()->whereKey($settlement->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status === SettlementStatus::Approved) {
                return $locked;
            }
            if ($locked->status !== SettlementStatus::Draft) {
                throw ValidationException::withMessages(['settlement' => __('settlements.state_invalid')]);
            }

            $ids = SettlementEntry::query()->whereIn('item_id', $locked->items()->select('id'))->pluck('ledger_entry_id')->all();
            $moved = LedgerEntry::query()->whereIn('id', $ids)->where('status', LedgerStatus::Pending->value)->update(['status' => LedgerStatus::Approved->value]);
            if ($moved !== count($ids)) {
                throw ValidationException::withMessages(['settlement' => __('settlements.state_invalid')]);
            }

            $locked->forceFill(['status' => SettlementStatus::Approved->value, 'approved_by' => $actor->getKey(), 'approved_at' => now()])->save();

            if ($this->audit->record('settlement.approved', $locked, (int) $actor->getKey(), ['status' => SettlementStatus::Draft->value], ['status' => SettlementStatus::Approved->value, 'entries' => $moved]) === null) {
                throw new RuntimeException('Unable to record settlement audit.');
            }

            return $locked;
        });
    }
}
