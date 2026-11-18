<?php

declare(strict_types=1);

namespace App\Domain\InstructorFinance\Queries;

use App\Domain\InstructorFinance\LedgerEntry;
use App\Domain\InstructorFinance\LedgerStatus;
use App\Domain\Instructors\Queries\PayoutReadiness;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/** Ledger entries finance can still put into a settlement: status `accrued` only (held, pending, approved, paid and reversed are out). */
final class EligibleLedgerEntries
{
    /** @return Builder<LedgerEntry> */
    public function query(string $currency, ?int $instructorId = null, ?Carbon $until = null): Builder
    {
        return LedgerEntry::query()->where('status', LedgerStatus::Accrued->value)->where('currency', strtoupper($currency))
            ->when($instructorId !== null, static fn (Builder $q) => $q->where('instructor_id', $instructorId))
            ->when($until !== null, static fn (Builder $q) => $q->where('earned_at', '<=', $until->copy()->endOfDay()));
    }

    /** @return list<int> */
    public function ids(string $currency, ?int $instructorId = null, ?Carbon $until = null): array
    {
        return array_map('intval', $this->query($currency, $instructorId, $until)->orderBy('id')->limit(2000)->pluck('id')->all());
    }

    /** @return list<array{instructor_id: int, name: string, entries: int, net_minor: int, payout_ready: bool}> one row per instructor, with the net of the eligible entries */
    public function summary(string $currency, ?int $instructorId = null, ?Carbon $until = null): array
    {
        $rows = $this->query($currency, $instructorId, $until)->selectRaw('instructor_id, COUNT(*) as entries, SUM(amount_minor) as net')->groupBy('instructor_id')->get();
        $users = User::query()->whereIn('id', $rows->pluck('instructor_id')->all())->get()->keyBy('id');

        $out = [];
        foreach ($rows as $row) {
            $user = $users->get((int) $row->getAttribute('instructor_id'));
            $out[] = [
                'instructor_id' => (int) $row->getAttribute('instructor_id'), 'name' => $user instanceof User ? (string) $user->getAttribute('name') : '',
                'entries' => (int) $row->getAttribute('entries'), 'net_minor' => (int) $row->getAttribute('net'),
                'payout_ready' => $user instanceof User && PayoutReadiness::isReady($user),
            ];
        }

        return $out;
    }
}
