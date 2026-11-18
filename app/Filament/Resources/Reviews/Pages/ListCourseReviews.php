<?php

declare(strict_types=1);

namespace App\Filament\Resources\Reviews\Pages;

use App\Domain\Engagement\Queries\ReviewModerationQueue;
use App\Filament\Resources\Reviews\CourseReviewResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;

final class ListCourseReviews extends WorkspaceListRecords
{
    protected static string $resource = CourseReviewResource::class;

    /** @return array<array{label: string, value: int|string, icon?: string, tone?: string}> */
    protected function getWorkspaceSummary(): array
    {
        $summary = ReviewModerationQueue::summary();

        return [
            ['label' => __('engagement.summary_pending'), 'value' => $summary['pending'], 'icon' => 'heroicon-o-clock', 'tone' => 'info'],
            ['label' => __('engagement.summary_published'), 'value' => $summary['published'], 'icon' => 'heroicon-o-check-circle', 'tone' => 'success'],
            ['label' => __('engagement.summary_hidden'), 'value' => $summary['hidden'], 'icon' => 'heroicon-o-eye-slash', 'tone' => 'warning'],
        ];
    }
}
