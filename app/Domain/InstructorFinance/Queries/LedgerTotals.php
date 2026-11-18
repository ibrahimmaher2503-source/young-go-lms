<?php

declare(strict_types=1);

namespace App\Domain\InstructorFinance\Queries;

use App\Domain\InstructorFinance\LedgerEntry;
use App\Domain\InstructorFinance\LedgerEntryType;

/** Ledger sums by currency, for the finance summary strip and the instructor's own earnings page. */
final class LedgerTotals
{
    /** @return array<string, array{earnings: int, reversals: int, adjustments: int, net: int}> keyed by currency */
    public function forInstructor(?int $instructorId = null): array
    {
        $rows = LedgerEntry::query()
            ->when($instructorId !== null, static fn ($q) => $q->where('instructor_id', $instructorId))
            ->selectRaw('currency, type, SUM(amount_minor) as total')
            ->groupBy('currency', 'type')
            ->get();

        $totals = [];
        foreach ($rows as $row) {
            $currency = (string) $row->getAttribute('currency');
            $totals[$currency] ??= ['earnings' => 0, 'reversals' => 0, 'adjustments' => 0, 'net' => 0];
            $sum = (int) $row->getAttribute('total');
            $key = match ($row->type) {
                LedgerEntryType::Earning => 'earnings',
                LedgerEntryType::Reversal => 'reversals',
                LedgerEntryType::Adjustment => 'adjustments',
            };
            $totals[$currency][$key] += $sum;
            $totals[$currency]['net'] += $sum;
        }

        return $totals;
    }
}
