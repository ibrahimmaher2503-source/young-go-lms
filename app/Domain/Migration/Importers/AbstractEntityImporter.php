<?php

declare(strict_types=1);

namespace App\Domain\Migration\Importers;

use App\Domain\Migration\Contracts\EntityImporter;
use App\Domain\Migration\Contracts\LegacySourceReader;
use App\Domain\Migration\Data\EntityVerificationResult;
use App\Domain\Migration\Data\SampleCheckResult;
use App\Domain\Migration\Support\LegacyIdMap;
use Closure;

/**
 * Shared chunking, deterministic source ID extraction, and count/sample verification scaffolding for entity importers.
 */
abstract class AbstractEntityImporter implements EntityImporter
{
    public function sourceIdColumn(): string
    {
        return 'id';
    }

    public function tableIsOptional(): bool
    {
        return false;
    }

    protected function sourceScope(): ?Closure
    {
        return null;
    }

    public function sourceCount(LegacySourceReader $reader): int
    {
        if (! $reader->tableExists($this->sourceTable())) {
            return 0;
        }

        return $reader->count($this->sourceTable(), $this->sourceScope());
    }

    public function iterateSource(
        LegacySourceReader $reader,
        int $chunkSize,
        ?string $afterSourceId,
        Closure $onChunk,
    ): void {
        if (! $reader->tableExists($this->sourceTable())) {
            return;
        }

        $reader->chunkById(
            $this->sourceTable(),
            $this->sourceIdColumn(),
            $chunkSize,
            $afterSourceId,
            $onChunk,
            $this->sourceScope(),
        );
    }

    public function extractSourceId(array $row): string
    {
        return (string) ($row[$this->sourceIdColumn()] ?? '');
    }

    public function checksumPayload(array $row): array
    {
        return $row;
    }

    public function verifyTarget(LegacySourceReader $reader, LegacyIdMap $idMap, int $sampleSize): EntityVerificationResult
    {
        $sourceSystem = config('legacy_migration.source_system', 'legacy_academy');
        $tableExists = $reader->tableExists($this->sourceTable());

        if (! $tableExists && $this->tableIsOptional()) {
            return new EntityVerificationResult(
                entity: $this->entity()->value,
                sourceTable: $this->sourceTable(),
                sourceCount: 0,
                mappedCount: 0,
                skippedCount: 0,
                rejectedCount: 0,
                targetFoundCount: 0,
                countsReconcile: true,
                samplesChecked: 0,
                samplesPassed: 0,
                samplesFailed: 0,
                samples: [],
                notes: ["Optional legacy table '{$this->sourceTable()}' is absent on the connected source."],
            );
        }

        $sourceCount = $this->sourceCount($reader);
        $counts = $idMap->countsByStatus((string) $sourceSystem, $this->entity());
        $mappedRecords = $idMap->mappedRecords((string) $sourceSystem, $this->entity());

        $targetIds = $mappedRecords->pluck('target_id')->filter()->map(static fn (mixed $id): int => (int) $id)->values()->all();
        $existingIds = $this->existingTargetIds($targetIds);
        $existingSet = array_flip($existingIds);
        $targetFoundCount = count($existingIds);

        $accounted = $counts['mapped'] + $counts['skipped'] + $counts['rejected'];
        $countsReconcile = ($accounted === $sourceCount) && ($targetFoundCount === $counts['mapped']);

        $sampleRecords = $mappedRecords->take(max(1, $sampleSize));
        $sampleSourceIds = $sampleRecords->pluck('source_id')->map(static fn (mixed $id): string => (string) $id)->values()->all();
        $sourceRows = $tableExists ? $reader->selectSample($this->sourceTable(), $this->sourceIdColumn(), $sampleSourceIds) : [];

        $samples = [];
        $samplesPassed = 0;
        $samplesFailed = 0;

        foreach ($sampleRecords as $record) {
            $sourceId = (string) $record->source_id;
            $targetId = $record->target_id !== null ? (int) $record->target_id : null;
            $mismatches = [];

            if ($targetId === null || ! isset($existingSet[$targetId])) {
                $mismatches[] = 'target_missing';
            } elseif (! isset($sourceRows[$sourceId])) {
                $mismatches[] = 'source_missing';
            } else {
                $mismatches = $this->compareSample($sourceRows[$sourceId], $targetId);
            }

            $passed = $mismatches === [];
            if ($passed) {
                $samplesPassed++;
            } else {
                $samplesFailed++;
            }

            $samples[] = new SampleCheckResult($sourceId, $targetId, $passed, $mismatches);
        }

        return new EntityVerificationResult(
            entity: $this->entity()->value,
            sourceTable: $this->sourceTable(),
            sourceCount: $sourceCount,
            mappedCount: $counts['mapped'],
            skippedCount: $counts['skipped'],
            rejectedCount: $counts['rejected'],
            targetFoundCount: $targetFoundCount,
            countsReconcile: $countsReconcile,
            samplesChecked: count($samples),
            samplesPassed: $samplesPassed,
            samplesFailed: $samplesFailed,
            samples: $samples,
        );
    }

    /**
     * @param  list<int>  $targetIds
     * @return list<int>
     */
    abstract protected function existingTargetIds(array $targetIds): array;

    /**
     * @param  array<string, mixed>  $sourceRow
     * @return list<string>
     */
    abstract protected function compareSample(array $sourceRow, int $targetId): array;
}
