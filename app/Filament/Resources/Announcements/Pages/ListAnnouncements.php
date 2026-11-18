<?php

declare(strict_types=1);

namespace App\Filament\Resources\Announcements\Pages;

use App\Domain\Notifications\Announcement;
use App\Filament\Resources\Announcements\AnnouncementForm;
use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;
use Filament\Actions\Action;

final class ListAnnouncements extends WorkspaceListRecords
{
    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [AnnouncementForm::configure(Action::make('create')->label(__('notification_events.announcement_create'))->icon('heroicon-o-plus'))];
    }

    /** @return array<array{label: string, value: int|string, icon?: string, tone?: string}> */
    protected function getWorkspaceSummary(): array
    {
        $query = AnnouncementResource::getEloquentQuery();

        return [
            ['label' => __('notification_events.announcement_state_draft'), 'value' => (clone $query)->where('status', Announcement::DRAFT)->count(), 'icon' => 'heroicon-o-pencil-square'],
            ['label' => __('notification_events.announcement_state_published'), 'value' => (clone $query)->where('status', Announcement::PUBLISHED)->count(), 'icon' => 'heroicon-o-check-circle', 'tone' => 'success'],
        ];
    }
}
