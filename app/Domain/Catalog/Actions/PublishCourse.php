<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Catalog\CourseStatus;
use App\Models\Course;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

final class PublishCourse
{
    public function __invoke(Course $course): void
    {
        DB::transaction(static function () use ($course): void {
            $current = Course::query()->lockForUpdate()->findOrFail($course->getKey());
            Gate::authorize('publish', $current);
            $current->update(['status' => CourseStatus::Published]);
        }, attempts: 3);
    }
}
