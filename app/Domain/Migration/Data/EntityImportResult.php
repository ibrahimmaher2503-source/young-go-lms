<?php

declare(strict_types=1);

namespace App\Domain\Migration\Data;

final readonly class EntityImportResult
{
    /**
     * @param  list<array{source_id: string, code: string, reason: string}>  $rejectedRows
     * @param  array<string, int>  $rejectionSummary
     * @param  array<string, int>  $exceptionSummary
     */
    public function __construct(
        public string $entity,
        public string $sourceTable,
        public bool $dryRun,
        public ?int $batchId,
        public ?int $resumedFromBatchId,
        public string $status,
        public int $sourceCount,
        public int $processedCount,
        public int $importedCount,
        public int $updatedCount,
        public int $unchangedCount,
        public int $skippedCount,
        public int $rejectedCount,
        public int $exceptionsCount,
        public ?string $lastSourceId,
        public array $rejectedRows = [],
        public array $rejectionSummary = [],
        public array $exceptionSummary = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'entity' => $this->entity,
            'source_table' => $this->sourceTable,
            'dry_run' => $this->dryRun,
            'batch_id' => $this->batchId,
            'resumed_from_batch_id' => $this->resumedFromBatchId,
            'status' => $this->status,
            'source_count' => $this->sourceCount,
            'processed_count' => $this->processedCount,
            'imported_count' => $this->importedCount,
            'updated_count' => $this->updatedCount,
            'unchanged_count' => $this->unchangedCount,
            'skipped_count' => $this->skippedCount,
            'rejected_count' => $this->rejectedCount,
            'exceptions_count' => $this->exceptionsCount,
            'last_source_id' => $this->lastSourceId,
            'rejected_rows' => $this->rejectedRows,
            'rejection_summary' => $this->rejectionSummary,
            'exception_summary' => $this->exceptionSummary,
        ];
    }
}
