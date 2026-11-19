<?php

declare(strict_types=1);

namespace App\Domain\Migration\Importers;

use App\Domain\Migration\Data\ImportContext;
use App\Domain\Migration\Data\ImportExceptionData;
use App\Domain\Migration\Data\RowImportResult;
use App\Domain\Migration\Enums\LegacyEntity;
use App\Domain\Migration\Enums\MigrationExceptionSeverity;
use App\Domain\Migration\Support\ScriptNameMapper;
use App\Models\CourseSection;

final class SectionImporter extends AbstractEntityImporter
{
    public function entity(): LegacyEntity
    {
        return LegacyEntity::Sections;
    }

    public function sourceTable(): string
    {
        return 'section';
    }

    public function checksumPayload(array $row): array
    {
        return [
            'id' => $row['id'] ?? null,
            'title' => $row['title'] ?? null,
            'course_id' => $row['course_id'] ?? null,
            'order' => $row['order'] ?? 0,
            'start_date' => $row['start_date'] ?? null,
            'end_date' => $row['end_date'] ?? null,
            'restricted_by' => $row['restricted_by'] ?? null,
        ];
    }

    public function importRow(array $row, ImportContext $context): RowImportResult
    {
        $sourceId = $this->extractSourceId($row);
        $titleText = trim((string) ($row['title'] ?? ''));

        if ($titleText === '') {
            return RowImportResult::rejected('empty_title', 'Legacy section has an empty title.');
        }

        $legacyCourseId = (int) ($row['course_id'] ?? 0);
        $courseId = $context->mappedId(LegacyEntity::Courses, $legacyCourseId);
        if ($courseId === null) {
            return RowImportResult::rejected('missing_course', "Legacy course {$legacyCourseId} is not mapped.");
        }

        $exceptions = [];
        if (filled($row['start_date'] ?? null) || filled($row['end_date'] ?? null) || filled($row['restricted_by'] ?? null)) {
            $exceptions[] = new ImportExceptionData(
                'section_drip_metadata_unmapped',
                MigrationExceptionSeverity::Warning,
                'Legacy section carries drip date/restriction fields that map at item level in target LMS.',
                [
                    'start_date' => $row['start_date'] ?? null,
                    'end_date' => $row['end_date'] ?? null,
                    'restricted_by' => $row['restricted_by'] ?? null,
                ],
            );
        }

        $courseMeta = $context->mappedMetadata(LegacyEntity::Courses, $legacyCourseId);
        $published = ($courseMeta['status'] ?? '') === 'published';
        $sort = max(0, (int) ($row['order'] ?? 0));
        $existingTargetId = $context->existingRecord?->target_id;

        if ($context->dryRun) {
            $shadowId = $context->idMap->rememberShadow(LegacyEntity::Sections, $sourceId, CourseSection::class, $existingTargetId, ['course_id' => $courseId]);

            return $existingTargetId !== null
                ? RowImportResult::updated(CourseSection::class, $shadowId, ['course_id' => $courseId], $exceptions)
                : RowImportResult::imported(CourseSection::class, $shadowId, ['course_id' => $courseId], $exceptions);
        }

        $section = $existingTargetId !== null ? CourseSection::query()->find($existingTargetId) : null;
        $isNew = $section === null;
        $section ??= new CourseSection;

        $section->forceFill([
            'course_id' => $courseId,
            'title' => ScriptNameMapper::bilingual($titleText, $context->defaultLocale),
            'sort' => $sort,
            'published' => $published,
        ])->save();

        return $isNew
            ? RowImportResult::imported(CourseSection::class, (int) $section->getKey(), ['course_id' => $courseId], $exceptions)
            : RowImportResult::updated(CourseSection::class, (int) $section->getKey(), ['course_id' => $courseId], $exceptions);
    }

    protected function existingTargetIds(array $targetIds): array
    {
        if ($targetIds === []) {
            return [];
        }

        return CourseSection::query()->whereIn('id', $targetIds)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->values()->all();
    }

    protected function compareSample(array $sourceRow, int $targetId): array
    {
        $section = CourseSection::query()->find($targetId);
        if ($section === null) {
            return ['target_missing'];
        }

        $mismatches = [];
        $expectedSort = max(0, (int) ($sourceRow['order'] ?? 0));
        if ((int) $section->sort !== $expectedSort) {
            $mismatches[] = "sort expected {$expectedSort}, got {$section->sort}";
        }

        return $mismatches;
    }
}
