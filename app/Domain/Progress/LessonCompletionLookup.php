<?php

declare(strict_types=1);

namespace App\Domain\Progress;

use App\Domain\Entitlements\Contracts\CompletionLookup;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Collection;

final class LessonCompletionLookup implements CompletionLookup
{
    public function completedItemIds(User $learner, Course $course): array
    {
        return $this->completedItemIdsByCourse($learner, [$course->id])[$course->id] ?? [];
    }

    public function completedItemIdsByCourse(User $learner, array $courseIds): array
    {
        return LessonProgress::query()->select('lesson_progress.curriculum_item_id', 'curriculum_items.course_id')
            ->join('curriculum_items', 'curriculum_items.id', '=', 'lesson_progress.curriculum_item_id')
            ->where('lesson_progress.learner_id', $learner->id)->where('lesson_progress.status', LessonProgress::COMPLETED)
            ->whereIn('curriculum_items.course_id', $courseIds)->get()->groupBy('course_id')
            ->map(fn (Collection $rows): array => $rows->pluck('curriculum_item_id')->map(static fn ($id): int => (int) $id)->all())->all();
    }
}
