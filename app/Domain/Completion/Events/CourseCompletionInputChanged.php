<?php

declare(strict_types=1);

namespace App\Domain\Completion\Events;

/** Something that can change a learner's completion happened: progress, a quiz result, an assignment decision or attendance. */
final class CourseCompletionInputChanged
{
    public function __construct(public readonly int $learnerId, public readonly int $courseId) {}
}
