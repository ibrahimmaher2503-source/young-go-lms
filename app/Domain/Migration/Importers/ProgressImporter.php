<?php

declare(strict_types=1);

namespace App\Domain\Migration\Importers;

use App\Domain\Migration\Contracts\LegacySourceReader;
use App\Domain\Migration\Data\ImportContext;
use App\Domain\Migration\Data\ImportExceptionData;
use App\Domain\Migration\Data\RowImportResult;
use App\Domain\Migration\Enums\LegacyEntity;
use App\Domain\Migration\Enums\MigrationExceptionSeverity;
use App\Domain\Migration\Support\LegacyDateParser;
use App\Domain\Progress\Actions\ImportLegacyProgress;
use App\Domain\Progress\Actions\RecalculateCourseProgress;
use App\Domain\Progress\CourseProgress;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\User;
use Illuminate\Database\Query\Builder;

final class ProgressImporter extends AbstractEntityImporter
{
    public function __construct(
        private readonly ImportLegacyProgress $importProgress,
        private readonly RecalculateCourseProgress $recalculate,
    ) {}

    public function entity(): LegacyEntity
    {
        return LegacyEntity::Progress;
    }

    public function sourceTable(): string
    {
        return 'watch_histories';
    }

    public function sourceIdColumn(): string
    {
        return 'watch_history_id';
    }

    public function checksumPayload(array $row): array
    {
        return [
            'watch_history_id' => $row['watch_history_id'] ?? null,
            'course_id' => $row['course_id'] ?? null,
            'student_id' => $row['student_id'] ?? null,
            'completed_lesson' => $row['completed_lesson'] ?? null,
            'course_progress' => $row['course_progress'] ?? null,
            'watching_lesson_id' => $row['watching_lesson_id'] ?? null,
            'quiz_result' => $row['quiz_result'] ?? null,
            'completed_date' => $row['completed_date'] ?? null,
        ];
    }

