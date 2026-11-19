<?php

declare(strict_types=1);

namespace App\Domain\Migration\Importers;

use App\Domain\Migration\Data\ImportContext;
use App\Domain\Migration\Data\ImportExceptionData;
use App\Domain\Migration\Data\RowImportResult;
use App\Domain\Migration\Enums\LegacyEntity;
use App\Domain\Migration\Enums\MigrationExceptionSeverity;
use App\Domain\Migration\Support\ScriptNameMapper;
use App\Models\LessonResource;

final class ResourceImporter extends AbstractEntityImporter
{
    public function entity(): LegacyEntity
    {
        return LegacyEntity::Resources;
    }

    public function sourceTable(): string
    {
        return 'resource_files';
    }

    public function checksumPayload(array $row): array
    {
        return [
            'id' => $row['id'] ?? null,
            'lesson_id' => $row['lesson_id'] ?? null,
            'title' => $row['title'] ?? null,
            'file_name' => $row['file_name'] ?? null,
        ];
    }

    public function importRow(array $row, ImportContext $context): RowImportResult
    {
        $sourceId = $this->extractSourceId($row);
        $fileName = trim((string) ($row['file_name'] ?? ''));

        if ($fileName === '') {
            return RowImportResult::rejected('empty_file_name', 'Legacy resource_files row has an empty file_name.');
        }

        $legacyLessonId = (int) ($row['lesson_id'] ?? 0);
        $lessonId = $context->mappedId(LegacyEntity::Lessons, $legacyLessonId);
        if ($lessonId === null) {
            return RowImportResult::rejected('missing_lesson', "Legacy lesson {$legacyLessonId} is not mapped.");
        }

        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $type = in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)
            ? LessonResource::TYPE_IMAGE
            : LessonResource::TYPE_FILE;

        $exceptions = [];
        $size = null;
        $sha256 = null;
        $relativePath = 'legacy/resources/'.ltrim($fileName, '/');

        if ($context->storageRoot !== null && $context->storageRoot !== '') {
            $fullPath = rtrim($context->storageRoot, '/\\').DIRECTORY_SEPARATOR.ltrim($fileName, '/\\');
            if (is_file($fullPath)) {
                $size = filesize($fullPath) ?: null;
                $sha256 = hash_file('sha256', $fullPath) ?: null;
            } else {
                $exceptions[] = new ImportExceptionData(
                    'missing_resource_file',
                    MigrationExceptionSeverity::Warning,
                    "Legacy resource file '{$fileName}' was not found in storage_root.",
                    ['file_name' => $fileName],
                );
            }
        }

        $isPdf = $extension === 'pdf';
        if ($isPdf) {
            $exceptions[] = new ImportExceptionData(
                'pdf_curriculum_candidate',
                MigrationExceptionSeverity::Warning,
                'PDF resource flagged as candidate for future curriculum document import (AI stays off).',
                ['file_name' => $fileName],
            );
        }

        $titleText = trim((string) ($row['title'] ?? ''));
        if ($titleText === '') {
            $titleText = $fileName;
        }

        $metadata = [
            'is_pdf_candidate' => $isPdf,
            'file_name' => $fileName,
            'sha256' => $sha256,
        ];

        $existingTargetId = $context->existingRecord?->target_id;
        if ($context->dryRun) {
            $shadowId = $context->idMap->rememberShadow(LegacyEntity::Resources, $sourceId, LessonResource::class, $existingTargetId, $metadata);

            return $existingTargetId !== null
                ? RowImportResult::updated(LessonResource::class, $shadowId, $metadata, $exceptions)
                : RowImportResult::imported(LessonResource::class, $shadowId, $metadata, $exceptions);
        }

        $resource = $existingTargetId !== null ? LessonResource::query()->find($existingTargetId) : null;
        $isNew = $resource === null;
        $resource ??= new LessonResource;

        $resource->forceFill([
            'lesson_id' => $lessonId,
            'type' => $type,
            'title' => ScriptNameMapper::bilingual($titleText, $context->defaultLocale),
            'path' => $relativePath,
            'mime' => $this->mimeForExtension($extension),
            'size' => $size,
            'sha256' => $sha256,
            'downloadable' => true,
            'visibility' => 'enrolled',
            'sort' => (int) $sourceId,
        ])->save();

        return $isNew
            ? RowImportResult::imported(LessonResource::class, (int) $resource->getKey(), $metadata, $exceptions)
            : RowImportResult::updated(LessonResource::class, (int) $resource->getKey(), $metadata, $exceptions);
    }

    protected function existingTargetIds(array $targetIds): array
    {
        if ($targetIds === []) {
            return [];
        }

        return LessonResource::query()->whereIn('id', $targetIds)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->values()->all();
    }

    protected function compareSample(array $sourceRow, int $targetId): array
    {
        $resource = LessonResource::query()->find($targetId);
        if ($resource === null) {
            return ['target_missing'];
        }

        $fileName = trim((string) ($sourceRow['file_name'] ?? ''));
        if ($fileName !== '' && ! str_ends_with((string) $resource->path, $fileName)) {
            return ["path expected suffix '{$fileName}', got '{$resource->path}'"];
        }

        return [];
    }

    private function mimeForExtension(string $extension): ?string
    {
        return match ($extension) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            'zip' => 'application/zip',
            default => null,
        };
    }
}
