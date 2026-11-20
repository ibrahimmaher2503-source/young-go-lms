<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Catalog\Contracts\CourseHistoryChecker;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\LessonDocumentChunk;
use App\Models\LessonDocumentPage;
use App\Models\LessonKeyTerm;
use App\Models\LessonResource;
use App\Models\MediaAsset;
use App\Models\Quiz;
use App\Models\SubtitleTrack;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Deletes a course only when nothing in the system remembers it: every module that holds history (access, progress,
 * later orders and attempts) is asked through the "course.history" tag. If any says no, the course must be archived.
 */
final class DeleteCourse
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @return list<string> reason codes; empty when the course may be deleted */
    public static function blockers(Course $course): array
    {
        $reasons = [];
        foreach (app()->tagged('course.history') as $checker) {
            if ($checker instanceof CourseHistoryChecker && ($reason = $checker->blocks($course)) !== null) {
                $reasons[] = $reason;
            }
        }

        return $reasons;
    }

    public function __invoke(User $actor, Course $course): void
    {
        Gate::forUser($actor)->authorize('delete', $course);

        $files = [];
        DB::transaction(function () use ($actor, $course, &$files): void {
            $locked = Course::query()->whereKey($course->getKey())->lockForUpdate()->firstOrFail();

            $blockers = self::blockers($locked);
            if ($blockers !== []) {
                throw ValidationException::withMessages(['course' => __('courses.delete_blocked', ['reasons' => implode(', ', array_map(static fn (string $r): string => __('courses.blocked.'.$r), $blockers))])]);
            }

            if ($this->audit->record('course.deleted', $locked, (int) $actor->getKey(), ['title' => $locked->getTranslations('title'), 'status' => $locked->status->value]) === null) {
                throw new RuntimeException('Unable to record course audit.');
            }

            $items = CurriculumItem::query()->where('course_id', $locked->getKey())->get();
            $lessonIds = $items->where('itemable_type', 'lesson')->pluck('itemable_id')->all();
            $documents = LessonCurriculumDocument::query()->whereIn('lesson_id', $lessonIds)->get();

            LessonDocumentChunk::query()->whereIn('document_id', $documents->pluck('id'))->delete();
            LessonDocumentPage::query()->whereIn('document_id', $documents->pluck('id'))->delete();
            LessonKeyTerm::query()->whereIn('document_id', $documents->pluck('id'))->delete();
            $files = array_merge(
                $documents->pluck('path')->all(),
                LessonResource::query()->whereIn('lesson_id', $lessonIds)->whereNotNull('path')->pluck('path')->all(),
                SubtitleTrack::query()->whereIn('lesson_id', $lessonIds)->pluck('path')->all(),
                MediaAsset::query()->whereIn('lesson_id', $lessonIds)->whereNotNull('path')->pluck('path')->all(),
            );
            LessonCurriculumDocument::query()->whereIn('id', $documents->pluck('id'))->delete();
            LessonResource::query()->whereIn('lesson_id', $lessonIds)->delete();
            SubtitleTrack::query()->whereIn('lesson_id', $lessonIds)->delete();
            MediaAsset::query()->whereIn('lesson_id', $lessonIds)->delete();
            CurriculumItem::query()->where('course_id', $locked->getKey())->delete();
            Lesson::query()->whereIn('id', $lessonIds)->delete();
            Quiz::query()->whereIn('id', $items->where('itemable_type', 'quiz')->pluck('itemable_id'))->delete();
            Assignment::query()->whereIn('id', $items->where('itemable_type', 'assignment')->pluck('itemable_id'))->delete();
            CourseSection::query()->where('course_id', $locked->getKey())->delete();
            $locked->instructors()->detach();
            $locked->delete();
        });

        // Files go only after the rows are gone for good, so a failed delete never leaves rows pointing at missing files.
        Storage::disk(Config::string('media.disk'))->delete(array_values(array_filter($files, static fn (string $path): bool => ! str_starts_with($path, 'library/'))));
    }
}
