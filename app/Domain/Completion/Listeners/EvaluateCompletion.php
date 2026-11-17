<?php

declare(strict_types=1);

namespace App\Domain\Completion\Listeners;

use App\Domain\Completion\Actions\CourseCompletionEvaluator;
use App\Domain\Completion\Events\CourseCompletionInputChanged;
use App\Models\Course;
use App\Models\User;

final class EvaluateCompletion
{
    public function __construct(private readonly CourseCompletionEvaluator $evaluator) {}

    public function handle(CourseCompletionInputChanged $event): void
    {
        $learner = User::query()->find($event->learnerId);
        $course = Course::query()->find($event->courseId);
        if ($learner instanceof User && $course instanceof Course) {
            ($this->evaluator)($learner, $course);
        }
    }
}
