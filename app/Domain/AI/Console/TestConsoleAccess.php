<?php

declare(strict_types=1);

namespace App\Domain\AI\Console;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Who may use the instructor test console (AI spec 13.5): whoever may upload lesson documents and update the course, that
 * is the course's manage-scope instructors and admins. Instructors of other courses get nothing.
 */
final class TestConsoleAccess
{
    public static function allows(User $user, Course $course): bool
    {
        return $user->getAttribute('status') === 'active'
            && $user->can('lesson_document.upload')
            && $user->can('update', $course);
    }

    /**
     * Lessons with an active, ready document in courses the user may test.
     *
     * @return Collection<int, Lesson>
     */
    public static function lessonsFor(User $user): Collection
    {
        if ($user->getAttribute('status') !== 'active' || ! $user->can('lesson_document.upload')) {
            return new Collection;
        }

        return Lesson::query()
            ->whereHas('curriculumDocuments', static fn (Builder $documents) => $documents->where('active', true)->where('status', LessonCurriculumDocument::STATUS_READY))
            ->whereHas('item.course', static function (Builder $courses) use ($user): void {
                if (! $user->can('panel.admin.access')) {
                    $courses->whereHas('instructors', static fn (Builder $instructors) => $instructors
                        ->whereKey($user->getKey())->where('course_instructors.scope', Course::INSTRUCTOR_SCOPE_MANAGE));
                }
            })
            ->with('item.course')
            ->orderBy('id')
            ->get()
            ->filter(static fn (Lesson $lesson): bool => $lesson->item?->course !== null && self::allows($user, $lesson->item->course))
            ->values();
    }
}
