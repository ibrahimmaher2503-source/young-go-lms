<?php

declare(strict_types=1);

namespace App\Domain\Progress;

use App\Domain\Entitlements\Contracts\CompletionLookup;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\User;

final class LessonCompletionLookup implements CompletionLookup
{
    public function completedItemIds(User $learner, Course $course): array
    {
        return LessonProgress::query()->where('learner_id', $learner->getKey())->where('status', LessonProgress::COMPLETED)
            ->whereIn('curriculum_item_id', CurriculumItem::query()->where('course_id', $course->getKey())->select('id'))
            ->pluck('curriculum_item_id')->map(static fn ($id): int => (int) $id)->all();
    }
}
