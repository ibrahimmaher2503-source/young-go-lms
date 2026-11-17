<?php

declare(strict_types=1);

namespace App\Domain\AI\Queries;

use App\Domain\AI\Admin\AssistantAdminAccess;
use App\Domain\AI\Ingestion\DocumentReadiness;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** The per-course and per-lesson assistant switches a staff member may change, with each lesson's PDF readiness (AI spec 14). */
final class CourseAssistantOverview
{
    /**
     * Courses the user may switch: all for admins, the own manage-scope courses for instructors.
     *
     * @return Collection<int, Course>
     */
    public function courses(User $user): Collection
    {
        if (! AssistantAdminAccess::canOpenCoursePages($user)) {
            return new Collection;
        }

        return Course::query()
            ->when(! $user->can('panel.admin.access'), static fn (Builder $courses) => $courses->whereHas('instructors', static fn (Builder $instructors) => $instructors
                ->whereKey($user->getKey())->where('course_instructors.scope', Course::INSTRUCTOR_SCOPE_MANAGE)))
            ->orderByDesc('id')->limit(200)->get()
            ->filter(static fn (Course $course): bool => AssistantAdminAccess::canManageCourse($user, $course))->values();
    }

    /**
     * @return list<array{item_id: int, lesson_id: int, title: string, enabled: bool, type: string, readiness: DocumentReadiness}>
     */
    public function lessons(Course $course): array
    {
        $rows = [];
        $items = CurriculumItem::query()->where('course_id', $course->getKey())->where('itemable_type', 'lesson')->orderBy('sort')->orderBy('id')->get();
        $lessons = Lesson::query()->with('curriculumDocuments')->whereIn('id', $items->pluck('itemable_id'))->get()->keyBy('id');

        foreach ($items as $item) {
            $lesson = $lessons->get($item->itemable_id);
            if ($lesson === null) {
                continue;
            }
            $rows[] = [
                'item_id' => (int) $item->getKey(),
                'lesson_id' => (int) $lesson->getKey(),
                'title' => (string) $lesson->title,
                'enabled' => $item->ai_enabled,
                'type' => $lesson->type,
                'readiness' => DocumentReadiness::fromStatus($lesson->curriculumDocuments->first()?->status),
            ];
        }

        return $rows;
    }
}
