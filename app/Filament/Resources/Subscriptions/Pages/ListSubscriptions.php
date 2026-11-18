<?php

declare(strict_types=1);

namespace App\Filament\Resources\Subscriptions\Pages;

use App\Domain\Subscriptions\Subscription;
use App\Domain\Subscriptions\SubscriptionStatus;
use App\Filament\Resources\Subscriptions\SubscriptionResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;

final class ListSubscriptions extends WorkspaceListRecords
{
    protected static string $resource = SubscriptionResource::class;

    /** @return array<array{label: string, value: int|string, icon?: string, tone?: string}> */
    protected function getWorkspaceSummary(): array
    {
        $open = Subscription::query()->where('status', SubscriptionStatus::Active->value);

        return [
            ['label' => __('subscriptions.summary_active'), 'value' => (clone $open)->whereNull('cancelled_at')->count(), 'icon' => 'heroicon-o-check-circle', 'tone' => 'success'],
            ['label' => __('subscriptions.summary_cancelled'), 'value' => (clone $open)->whereNotNull('cancelled_at')->count(), 'icon' => 'heroicon-o-x-circle'],
            ['label' => __('subscriptions.summary_expired'), 'value' => Subscription::query()->where('status', SubscriptionStatus::Expired->value)->count(), 'icon' => 'heroicon-o-pause-circle'],
        ];
    }
}
