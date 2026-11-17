<?php

declare(strict_types=1);

namespace App\Domain\Progress;

use App\Domain\Catalog\Contracts\CourseHistoryChecker;
use App\Models\Course;
use App\Models\CurriculumItem;

final class ProgressHistory implements CourseHistoryChecker
{
    public function blocks(Course $course): ?string
    {
        $hasProgress = CourseProgress::query()->where('course_id', $course->getKey())->exists()
            || LessonProgress::query()->whereIn('curriculum_item_id', CurriculumItem::query()->where('course_id', $course->getKey())->select('id'))->exists();

        return $hasProgress ? 'progress' : null;
    }
}
