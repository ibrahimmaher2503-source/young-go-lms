<?php

declare(strict_types=1);

namespace App\Domain\Migration\Importers;

use App\Domain\Content\RichContent;
use App\Domain\Migration\Data\ImportContext;
use App\Domain\Migration\Data\RowImportResult;
use App\Domain\Migration\Enums\LegacyEntity;
use App\Domain\Migration\Support\LegacyDateParser;
use App\Domain\Migration\Support\MediaTierClassifier;
use App\Domain\Migration\Support\ScriptNameMapper;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\MediaAsset;
use Closure;
use Illuminate\Database\Query\Builder;

final class LessonImporter extends AbstractEntityImporter
{
    public function entity(): LegacyEntity
    {
        return LegacyEntity::Lessons;
    }

    public function sourceTable(): string
    {
        return 'lesson';
    }

    protected function sourceScope(): Closure
    {
        return static function (Builder $query): void {
            $query->whereRaw("LOWER(TRIM(COALESCE(lesson_type, ''))) <> 'quiz'");
        };
    }

    public function checksumPayload(array $row): array
    {
        return [
            'id' => $row['id'] ?? null,
            'title' => $row['title'] ?? null,
            'duration' => $row['duration'] ?? null,
            'course_id' => $row['course_id'] ?? null,
            'section_id' => $row['section_id'] ?? null,
            'lesson_type' => $row['lesson_type'] ?? null,
            'video_type' => $row['video_type'] ?? null,
            'cloud_video_id' => $row['cloud_video_id'] ?? null,
            'video_url' => $row['video_url'] ?? null,
            'audio_url' => $row['audio_url'] ?? null,
            'attachment' => $row['attachment'] ?? null,
            'attachment_type' => $row['attachment_type'] ?? null,
            'summary' => $row['summary'] ?? null,
            'is_free' => $row['is_free'] ?? 0,
            'order' => $row['order'] ?? 0,
            'video_url_for_mobile_application' => $row['video_url_for_mobile_application'] ?? null,
        ];
    }

