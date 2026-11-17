<?php

declare(strict_types=1);

namespace App\Filament\Resources\EssayReview\Pages;

use App\Filament\Resources\EssayReview\EssayReviewResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;
use Illuminate\Database\Eloquent\Builder;

final class ListEssayReviews extends WorkspaceListRecords
{
    protected static string $resource = EssayReviewResource::class;

    /** @return array<array{label: string, value: int|string, icon?: string, tone?: string}> */
    protected function getWorkspaceSummary(): array
    {
        $base = EssayReviewResource::getEloquentQuery();
        $courseId = $this->tableFilters['course']['value'] ?? null;
        if (filled($courseId)) {
            $base->whereHas('quiz.item', static fn (Builder $item): Builder => $item->where('course_id', (int) $courseId));
        }

        return [
            ['label' => __('review.summary.awaiting'), 'value' => (clone $base)->where('needs_review', true)->count(), 'icon' => 'heroicon-o-clock', 'tone' => 'info'],
            ['label' => __('review.summary.decided'), 'value' => (clone $base)->where('needs_review', false)->count(), 'icon' => 'heroicon-o-check-circle', 'tone' => 'success'],
        ];
    }
}
