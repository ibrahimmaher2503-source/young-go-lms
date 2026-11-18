<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Queries;

use App\Domain\Commerce\Refund;
use App\Domain\Commerce\RefundStatus;

/** Counts for the finance refunds header. */
final class RefundQueue
{
    /** @return array{requested: int, approved: int, executed_minor: array<string, int>} executed totals per currency */
    public static function summary(): array
    {
        /** @var array<string, int> $executed */
        $executed = Refund::query()->where('status', RefundStatus::Executed->value)->selectRaw('currency, sum(amount_minor) as total')->groupBy('currency')
            ->pluck('total', 'currency')->map(static fn (mixed $total): int => (int) $total)->all();

        return [
            'requested' => Refund::query()->where('status', RefundStatus::Requested->value)->count(),
            'approved' => Refund::query()->where('status', RefundStatus::Approved->value)->count(),
            'executed_minor' => $executed,
        ];
    }
}
