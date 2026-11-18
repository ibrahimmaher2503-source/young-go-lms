<?php

declare(strict_types=1);

namespace App\Filament\Resources\DataRequests\Pages;

use App\Domain\Privacy\DataRequest;
use App\Domain\Privacy\DataRequestStatus;
use App\Domain\Privacy\DataRequestType;
use App\Filament\Resources\DataRequests\DataRequestResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;

final class ListDataRequests extends WorkspaceListRecords
{
    protected static string $resource = DataRequestResource::class;

    /** @return array<array{label: string, value: int|string, icon?: string, tone?: string}> */
    protected function getWorkspaceSummary(): array
    {
        return [
            ['label' => __('privacy.summary_requested'), 'value' => DataRequest::query()->where('status', DataRequestStatus::Requested->value)->count(), 'icon' => 'heroicon-o-clock', 'tone' => 'info'],
            ['label' => __('privacy.summary_approved'), 'value' => DataRequest::query()->where('status', DataRequestStatus::Approved->value)->count(), 'icon' => 'heroicon-o-check-circle', 'tone' => 'warning'],
            ['label' => __('privacy.summary_deletions'), 'value' => DataRequest::query()->where('type', DataRequestType::Deletion->value)->whereIn('status', [DataRequestStatus::Requested->value, DataRequestStatus::Approved->value])->count(), 'icon' => 'heroicon-o-trash'],
        ];
    }
}
