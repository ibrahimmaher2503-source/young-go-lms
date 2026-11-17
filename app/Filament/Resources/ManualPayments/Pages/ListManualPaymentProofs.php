<?php

declare(strict_types=1);

namespace App\Filament\Resources\ManualPayments\Pages;

use App\Domain\Commerce\Queries\ManualPaymentQueue;
use App\Filament\Resources\ManualPayments\ManualPaymentProofResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;

final class ListManualPaymentProofs extends WorkspaceListRecords
{
    protected static string $resource = ManualPaymentProofResource::class;

    /** @return array<array{label: string, value: int|string, icon?: string, tone?: string}> */
    protected function getWorkspaceSummary(): array
    {
        $summary = ManualPaymentQueue::summary();

        return [
            ['label' => __('manual_payment.summary_pending'), 'value' => $summary['pending'], 'icon' => 'heroicon-o-clock', 'tone' => 'info'],
            ['label' => __('manual_payment.summary_overdue'), 'value' => $summary['overdue'], 'icon' => 'heroicon-o-exclamation-triangle', 'tone' => 'warning'],
            ['label' => __('manual_payment.summary_mismatch'), 'value' => $summary['mismatch'], 'icon' => 'heroicon-o-scale', 'tone' => 'danger'],
        ];
    }
}
