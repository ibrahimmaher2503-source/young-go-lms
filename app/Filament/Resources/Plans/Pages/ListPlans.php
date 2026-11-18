<?php

declare(strict_types=1);

namespace App\Filament\Resources\Plans\Pages;

use App\Domain\Subscriptions\SubscriptionPlan;
use App\Filament\Resources\Plans\PlanForm;
use App\Filament\Resources\Plans\PlanResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Gate;

final class ListPlans extends WorkspaceListRecords
{
    protected static string $resource = PlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            PlanForm::configure(Action::make('create')->label(__('subscriptions.create'))->icon('heroicon-o-plus')
                ->visible(static fn (): bool => Gate::allows('payment.approve_manual'))),
        ];
    }

    /** @return array<array{label: string, value: int|string, icon?: string, tone?: string}> */
    protected function getWorkspaceSummary(): array
    {
        return [
            ['label' => __('subscriptions.plans_summary_active'), 'value' => SubscriptionPlan::query()->where('is_active', true)->count(), 'icon' => 'heroicon-o-check-circle', 'tone' => 'success'],
            ['label' => __('subscriptions.plans_summary_inactive'), 'value' => SubscriptionPlan::query()->where('is_active', false)->count(), 'icon' => 'heroicon-o-pause-circle'],
        ];
    }
}
