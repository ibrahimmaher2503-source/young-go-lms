<?php

declare(strict_types=1);

namespace App\Domain\Completion;

use App\Domain\Completion\Events\CourseCompletionInputChanged;
use App\Models\Course;
use App\Models\User;
use Throwable;

/** Called by the Actions that change completion inputs, after their own work is committed. A failure here never breaks the caller. */
final class CompletionTrigger
{
    public static function changed(User|int $learner, Course|int $course): void
    {
        try {
            event(new CourseCompletionInputChanged(
                $learner instanceof User ? (int) $learner->getKey() : $learner,
                $course instanceof Course ? (int) $course->getKey() : $course,
            ));
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
