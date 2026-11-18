<?php

declare(strict_types=1);

namespace App\Filament\Resources\Bundles\Pages;

use App\Domain\Commerce\Bundle;
use App\Filament\Resources\Bundles\BundleForm;
use App\Filament\Resources\Bundles\BundleResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Gate;

final class ListBundles extends WorkspaceListRecords
{
    protected static string $resource = BundleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            BundleForm::configure(Action::make('create')->label(__('bundles.create'))->icon('heroicon-o-plus')
                ->visible(static fn (): bool => Gate::allows('payment.approve_manual'))),
        ];
    }

    /** @return array<array{label: string, value: int|string, icon?: string, tone?: string}> */
    protected function getWorkspaceSummary(): array
    {
        return [
            ['label' => __('bundles.summary_active'), 'value' => Bundle::query()->where('is_active', true)->count(), 'icon' => 'heroicon-o-check-circle', 'tone' => 'success'],
            ['label' => __('bundles.summary_inactive'), 'value' => Bundle::query()->where('is_active', false)->count(), 'icon' => 'heroicon-o-pause-circle'],
        ];
    }
}
