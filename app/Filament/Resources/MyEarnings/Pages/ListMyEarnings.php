<?php

declare(strict_types=1);

namespace App\Filament\Resources\MyEarnings\Pages;

use App\Domain\InstructorFinance\Queries\LedgerTotals;
use App\Filament\Resources\MyEarnings\MyEarningsResource;
use App\Filament\Resources\Refunds\RefundResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;
use Illuminate\Support\Facades\Auth;

final class ListMyEarnings extends WorkspaceListRecords
{
    protected static string $resource = MyEarningsResource::class;

    /** @return array<array{label: string, value: int|string, icon?: string, tone?: string}> */
    protected function getWorkspaceSummary(): array
    {
        $items = [];
        foreach (app(LedgerTotals::class)->forInstructor((int) Auth::id()) as $currency => $totals) {
            $labelCurrency = RefundResource::currencyLabel($currency);
            $items[] = ['label' => __('ledger.summary_earned', ['currency' => $labelCurrency]), 'value' => RefundResource::money($totals['earnings'], $currency), 'icon' => 'heroicon-o-arrow-trending-up', 'tone' => 'success'];
            $items[] = ['label' => __('ledger.summary_reversed', ['currency' => $labelCurrency]), 'value' => RefundResource::money($totals['reversals'], $currency), 'icon' => 'heroicon-o-arrow-uturn-left'];
            $items[] = ['label' => __('ledger.summary_adjustments', ['currency' => $labelCurrency]), 'value' => RefundResource::money($totals['adjustments'], $currency), 'icon' => 'heroicon-o-adjustments-horizontal'];
            $items[] = ['label' => __('ledger.summary_net', ['currency' => $labelCurrency]), 'value' => RefundResource::money($totals['net'], $currency), 'icon' => 'heroicon-o-scale'];
        }

        return $items;
    }
}
