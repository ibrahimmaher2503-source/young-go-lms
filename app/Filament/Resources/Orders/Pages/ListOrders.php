<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Pages;

use App\Domain\Commerce\Queries\OrderDirectory;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;

final class ListOrders extends WorkspaceListRecords
{
    protected static string $resource = OrderResource::class;

    /** @return array<array{label: string, value: int|string, icon?: string, tone?: string}> */
    protected function getWorkspaceSummary(): array
    {
        $summary = OrderDirectory::summary();

        return [
            ['label' => __('orders.summary_pending'), 'value' => $summary['pending'], 'icon' => 'heroicon-o-clock', 'tone' => 'warning'],
            ['label' => __('orders.summary_paid'), 'value' => $summary['paid'], 'icon' => 'heroicon-o-check-circle', 'tone' => 'info'],
        ];
    }
}