    public function importRow(array $row, ImportContext $context): RowImportResult
    {
        $sourceId = $this->extractSourceId($row);
        $legacyUserId = (int) ($row['student_id'] ?? 0);
        $legacyCourseId = (int) ($row['course_id'] ?? 0);

        $learnerId = $context->mappedId(LegacyEntity::Users, $legacyUserId);
        if ($learnerId === null) {
            return RowImportResult::rejected('missing_user', "Legacy student {$legacyUserId} is not mapped.");
        }

        $courseId = $context->mappedId(LegacyEntity::Courses, $legacyCourseId);
        if ($courseId === null) {
            return RowImportResult::rejected('missing_course', "Legacy course {$legacyCourseId} is not mapped.");
        }

        $exceptions = [];
        $quizResultRaw = trim((string) ($row['quiz_result'] ?? ''));
        if ($quizResultRaw !== '' && ! in_array($quizResultRaw, ['[]', '{}', 'null'], true)) {
            $exceptions[] = new ImportExceptionData(
                'watch_history_quiz_mismatch',
                MigrationExceptionSeverity::Warning,
                'watch_histories.quiz_result contains non-empty JSON requiring reconciliation against quiz_results.',
                ['watch_history_id' => $sourceId],
            );
        }

        $completedLessonIds = json_decode((string) ($row['completed_lesson'] ?? '[]'), true);
        if (! is_array($completedLessonIds)) {
            $completedLessonIds = [];
        }
        $completedSet = array_fill_keys(array_map(static fn (mixed $id): string => (string) $id, $completedLessonIds), true);

        $durations = $this->loadWatchedDurations($context->reader, $legacyUserId, $legacyCourseId);
        $touchedLessons = array_unique(array_merge(array_keys($completedSet), array_keys($durations)));

        $lessonCount = 0;
        foreach ($touchedLessons as $legacyLessonId) {
            if ($legacyLessonId === '' || $legacyLessonId === '0') {
                continue;
            }
            $lessonTargetId = $context->mappedId(LegacyEntity::Lessons, $legacyLessonId);
            if ($lessonTargetId === null) {
                $exceptions[] = new ImportExceptionData(
                    'unmapped_progress_lesson',
                    MigrationExceptionSeverity::Warning,
                    "Legacy lesson {$legacyLessonId} referenced in progress history is not mapped.",
                    ['legacy_lesson_id' => $legacyLessonId],
                );

                continue;
            }
            $lessonCount++;
        }

        $existingTargetId = $context->existingRecord?->target_id;
        $metadata = ['lessons_matched' => $lessonCount, 'legacy_progress_percent' => (int) ($row['course_progress'] ?? 0)];

        if ($context->dryRun) {
            $shadowId = $context->idMap->rememberShadow(LegacyEntity::Progress, $sourceId, CourseProgress::class, $existingTargetId, $metadata);

            return $existingTargetId !== null
                ? RowImportResult::updated(CourseProgress::class, $shadowId, $metadata, $exceptions)
                : RowImportResult::imported(CourseProgress::class, $shadowId, $metadata, $exceptions);
        }

        $learner = User::query()->find($learnerId);
        $course = Course::query()->find($courseId);
        if ($learner === null || $course === null) {
            return RowImportResult::rejected('missing_target_entity', 'Target learner or course record does not exist.');
        }

        $completedAt = LegacyDateParser::toUtc($row['completed_date'] ?? null);
        $lastAccessedAt = LegacyDateParser::toUtc($row['date_updated'] ?? ($row['date_added'] ?? null)) ?? now()->utc();

        foreach ($touchedLessons as $legacyLessonId) {
            if ($legacyLessonId === '' || $legacyLessonId === '0') {
                continue;
            }
            $lessonMeta = $context->mappedMetadata(LegacyEntity::Lessons, $legacyLessonId);
            $curriculumItemId = isset($lessonMeta['curriculum_item_id']) ? (int) $lessonMeta['curriculum_item_id'] : null;
            if ($curriculumItemId === null) {
                continue;
            }
            $item = CurriculumItem::query()->find($curriculumItemId);
            if ($item === null) {
                continue;
            }

            ($this->importProgress)(
                $learner,
                $item,
                isset($completedSet[$legacyLessonId]),
                $durations[$legacyLessonId] ?? 0,
                $completedAt,
                $lastAccessedAt,
            );
        }

        $courseProgress = ($this->recalculate)($learner, $course);

        return $existingTargetId !== null
            ? RowImportResult::updated(CourseProgress::class, (int) $courseProgress->getKey(), $metadata, $exceptions)
            : RowImportResult::imported(CourseProgress::class, (int) $courseProgress->getKey(), $metadata, $exceptions);
    }

    protected function existingTargetIds(array $targetIds): array
    {
        if ($targetIds === []) {
            return [];
        }

        return CourseProgress::query()->whereIn('id', $targetIds)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->values()->all();
    }

    protected function compareSample(array $sourceRow, int $targetId): array
    {
        return CourseProgress::query()->whereKey($targetId)->exists() ? [] : ['target_missing'];
    }

    /** @return array<string, int> */
    private function loadWatchedDurations(LegacySourceReader $reader, int $legacyUserId, int $legacyCourseId): array
    {
        if (! $reader->tableExists('watched_duration')) {
            return [];
        }

        $rows = $reader->selectAll('watched_duration', static function (Builder $query) use ($legacyUserId, $legacyCourseId): void {
            $query->where('watched_student_id', $legacyUserId)->where('watched_course_id', $legacyCourseId);
        });

        $map = [];
        foreach ($rows as $row) {
            $lessonId = (string) ($row['watched_lesson_id'] ?? '');
            if ($lessonId !== '' && $lessonId !== '0') {
                $map[$lessonId] = max(0, (int) ($row['current_duration'] ?? 0));
            }
        }

        return $map;
    }
}
