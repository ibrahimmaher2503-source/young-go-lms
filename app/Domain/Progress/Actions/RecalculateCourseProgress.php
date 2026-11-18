<?php

declare(strict_types=1);

namespace App\Domain\Progress\Actions;

use App\Domain\Progress\CourseProgress;
use App\Domain\Progress\Queries\CourseProgressSnapshot;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** Persists the latest course progress after a completion event. */
final class RecalculateCourseProgress
{
    public function __invoke(User $learner, Course $course): CourseProgress
    {
        return DB::transaction(function () use ($learner, $course): CourseProgress {
            // Serialize concurrent completions for a learner, including the first aggregate insert.
            User::query()->lockForUpdate()->findOrFail($learner->id);
            $stored = CourseProgress::query()->firstOrNew(['learner_id' => $learner->id, 'course_id' => $course->id]);
            $progress = CourseProgressSnapshot::for(
                $learner,
                $course,
                CourseProgressSnapshot::lessonRows($learner, [$course->id])->get($course->id, collect()),
                $stored,
            );
            $progress->save();

            return $progress;
        });
    }
}