    public function importRow(array $row, ImportContext $context): RowImportResult
    {
        $sourceId = $this->extractSourceId($row);
        $titleText = trim((string) ($row['title'] ?? ''));

        if ($titleText === '') {
            return RowImportResult::rejected('empty_title', 'Legacy lesson has an empty title.');
        }

        $legacyCourseId = (int) ($row['course_id'] ?? 0);
        $legacySectionId = (int) ($row['section_id'] ?? 0);

        $courseId = $context->mappedId(LegacyEntity::Courses, $legacyCourseId);
        if ($courseId === null) {
            return RowImportResult::rejected('missing_course', "Legacy course {$legacyCourseId} is not mapped.");
        }

        $sectionId = $context->mappedId(LegacyEntity::Sections, $legacySectionId);
        if ($sectionId === null) {
            return RowImportResult::rejected('missing_section', "Legacy section {$legacySectionId} is not mapped.");
        }

        $courseMeta = $context->mappedMetadata(LegacyEntity::Courses, $legacyCourseId);
        $courseIsPaid = (bool) ($courseMeta['is_paid'] ?? false);
        $coursePublished = ($courseMeta['status'] ?? '') === 'published';

        $media = MediaTierClassifier::classify($row, $courseIsPaid);
        $durationSeconds = LegacyDateParser::durationToSeconds($row['duration'] ?? null);
        $sort = max(0, (int) ($row['order'] ?? 0));
        $existingTargetId = $context->existingRecord?->target_id;

        $metadata = [
            'provider' => $media['provider'],
            'protection_tier' => $media['protection_tier'],
            'media_status' => $media['media_status'],
            'sort' => $sort,
        ];

        if ($context->dryRun) {
            $shadowId = $context->idMap->rememberShadow(LegacyEntity::Lessons, $sourceId, Lesson::class, $existingTargetId, $metadata);

            return $existingTargetId !== null
                ? RowImportResult::updated(Lesson::class, $shadowId, $metadata, $media['exceptions'])
                : RowImportResult::imported(Lesson::class, $shadowId, $metadata, $media['exceptions']);
        }

        $lesson = $existingTargetId !== null ? Lesson::query()->find($existingTargetId) : null;
        $isNew = $lesson === null;
        $lesson ??= new Lesson;

        $summaryText = trim((string) ($row['summary'] ?? ''));
        $richContent = null;
        if ($media['lesson_type'] === 'text') {
            $rawText = trim(strip_tags((string) ($media['metadata']['text'] ?? $summaryText)));
            if ($rawText !== '') {
                $richContent = RichContent::clean([
                    'type' => 'doc',
                    'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => $rawText]]]],
                ]);
            }
        }

        $lesson->forceFill([
            'type' => $media['lesson_type'],
            'title' => ScriptNameMapper::bilingual($titleText, $context->defaultLocale),
            'description' => $summaryText !== '' ? ScriptNameMapper::bilingual(strip_tags($summaryText), $context->defaultLocale) : null,
            'content' => $richContent,
            'duration_seconds' => $durationSeconds,
            'is_preview' => (int) ($row['is_free'] ?? 0) === 1,
        ]);

        if ($isNew && ($createdAt = LegacyDateParser::toUtc($row['date_added'] ?? null)) !== null) {
            $lesson->created_at = $createdAt->toMutable();
        }
        $lesson->save();

        // ED-002: curriculum_items is the single ordering source.
        $item = CurriculumItem::query()->updateOrCreate(
            ['itemable_type' => (new Lesson)->getMorphClass(), 'itemable_id' => $lesson->getKey()],
            [
                'course_id' => $courseId,
                'section_id' => $sectionId,
                'sort' => $sort,
                'status' => $coursePublished ? CurriculumItem::STATUS_PUBLISHED : CurriculumItem::STATUS_DRAFT,
                'visibility' => 'enrolled',
                'unlock_rule' => 'none',
                'required' => true,
                'ai_enabled' => false,
            ],
        );

        $asset = MediaAsset::query()->updateOrCreate(
            ['lesson_id' => $lesson->getKey()],
            [
                'provider' => $media['provider'],
                'kind' => $media['kind'],
                'external_id' => $media['external_id'],
                'url' => $media['url'],
                'path' => $media['path'],
                'protection_tier' => $media['protection_tier'],
                'exception_reason' => $media['exception_reason'],
                'duration_seconds' => $durationSeconds,
                'status' => 'ready',
                'metadata' => $media['metadata'],
            ],
        );

        $metadata['curriculum_item_id'] = (int) $item->getKey();
        $metadata['media_asset_id'] = (int) $asset->getKey();

        return $isNew
            ? RowImportResult::imported(Lesson::class, (int) $lesson->getKey(), $metadata, $media['exceptions'])
            : RowImportResult::updated(Lesson::class, (int) $lesson->getKey(), $metadata, $media['exceptions']);
    }

    protected function existingTargetIds(array $targetIds): array
    {
        if ($targetIds === []) {
            return [];
        }

        return Lesson::query()->whereIn('id', $targetIds)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->values()->all();
    }

    protected function compareSample(array $sourceRow, int $targetId): array
    {
        $lesson = Lesson::query()->with(['item', 'media'])->find($targetId);
        if ($lesson === null) {
            return ['target_missing'];
        }

        $mismatches = [];
        $expectedSort = max(0, (int) ($sourceRow['order'] ?? 0));
        if ($lesson->item === null) {
            $mismatches[] = 'curriculum_item_missing';
        } elseif ((int) $lesson->item->sort !== $expectedSort) {
            $mismatches[] = "curriculum_item.sort expected {$expectedSort}, got {$lesson->item->sort}";
        }

        if ($lesson->media === null) {
            $mismatches[] = 'media_asset_missing';
        }

        return $mismatches;
    }
}
