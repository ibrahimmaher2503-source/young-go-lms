<?php

declare(strict_types=1);

namespace App\Filament\Resources\NotificationTemplates\Pages;

use App\Domain\Notifications\Actions\SyncNotificationTemplateRows;
use App\Domain\Notifications\NotificationMatrix;
use App\Domain\Notifications\NotificationTemplate;
use App\Filament\Resources\NotificationTemplates\NotificationTemplateResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;

final class ListNotificationTemplates extends WorkspaceListRecords
{
    protected static string $resource = NotificationTemplateResource::class;

    public function mount(): void
    {
        app(SyncNotificationTemplateRows::class)();

        parent::mount();
    }

    /** @return array<array{label: string, value: int|string, icon?: string, tone?: string}> */
    protected function getWorkspaceSummary(): array
    {
        $custom = NotificationTemplate::query()->where('is_active', true)->where(static fn ($q) => $q->whereNotNull('title_ar')->orWhereNotNull('title_en')->orWhereNotNull('body_ar')->orWhereNotNull('body_en'))->count();

        return [
            ['label' => __('notification_events.summary_events'), 'value' => count(NotificationMatrix::all()), 'icon' => 'heroicon-o-bell'],
            ['label' => __('notification_events.summary_custom'), 'value' => $custom, 'icon' => 'heroicon-o-pencil-square', 'tone' => 'info'],
            ['label' => __('notification_events.summary_optional'), 'value' => count(NotificationMatrix::optional()), 'icon' => 'heroicon-o-adjustments-horizontal'],
        ];
    }
}
