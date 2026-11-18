<?php

declare(strict_types=1);

namespace App\Domain\InstructorFinance\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\InstructorFinance\LedgerEntry;
use App\Domain\InstructorFinance\LedgerStatus;
use App\Domain\InstructorFinance\Settlement;
use App\Domain\InstructorFinance\SettlementEntry;
use App\Domain\InstructorFinance\SettlementItem;
use App\Domain\InstructorFinance\SettlementStatus;
use App\Domain\Instructors\Queries\PayoutReadiness;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Finance selects ledger entries (all `accrued`, one currency) and groups them into a draft settlement batch (T-P8-10). Per
 * instructor the selected entries are netted: a zero or negative net, or an instructor without a payout profile (YG-GAP-INS-003),
 * or a net below the minimum payout of the currency (O-012, `commissions.settlement.minimum_payout_minor`), is NOT settled; their entries stay `accrued` and the negative balance carries into the next batch (recorded in
 * `carried_forward`). Settled entries move to `pending`; amounts are never touched. No money moves: settlement is a record.
 * `$key` makes the call idempotent. Needs `settlement.manage`. Audited as `settlement.created`.
 */
final class CreateSettlementBatch
{
    private const int MAX_ENTRIES = 2000;

    public function __construct(private readonly AuditLogger $audit) {}

    /** @param  list<int>  $entryIds */
    public function __invoke(User $actor, string $currency, array $entryIds, ?string $note = null, ?string $key = null): Settlement
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('settlement.manage')) {
            throw new AuthorizationException;
        }

        $requestKey = $key === null || trim($key) === '' ? null : 'batch:'.mb_substr(trim($key), 0, 56);
        if ($requestKey !== null) {
            $existing = Settlement::query()->where('request_key', $requestKey)->first();
            if ($existing !== null) {
                return $existing;
            }
        }

        $currency = strtoupper(trim($currency));
        $ids = array_values(array_unique(array_map('intval', $entryIds)));
        $note = $note === null || trim($note) === '' ? null : trim($note);
        if ($ids === [] || count($ids) > self::MAX_ENTRIES) {
            $this->refuse('entries', 'entries_invalid');
        }
        if ($note !== null && mb_strlen($note) > 255) {
            $this->refuse('note', 'note_invalid');
        }

        try {
            return DB::transaction(function () use ($actor, $currency, $ids, $note, $requestKey): Settlement {
                $entries = LedgerEntry::query()->whereIn('id', $ids)->orderBy('id')->get();
                if ($entries->count() !== count($ids) || $entries->contains(static fn (LedgerEntry $e): bool => $e->currency !== $currency)) {
                    $this->refuse('entries', 'entries_invalid');
                }
                if ($entries->contains(static fn (LedgerEntry $e): bool => $e->status !== LedgerStatus::Accrued)) {
                    $this->refuse('entries', 'entries_not_eligible');
                }

                $included = [];
                $carried = [];
                $minimum = max(0, (int) config('commissions.settlement.minimum_payout_minor.'.$currency, 0));
                foreach ($entries->groupBy('instructor_id') as $instructorId => $group) {
                    $net = (int) $group->sum('amount_minor');
                    $instructor = User::query()->find((int) $instructorId);
                    if ($net <= 0) {
                        $carried[] = ['instructor_id' => (int) $instructorId, 'reason' => 'not_positive', 'amount_minor' => $net, 'entries' => $group->count()];
                    } elseif ($net < $minimum) {
                        $carried[] = ['instructor_id' => (int) $instructorId, 'reason' => 'below_minimum', 'amount_minor' => $net, 'entries' => $group->count()];
                    } elseif (! $instructor instanceof User || ! PayoutReadiness::isReady($instructor)) {
                        $carried[] = ['instructor_id' => (int) $instructorId, 'reason' => 'no_payout_profile', 'amount_minor' => $net, 'entries' => $group->count()];
                    } else {
                        $included[(int) $instructorId] = ['net' => $net, 'ids' => $group->pluck('id')->map(static fn ($id): int => (int) $id)->all()];
                    }
                }
                if ($included === []) {
                    $this->refuse('entries', 'nothing_to_settle');
                }

                $entryCount = array_sum(array_map(static fn (array $i): int => count($i['ids']), $included));
                $settlement = Settlement::query()->create([
                    'reference' => 'SET-'.now()->format('Ym').'-'.Str::upper(Str::random(6)), 'request_key' => $requestKey, 'currency' => $currency,
                    'status' => SettlementStatus::Draft->value, 'note' => $note, 'instructor_count' => count($included), 'entry_count' => $entryCount,
                    'total_minor' => array_sum(array_map(static fn (array $i): int => $i['net'], $included)), 'carried_forward' => $carried === [] ? null : $carried,
                    'created_by' => $actor->getKey(),
                ]);

                foreach ($included as $instructorId => $info) {
                    $item = SettlementItem::query()->create(['settlement_id' => $settlement->getKey(), 'instructor_id' => $instructorId, 'entry_count' => count($info['ids']), 'amount_minor' => $info['net']]);
                    foreach ($info['ids'] as $entryId) {
                        SettlementEntry::query()->create(['item_id' => $item->getKey(), 'ledger_entry_id' => $entryId]);
                    }
                    // Status only; the guard in the WHERE makes a concurrent batch lose instead of double-settling.
                    $moved = LedgerEntry::query()->whereIn('id', $info['ids'])->where('status', LedgerStatus::Accrued->value)->update(['status' => LedgerStatus::Pending->value]);
                    if ($moved !== count($info['ids'])) {
                        throw ValidationException::withMessages(['entries' => __('settlements.entries_not_eligible')]);
                    }
                }

                $recorded = $this->audit->record('settlement.created', $settlement, (int) $actor->getKey(), null, [
                    'reference' => $settlement->reference, 'currency' => $currency, 'instructors' => count($included), 'entries' => $entryCount, 'total_minor' => $settlement->total_minor,
                ], ['carried_forward' => $carried]);
                if ($recorded === null) {
                    throw new RuntimeException('Unable to record settlement audit.');
                }

                return $settlement;
            });
        } catch (UniqueConstraintViolationException) {
            if ($requestKey !== null) {
                return Settlement::query()->where('request_key', $requestKey)->firstOrFail();
            }

            throw ValidationException::withMessages(['entries' => __('settlements.entries_not_eligible')]);
        }
    }

    private function refuse(string $field, string $key): never
    {
        throw ValidationException::withMessages([$field => __('settlements.'.$key)]);
    }
}
