<?php

declare(strict_types=1);

namespace App\Domain\Completion\Events;

use App\Domain\Completion\CourseCompletion;

/** Dispatched once, when the completion row is first created (certificates listen to this, T-P6-06). */
final class CourseCompleted
{
    public function __construct(public readonly CourseCompletion $completion) {}
}
