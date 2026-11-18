<?php

declare(strict_types=1);

namespace App\Domain\Assessment;

use App\Domain\Catalog\Contracts\CourseHistoryChecker;
use App\Domain\Completion\CourseCompletion;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\QuizAttempt;

final class AssessmentHistory implements CourseHistoryChecker
{
    public function blocks(Course $course): ?string
    {
        $items = CurriculumItem::query()->where('course_id', $course->getKey())->select('itemable_id');
        if (QuizAttempt::query()->whereIn('quiz_id', (clone $items)->where('itemable_type', 'quiz'))->exists()) {
            return 'attempts';
        }
        if (AssignmentSubmission::query()->whereIn('assignment_id', $items->where('itemable_type', 'assignment'))->exists()) {
            return 'submissions';
        }

        return CourseCompletion::query()->where('course_id', $course->getKey())->exists() ? 'completion' : null;
    }
}
