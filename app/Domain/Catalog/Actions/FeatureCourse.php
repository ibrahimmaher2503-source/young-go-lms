<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Models\Course;
use Illuminate\Support\Facades\Gate;

final class FeatureCourse
{
    public function __invoke(Course $course, bool $featured): void
    {
        Gate::authorize('feature', $course);
        $course->update(['featured' => $featured]);
    }
}
