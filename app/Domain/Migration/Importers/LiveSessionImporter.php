<?php

declare(strict_types=1);

namespace App\Domain\Migration\Importers;

use App\Domain\Migration\Data\ImportContext;
use App\Domain\Migration\Data\ImportExceptionData;
use App\Domain\Migration\Data\RowImportResult;
use App\Domain\Migration\Enums\LegacyEntity;
use App\Domain\Migration\Enums\MigrationExceptionSeverity;
use App\Domain\Migration\Support\LegacyDateParser;
use App\Domain\Migration\Support\ScriptNameMapper;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\LiveSession;

final class LiveSessionImporter extends AbstractEntityImporter
{
    public function entity(): LegacyEntity
    {
        return LegacyEntity::LiveSessions;
    }

    public function sourceTable(): string
    {
        return 'bbb_meetings';
    }

    public function tableIsOptional(): bool
    {
        return true;
    }

    public function checksumPayload(array $row): array
    {
        return [
            'id' => $row['id'] ?? null,
            'course_id' => $row['course_id'] ?? null,
            'meeting_id' => $row['meeting_id'] ?? null,
            'instructions' => $row['instructions'] ?? null,
            'created_at' => $row['created_at'] ?? null,
        ];
    }

    public function importRow(array $row, ImportContext $context): RowImportResult
    {
        $sourceId = $this->extractSourceId($row);
        $legacyCourseId = (int) ($row['course_id'] ?? 0);
        $courseId = $context->mappedId(LegacyEntity::Courses, $legacyCourseId);

        if ($courseId === null) {
            return RowImportResult::rejected('missing_course', "Legacy course {$legacyCourseId} is not mapped.");
        }

        $courseMeta = $context->mappedMetadata(LegacyEntity::Courses, $legacyCourseId);
        $coursePublished = ($courseMeta['status'] ?? '') === 'published';

        $sectionId = null;
        if (! $context->idMap->isShadowId($courseId)) {
            $sectionId = CourseSection::query()->where('course_id', $courseId)->orderBy('sort')->orderBy('id')->value('id');
            if ($sectionId === null) {
                return RowImportResult::rejected('missing_course_section', "Course {$courseId} has no section to attach live session.");
            }
        }

        $exceptions = [];
        $startsAt = LegacyDateParser::toUtc($row['starts_at'] ?? ($row['created_at'] ?? null));
        if ($startsAt === null) {
            $startsAt = now()->utc();
            $exceptions[] = new ImportExceptionData(
                'live_schedule_missing',
                MigrationExceptionSeverity::Warning,
                'Legacy bbb_meetings row has no schedule timestamp; defaulted to import timestamp.',
            );
        }

        $endsAt = LegacyDateParser::toUtc($row['ends_at'] ?? null) ?? $startsAt->addHour();
        $meetingId = trim((string) ($row['meeting_id'] ?? ''));
        $titleText = $meetingId !== '' ? 'Live Session '.$meetingId : 'Live Session '.$sourceId;
        $instructions = trim((string) ($row['instructions'] ?? ''));

        $existingTargetId = $context->existingRecord?->target_id;
        if ($context->dryRun) {
            $shadowId = $context->idMap->rememberShadow(LegacyEntity::LiveSessions, $sourceId, LiveSession::class, $existingTargetId);

            return $existingTargetId !== null
                ? RowImportResult::updated(LiveSession::class, $shadowId, ['provider' => 'bbb'], $exceptions)
                : RowImportResult::imported(LiveSession::class, $shadowId, ['provider' => 'bbb'], $exceptions);
        }

        $session = $existingTargetId !== null ? LiveSession::query()->find($existingTargetId) : null;
        $isNew = $session === null;
        $session ??= new LiveSession;

        $session->forceFill([
            'course_id' => $courseId,
            'title' => ScriptNameMapper::bilingual($titleText, $context->defaultLocale),
            'description' => $instructions !== '' ? ScriptNameMapper::bilingual($instructions, $context->defaultLocale) : null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'timezone' => $context->defaultTimezone,
            'provider' => 'bbb',
            'meeting_url' => $meetingId !== '' ? 'https://meet.example.test/bbb/'.rawurlencode($meetingId) : null,
            'status' => LiveSession::SCHEDULED,
        ])->save();

        $nextSort = ((int) CurriculumItem::query()->where('course_id', $courseId)->where('section_id', $sectionId)->max('sort')) + 1;
        $existingItem = CurriculumItem::query()
            ->where('itemable_type', (new LiveSession)->getMorphClass())
            ->where('itemable_id', $session->getKey())
            ->first();

        $item = CurriculumItem::query()->updateOrCreate(
            ['itemable_type' => (new LiveSession)->getMorphClass(), 'itemable_id' => $session->getKey()],
            [
                'course_id' => $courseId,
                'section_id' => (int) $sectionId,
                'sort' => $existingItem !== null ? $existingItem->sort : $nextSort,
                'status' => $coursePublished ? CurriculumItem::STATUS_PUBLISHED : CurriculumItem::STATUS_DRAFT,
                'visibility' => 'enrolled',
                'unlock_rule' => 'none',
                'required' => false,
                'ai_enabled' => false,
            ],
        );

        return $isNew
            ? RowImportResult::imported(LiveSession::class, (int) $session->getKey(), ['curriculum_item_id' => (int) $item->getKey()], $exceptions)
            : RowImportResult::updated(LiveSession::class, (int) $session->getKey(), ['curriculum_item_id' => (int) $item->getKey()], $exceptions);
    }

    protected function existingTargetIds(array $targetIds): array
    {
        if ($targetIds === []) {
            return [];
        }

        return LiveSession::query()->whereIn('id', $targetIds)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->values()->all();
    }

    protected function compareSample(array $sourceRow, int $targetId): array
    {
        return LiveSession::query()->whereKey($targetId)->exists() ? [] : ['target_missing'];
    }
}
