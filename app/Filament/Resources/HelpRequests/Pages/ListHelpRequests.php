<?php

declare(strict_types=1);

namespace App\Filament\Resources\HelpRequests\Pages;

use App\Domain\AI\Help\HelpRequestStatus;
use App\Filament\Resources\HelpRequests\HelpRequestResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;

final class ListHelpRequests extends WorkspaceListRecords
{
    protected static string $resource = HelpRequestResource::class;

    /** @return array<array{label: string, value: int|string, icon?: string, tone?: string}> */
    protected function getWorkspaceSummary(): array
    {
        $base = HelpRequestResource::getEloquentQuery();
        $courseId = $this->tableFilters['course']['value'] ?? null;
        if (filled($courseId)) {
            $base->where('course_id', (int) $courseId);
        }

        return [
            ['label' => __('help.queue.summary.open'), 'value' => (clone $base)->whereIn('status', HelpRequestStatus::openValues())->count(), 'icon' => 'heroicon-o-lifebuoy', 'tone' => 'info'],
            ['label' => __('help.queue.summary.awaiting'), 'value' => (clone $base)->whereIn('status', HelpRequestStatus::awaitingValues())->count(), 'icon' => 'heroicon-o-clock'],
            ['label' => __('help.queue.summary.overdue'), 'value' => (clone $base)->whereIn('status', HelpRequestStatus::awaitingValues())->whereNotNull('due_at')->where('due_at', '<', now())->count(), 'icon' => 'heroicon-o-exclamation-triangle', 'tone' => 'warning'],
        ];
    }
}
