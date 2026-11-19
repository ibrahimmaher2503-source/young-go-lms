<?php

declare(strict_types=1);

namespace App\Domain\Migration\Importers;

use App\Domain\Migration\Actions\SyncLegacyRedirects;
use App\Domain\Migration\Contracts\LegacySourceReader;
use App\Domain\Migration\Data\ImportContext;
use App\Domain\Migration\Data\RowImportResult;
use App\Domain\Migration\Enums\LegacyEntity;
use App\Domain\Migration\Support\LegacyIdMap;
use App\Domain\Migration\Support\RedirectMapBuilder;
use App\Models\SeoRedirect;
use Closure;

/**
 * Virtual importer generating 301 redirects from mapped legacy categories, courses, instructors, certificates, and static pages.
 */
final class RedirectImporter extends AbstractEntityImporter
{
    public function __construct(
        private readonly RedirectMapBuilder $builder,
        private readonly SyncLegacyRedirects $sync,
        private readonly LegacyIdMap $idMap,
    ) {}

    public function entity(): LegacyEntity
    {
        return LegacyEntity::Redirects;
    }

    public function sourceTable(): string
    {
        return 'virtual:redirects';
    }

    public function sourceIdColumn(): string
    {
        return 'source_id';
    }

    public function tableIsOptional(): bool
    {
        return true;
    }

    public function sourceCount(LegacySourceReader $reader): int
    {
        $sourceSystem = (string) config('legacy_migration.source_system', 'legacy_academy');

        return count($this->builder->build($reader, $this->idMap, $sourceSystem));
    }

    public function iterateSource(
        LegacySourceReader $reader,
        int $chunkSize,
        ?string $afterSourceId,
        Closure $onChunk,
    ): void {
        $sourceSystem = (string) config('legacy_migration.source_system', 'legacy_academy');
        $entries = $this->builder->build($reader, $this->idMap, $sourceSystem);
        usort($entries, static fn (array $a, array $b): int => strcmp($a['source_id'], $b['source_id']));

        if ($afterSourceId !== null) {
            $entries = array_values(array_filter(
                $entries,
                static fn (array $row): bool => strcmp($row['source_id'], $afterSourceId) > 0,
            ));
        }

        foreach (array_chunk($entries, max(1, $chunkSize)) as $chunk) {
            $onChunk($chunk);
        }
    }

    public function checksumPayload(array $row): array
    {
        return [
            'source_id' => $row['source_id'] ?? null,
            'from_path' => $row['from_path'] ?? null,
            'to_path' => $row['to_path'] ?? null,
            'status_code' => $row['status_code'] ?? 301,
        ];
    }

    public function importRow(array $row, ImportContext $context): RowImportResult
    {
        $sourceId = $this->extractSourceId($row);
        $entry = [
            'from_path' => (string) ($row['from_path'] ?? ''),
            'to_path' => (string) ($row['to_path'] ?? ''),
            'status_code' => (int) ($row['status_code'] ?? 301),
        ];

        $outcome = ($this->sync)([$entry], dryRun: $context->dryRun);

        if ($outcome['skipped'] > 0) {
            return RowImportResult::rejected('invalid_redirect_entry', 'Redirect entry failed relative path or self-redirect validation.');
        }

        $targetId = $context->dryRun
            ? $context->idMap->rememberShadow(LegacyEntity::Redirects, $sourceId, SeoRedirect::class, $context->existingRecord?->target_id)
            : (int) SeoRedirect::query()->where('from_path', $entry['from_path'])->value('id');

        if ($outcome['unchanged'] > 0) {
            return RowImportResult::unchanged(SeoRedirect::class, $targetId, $entry);
        }

        return $outcome['updated'] > 0
            ? RowImportResult::updated(SeoRedirect::class, $targetId, $entry)
            : RowImportResult::imported(SeoRedirect::class, $targetId, $entry);
    }

    protected function existingTargetIds(array $targetIds): array
    {
        if ($targetIds === []) {
            return [];
        }

        return SeoRedirect::query()->whereIn('id', $targetIds)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->values()->all();
    }

    protected function compareSample(array $sourceRow, int $targetId): array
    {
        $redirect = SeoRedirect::query()->find($targetId);
        if ($redirect === null) {
            return ['target_missing'];
        }

        $expectedTo = (string) ($sourceRow['to_path'] ?? '');
        if ($redirect->to_path !== $expectedTo) {
            return ["to_path expected '{$expectedTo}', got '{$redirect->to_path}'"];
        }

        return [];
    }

    public function verifyTarget(LegacySourceReader $reader, LegacyIdMap $idMap, int $sampleSize): \App\Domain\Migration\Data\EntityVerificationResult
    {
        $sourceSystem = (string) config('legacy_migration.source_system', 'legacy_academy');
        $entries = $this->builder->build($reader, $idMap, $sourceSystem);
        $bySourceId = [];
        foreach ($entries as $entry) {
            $bySourceId[$entry['source_id']] = $entry;
        }

        $counts = $idMap->countsByStatus($sourceSystem, $this->entity());
        $mappedRecords = $idMap->mappedRecords($sourceSystem, $this->entity());
        $targetIds = $mappedRecords->pluck('target_id')->filter()->map(static fn (mixed $id): int => (int) $id)->values()->all();
        $existingSet = array_flip($this->existingTargetIds($targetIds));

        $samples = [];
        $passed = 0;
        $failed = 0;
        foreach ($mappedRecords->take(max(1, $sampleSize)) as $record) {
            $sourceId = (string) $record->source_id;
            $targetId = $record->target_id !== null ? (int) $record->target_id : null;
            $mismatches = [];

            if ($targetId === null || ! isset($existingSet[$targetId])) {
                $mismatches[] = 'target_missing';
            } elseif (! isset($bySourceId[$sourceId])) {
                $mismatches[] = 'source_missing';
            } else {
                $mismatches = $this->compareSample($bySourceId[$sourceId], $targetId);
            }

            if ($mismatches === []) {
                $passed++;
            } else {
                $failed++;
            }
            $samples[] = new \App\Domain\Migration\Data\SampleCheckResult($sourceId, $targetId, $mismatches === [], $mismatches);
        }

        $sourceCount = count($entries);
        $accounted = $counts['mapped'] + $counts['skipped'] + $counts['rejected'];
        $countsReconcile = ($accounted === $sourceCount) && (count($existingSet) === $counts['mapped']);

        return new \App\Domain\Migration\Data\EntityVerificationResult(
            entity: $this->entity()->value,
            sourceTable: $this->sourceTable(),
            sourceCount: $sourceCount,
            mappedCount: $counts['mapped'],
            skippedCount: $counts['skipped'],
            rejectedCount: $counts['rejected'],
            targetFoundCount: count($existingSet),
            countsReconcile: $countsReconcile,
            samplesChecked: count($samples),
            samplesPassed: $passed,
            samplesFailed: $failed,
            samples: $samples,
        );
    }
}
