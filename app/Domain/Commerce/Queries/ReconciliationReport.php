<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Queries;

use App\Domain\Commerce\PaymentReconciliation;
use App\Domain\Commerce\PaymentReconciliationMismatch;
use App\Domain\Commerce\ReconciliationMismatchStatus;
use Illuminate\Database\Eloquent\Collection;

/** Read model of the finance reconciliation page (T-P8-11): the latest run, open count and the mismatch list. */
final class ReconciliationReport
{
    public const int LIMIT = 100;

    public function latestRun(): ?PaymentReconciliation
    {
        return PaymentReconciliation::query()->orderByDesc('run_date')->orderByDesc('id')->first();
    }

    public function openCount(): int
    {
        return PaymentReconciliationMismatch::query()->where('status', ReconciliationMismatchStatus::Open->value)->count();
    }

    /** @return Collection<int, PaymentReconciliationMismatch> open first, newest first, capped */
    public function mismatches(?ReconciliationMismatchStatus $status): Collection
    {
        return PaymentReconciliationMismatch::query()
            ->with(['order:id,reference', 'reviewer:id,name'])
            ->when($status !== null, static fn ($query) => $query->where('status', $status?->value))
            ->orderByRaw("case when status = 'open' then 0 else 1 end")
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();
    }
}
