<?php

declare(strict_types=1);

namespace App\Domain\InstructorFinance\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\InstructorFinance\LedgerEntry;
use App\Domain\InstructorFinance\LedgerStatus;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Flags ledger entries as `held` (excluded from settlement) with a mandatory reason (5..500 chars), or releases them back to
 * `accrued`. Only status changes. Already-held (or already-released) entries are skipped, so repeating is safe; an entry that is
 * pending, approved or paid cannot be held. Needs `settlement.manage`. Audited per entry as `ledger.entry_held` / `ledger.entry_released`.
 * Returns the number of entries changed.
 */
final class HoldLedgerEntries
{
    private const int MAX_ENTRIES = 500;

    public function __construct(private readonly AuditLogger $audit) {}

    /** @param  list<int>  $entryIds */
    public function hold(User $actor, array $entryIds, string $reason): int
    {
        return $this->move($actor, $entryIds, $reason, LedgerStatus::Accrued, LedgerStatus::Held, 'ledger.entry_held');
    }

    /** @param  list<int>  $entryIds */
    public function release(User $actor, array $entryIds, string $reason): int
    {
        return $this->move($actor, $entryIds, $reason, LedgerStatus::Held, LedgerStatus::Accrued, 'ledger.entry_released');
    }

    /** @param  list<int>  $entryIds */
    private function move(User $actor, array $entryIds, string $reason, LedgerStatus $from, LedgerStatus $to, string $event): int
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('settlement.manage')) {
            throw new AuthorizationException;
        }
        $reason = trim($reason);
        $ids = array_values(array_unique(array_map('intval', $entryIds)));
        if ($ids === [] || count($ids) > self::MAX_ENTRIES) {
            throw ValidationException::withMessages(['entries' => __('settlements.entries_invalid')]);
        }
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => __('settlements.reason_invalid')]);
        }

        return DB::transaction(function () use ($actor, $ids, $reason, $from, $to, $event): int {
            $entries = LedgerEntry::query()->whereIn('id', $ids)->lockForUpdate()->get();
            if ($entries->count() !== count($ids)) {
                throw ValidationException::withMessages(['entries' => __('settlements.entries_invalid')]);
            }
            if ($entries->contains(static fn (LedgerEntry $e): bool => $e->status !== $from && $e->status !== $to)) {
                throw ValidationException::withMessages(['entries' => __('settlements.entries_not_eligible')]);
            }

            $changed = 0;
            foreach ($entries->where('status', $from) as $entry) {
                $entry->status = $to;
                $entry->save();
                $changed++;
                if ($this->audit->record($event, $entry, (int) $actor->getKey(), ['status' => $from->value], ['status' => $to->value, 'instructor_id' => $entry->instructor_id, 'amount_minor' => $entry->amount_minor], ['reason' => $reason]) === null) {
                    throw new RuntimeException('Unable to record ledger audit.');
                }
            }

            return $changed;
        });
    }
}
