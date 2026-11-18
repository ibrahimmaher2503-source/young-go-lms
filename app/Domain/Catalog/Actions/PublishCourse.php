<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\CourseStatus;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class PublishCourse
{
    /** Authorizes `$actor` when given (queue/CLI callers), otherwise the session user. */
    public function __invoke(Course $course, ?User $actor = null): void
    {
        DB::transaction(static function () use ($course, $actor): void {
            $current = Course::query()->lockForUpdate()->findOrFail($course->getKey());
            ($actor instanceof User ? Gate::forUser($actor) : Gate::getFacadeRoot())->authorize('publish', $current);
            $current->update(['status' => CourseStatus::Published]);
        }, attempts: 3);
    }
}
