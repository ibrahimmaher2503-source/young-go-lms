<?php

declare(strict_types=1);

namespace App\Domain\Commerce\Queries;

use App\Domain\Commerce\ManualPaymentProof;
use App\Domain\Commerce\ManualPaymentStatus;

/** Counts for the finance review queue header. */
final class ManualPaymentQueue
{
    /** @return array{pending: int, overdue: int, mismatch: int} */
    public static function summary(): array
    {
        $pending = ManualPaymentProof::query()->where('status', ManualPaymentStatus::Pending->value);

        return [
            'pending' => (clone $pending)->count(),
            'overdue' => (clone $pending)->where('sla_due_at', '<=', now())->count(),
            'mismatch' => (clone $pending)->whereHas('order', static fn ($q) => $q->whereColumn('orders.total_minor', '!=', 'manual_payment_proofs.amount_minor'))->count(),
        ];
    }
}
