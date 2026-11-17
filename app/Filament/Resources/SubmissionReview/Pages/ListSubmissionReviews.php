<?php

declare(strict_types=1);

namespace App\Filament\Resources\SubmissionReview\Pages;

use App\Domain\Assignments\SubmissionReview;
use App\Filament\Resources\SubmissionReview\SubmissionReviewResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;
use App\Models\AssignmentSubmission;
use Illuminate\Database\Eloquent\Builder;

final class ListSubmissionReviews extends WorkspaceListRecords
{
    protected static string $resource = SubmissionReviewResource::class;

    /** @return array<array{label: string, value: int|string, icon?: string, tone?: string}> */
    protected function getWorkspaceSummary(): array
    {
        $base = SubmissionReviewResource::getEloquentQuery();
        $courseId = $this->tableFilters['course']['value'] ?? null;
        if (filled($courseId)) {
            $base->whereHas('assignment.item', static fn (Builder $item): Builder => $item->where('course_id', (int) $courseId));
        }
        $count = static fn (array $statuses): int => (clone $base)->whereIn('status', $statuses)->count();

        return [
            ['label' => __('review.summary.awaiting'), 'value' => $count(SubmissionReview::AWAITING), 'icon' => 'heroicon-o-clock', 'tone' => 'info'],
            ['label' => __('review.summary.returned'), 'value' => $count([AssignmentSubmission::RETURNED]), 'icon' => 'heroicon-o-arrow-uturn-left'],
            ['label' => __('review.summary.decided'), 'value' => $count([AssignmentSubmission::GRADED, AssignmentSubmission::ACCEPTED, AssignmentSubmission::REJECTED]), 'icon' => 'heroicon-o-check-circle', 'tone' => 'success'],
        ];
    }
}
