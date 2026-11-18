<?php

declare(strict_types=1);

namespace App\Domain\InstructorFinance\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\InstructorFinance\LedgerEntry;
use App\Domain\InstructorFinance\LedgerStatus;
use App\Domain\InstructorFinance\PayoutMethod;
use App\Domain\InstructorFinance\Settlement;
use App\Domain\InstructorFinance\SettlementEntry;
use App\Domain\InstructorFinance\SettlementItem;
use App\Domain\InstructorFinance\SettlementNotices;
use App\Domain\InstructorFinance\SettlementStatus;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Records that finance PAID one instructor line of an approved batch outside the system (method, reference 3..64 chars, date not
 * in the future). The line's entries move `approved` -> `paid`; when every line is paid the batch becomes `paid`. This never moves
 * money. Idempotent: repeating the call with the same reference returns the paid line; a different reference is refused.
 * Needs `settlement.manage`. Audited as `settlement.paid`.
 */
final class RecordSettlementPayout
{
    public function __construct(private readonly AuditLogger $audit, private readonly SettlementNotices $notices) {}

    public function __invoke(User $actor, SettlementItem $item, PayoutMethod $method, string $reference, string $paidOn): SettlementItem
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('settlement.manage')) {
            throw new AuthorizationException;
        }

        $reference = trim($reference);
        if (mb_strlen($reference) < 3 || mb_strlen($reference) > 64) {
            $this->refuse('reference', 'reference_invalid');
        }
        try {
            $date = Carbon::createFromFormat('!Y-m-d', substr(trim($paidOn), 0, 10));
        } catch (Throwable) {
            $date = null;
        }
        if ($date === null || $date->isFuture()) {
            $this->refuse('paid_on', 'paid_on_invalid');
        }

        $paidSettlement = null;
        $result = DB::transaction(function () use ($actor, $item, $method, $reference, $date, &$paidSettlement): SettlementItem {
            $settlement = Settlement::query()->whereKey($item->settlement_id)->lockForUpdate()->firstOrFail();
            $line = SettlementItem::query()->whereKey($item->getKey())->lockForUpdate()->firstOrFail();

            if ($line->isPaid()) {
                if ($line->payout_reference === $reference) {
                    return $line;
                }
                $this->refuse('reference', 'already_paid');
            }
            if ($settlement->status !== SettlementStatus::Approved) {
                $this->refuse('settlement', 'state_invalid');
            }

            $ids = SettlementEntry::query()->where('item_id', $line->getKey())->pluck('ledger_entry_id')->all();
            $moved = LedgerEntry::query()->whereIn('id', $ids)->where('status', LedgerStatus::Approved->value)->update(['status' => LedgerStatus::Paid->value]);
            if ($moved !== count($ids)) {
                $this->refuse('settlement', 'state_invalid');
            }

            $line->forceFill(['payout_method' => $method->value, 'payout_reference' => $reference, 'paid_on' => $date->toDateString(), 'paid_at' => now(), 'paid_by' => $actor->getKey()])->save();

            if (! $settlement->items()->whereNull('paid_at')->exists()) {
                $settlement->forceFill(['status' => SettlementStatus::Paid->value, 'paid_at' => now()])->save();
            }

            $recorded = $this->audit->record('settlement.paid', $settlement, (int) $actor->getKey(), null, [
                'item_id' => $line->getKey(), 'instructor_id' => $line->instructor_id, 'amount_minor' => $line->amount_minor, 'currency' => $settlement->currency,
                'method' => $method->value, 'reference' => $reference, 'paid_on' => $date->toDateString(), 'settlement_status' => $settlement->status->value,
            ]);
            if ($recorded === null) {
                throw new RuntimeException('Unable to record settlement audit.');
            }
            $paidSettlement = $settlement;

            return $line;
        });

        if ($paidSettlement instanceof Settlement) {
            $this->notices->payoutRecorded($result, $paidSettlement);
        }

        return $result;
    }

    private function refuse(string $field, string $key): never
    {
        throw ValidationException::withMessages([$field => __('settlements.'.$key)]);
    }
}
