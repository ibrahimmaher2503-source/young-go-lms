<?php

declare(strict_types=1);

namespace App\Domain\Migration\Actions;

use App\Domain\Migration\Contracts\EntityImporter;
use App\Domain\Migration\Contracts\LegacySourceReader;
use App\Domain\Migration\Data\EntityImportResult;
use App\Domain\Migration\Data\ImportContext;
use App\Domain\Migration\Data\ImportExceptionData;
use App\Domain\Migration\Data\ImportOptions;
use App\Domain\Migration\Data\RowImportResult;
use App\Domain\Migration\Enums\MigrationBatchStatus;
use App\Domain\Migration\Enums\MigrationRecordStatus;
use App\Domain\Migration\Exceptions\MigrationResumeException;
use App\Domain\Migration\Models\MigrationBatch;
use App\Domain\Migration\Models\MigrationException;
use App\Domain\Migration\Models\MigrationRecord;
use App\Domain\Migration\Support\Checksum;
use App\Domain\Migration\Support\ImporterRegistry;
use App\Domain\Migration\Support\LegacyIdMap;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Orchestrates idempotent, chunked, resumable legacy imports with default dry-run mode and count/rejection reports.
 */
final class RunLegacyImport
{
    public function __construct(
        private readonly LegacySourceReader $reader,
        private readonly ImporterRegistry $registry,
        private readonly LegacyIdMap $idMap,
    ) {}

    /** @return list<EntityImportResult> */
    public function __invoke(ImportOptions $options): array
    {
        return $this->handle($options);
    }

    /** @return list<EntityImportResult> */
    public function handle(ImportOptions $options): array
    {
        $importers = $this->registry->for($options->entity);
        $sourceSystem = $options->sourceSystem ?? Config::string('legacy_migration.source_system', 'legacy_academy');
        $chunkSize = max(1, $options->chunkSize);

        $resumedBatch = null;
        if ($options->resumeBatchId !== null) {
            if ($options->dryRun) {
                throw new MigrationResumeException('Cannot resume a batch while running in dry-run mode.');
            }
            if (count($importers) !== 1) {
                throw new MigrationResumeException('Resuming a batch requires specifying the exact entity, not "all".');
            }

            $resumedBatch = MigrationBatch::query()->find($options->resumeBatchId);
            if ($resumedBatch === null) {
                throw new MigrationResumeException("Migration batch #{$options->resumeBatchId} was not found.");
            }
            if ($resumedBatch->status === MigrationBatchStatus::Completed) {
                throw new MigrationResumeException("Migration batch #{$options->resumeBatchId} is already completed.");
            }
            if ($resumedBatch->entity !== $importers[0]->entity()->value) {
                throw new MigrationResumeException(
                    "Batch #{$options->resumeBatchId} belongs to entity '{$resumedBatch->entity}', not '{$importers[0]->entity()->value}'.",
                );
            }
        }

        $results = [];
        foreach ($importers as $importer) {
            $results[] = $this->runEntity(
                $importer,
                $options,
                $sourceSystem,
                $chunkSize,
                $resumedBatch,
            );
        }

        return $results;
    }

    private function runEntity(
        EntityImporter $importer,
        ImportOptions $options,
        string $sourceSystem,
        int $chunkSize,
        ?MigrationBatch $resumedBatch,
    ): EntityImportResult {
        $entity = $importer->entity();
        $sourceTable = $importer->sourceTable();
        $sourceCount = $importer->sourceCount($this->reader);

        $afterSourceId = $resumedBatch?->last_source_id;
        $processed = $resumedBatch !== null ? $resumedBatch->processed_count : 0;
        $imported = $resumedBatch !== null ? $resumedBatch->imported_count : 0;
        $updated = $resumedBatch !== null ? $resumedBatch->updated_count : 0;
        $unchanged = $resumedBatch !== null ? $resumedBatch->unchanged_count : 0;
        $skipped = $resumedBatch !== null ? $resumedBatch->skipped_count : 0;
        $rejected = $resumedBatch !== null ? $resumedBatch->rejected_count : 0;
        $exceptionsCount = $resumedBatch !== null ? $resumedBatch->exceptions_count : 0;
        $lastSourceId = $afterSourceId;
        $rejectedRows = [];
        $rejectionSummary = [];
        $exceptionSummary = [];

        $batch = null;
        if (! $options->dryRun) {
            $batch = MigrationBatch::query()->create([
                'source_system' => $sourceSystem,
                'entity' => $entity->value,
                'mode' => 'apply',
                'status' => MigrationBatchStatus::Running,
                'resumed_from_batch_id' => $resumedBatch?->getKey(),
                'chunk_size' => $chunkSize,
                'last_source_id' => $lastSourceId,
                'source_count' => $sourceCount,
                'processed_count' => $processed,
                'imported_count' => $imported,
                'updated_count' => $updated,
                'unchanged_count' => $unchanged,
                'skipped_count' => $skipped,
                'rejected_count' => $rejected,
                'exceptions_count' => $exceptionsCount,
                'started_at' => now()->utc(),
            ]);
        }

        $defaultLocale = Config::string('legacy_migration.default_locale', 'ar');
        $defaultMarket = Config::string('legacy_migration.default_market', 'EG');
        $defaultCurrency = Config::string('legacy_migration.default_currency', 'EGP');
        $defaultTimezone = Config::string('legacy_migration.default_timezone', 'Africa/Cairo');
        $rawStorageRoot = Config::get('legacy_migration.storage_root');
        $storageRoot = is_string($rawStorageRoot) && $rawStorageRoot !== '' ? $rawStorageRoot : null;

        try {
            $importer->iterateSource(
                $this->reader,
                $chunkSize,
                $afterSourceId,
                function (array $chunk) use (
                    $importer,
                    $options,
                    $sourceSystem,
                    $sourceTable,
                    $entity,
                    $batch,
                    $defaultLocale,
                    $defaultMarket,
                    $defaultCurrency,
                    $defaultTimezone,
                    $storageRoot,
                    &$processed,
                    &$imported,
                    &$updated,
                    &$unchanged,
                    &$skipped,
                    &$rejected,
                    &$exceptionsCount,
                    &$lastSourceId,
                    &$rejectedRows,
                    &$rejectionSummary,
                    &$exceptionSummary,
                ): void {
                    foreach ($chunk as $row) {
                        $sourceId = $importer->extractSourceId($row);

                        if ($options->beforeRow !== null) {
                            ($options->beforeRow)($entity->value, $sourceId, $row);
                        }

                        $checksum = Checksum::of($importer->checksumPayload($row));
                        $existing = $this->idMap->findRecord($sourceSystem, $entity, $sourceId);

                        if ($existing !== null
                            && $existing->checksum === $checksum
                            && in_array($existing->status, [MigrationRecordStatus::Imported, MigrationRecordStatus::Updated], true)
                            && $existing->target_id !== null
                        ) {
                            if ($options->dryRun && $existing->target_type !== null) {
                                $this->idMap->rememberShadow(
                                    $entity,
                                    $sourceId,
                                    $existing->target_type,
                                    $existing->target_id,
                                    is_array($existing->metadata) ? $existing->metadata : [],
                                );
                            }
                            $unchanged++;
                            $processed++;
                            $lastSourceId = $sourceId;

                            continue;
                        }

                        $context = new ImportContext(
                            sourceSystem: $sourceSystem,
                            dryRun: $options->dryRun,
                            reader: $this->reader,
                            idMap: $this->idMap,
                            existingRecord: $existing,
                            checksum: $checksum,
                            defaultLocale: $defaultLocale,
                            defaultMarket: $defaultMarket,
                            defaultCurrency: $defaultCurrency,
                            defaultTimezone: $defaultTimezone,
                            storageRoot: $storageRoot,
                        );

                        if ($options->dryRun) {
                            $result = $importer->importRow($row, $context);
                        } else {
                            $result = DB::transaction(static fn (): RowImportResult => $importer->importRow($row, $context));
                        }

                        $this->tallyResult(
                            $result,
                            $sourceId,
                            $imported,
                            $updated,
                            $unchanged,
                            $skipped,
                            $rejected,
                            $exceptionsCount,
                            $rejectedRows,
                            $rejectionSummary,
                            $exceptionSummary,
                        );

                        if (! $options->dryRun && $batch !== null) {
                            $this->persistRowTracking($batch, $sourceSystem, $entity->value, $sourceTable, $sourceId, $checksum, $result);
                        }

                        $processed++;
                        $lastSourceId = $sourceId;
                    }

                    if ($batch !== null) {
                        $batch->forceFill([
                            'last_source_id' => $lastSourceId,
                            'processed_count' => $processed,
                            'imported_count' => $imported,
                            'updated_count' => $updated,
                            'unchanged_count' => $unchanged,
                            'skipped_count' => $skipped,
                            'rejected_count' => $rejected,
                            'exceptions_count' => $exceptionsCount,
                        ])->save();
                    }
                },
            );
        } catch (Throwable $exception) {
            if ($batch !== null) {
                $batch->forceFill([
                    'status' => MigrationBatchStatus::Failed,
                    'last_source_id' => $lastSourceId,
                    'processed_count' => $processed,
                    'imported_count' => $imported,
                    'updated_count' => $updated,
                    'unchanged_count' => $unchanged,
                    'skipped_count' => $skipped,
                    'rejected_count' => $rejected,
                    'exceptions_count' => $exceptionsCount,
                    'error_message' => mb_substr($exception->getMessage(), 0, 2000),
                    'finished_at' => now()->utc(),
                ])->save();
            }

            throw $exception;
        }

        $status = $options->dryRun ? MigrationBatchStatus::DryRun->value : MigrationBatchStatus::Completed->value;

        $report = new EntityImportResult(
            entity: $entity->value,
            sourceTable: $sourceTable,
            dryRun: $options->dryRun,
            batchId: $batch?->getKey() !== null ? (int) $batch->getKey() : null,
            resumedFromBatchId: $resumedBatch?->getKey() !== null ? (int) $resumedBatch->getKey() : null,
            status: $status,
            sourceCount: $sourceCount,
            processedCount: $processed,
            importedCount: $imported,
            updatedCount: $updated,
            unchangedCount: $unchanged,
            skippedCount: $skipped,
            rejectedCount: $rejected,
            exceptionsCount: $exceptionsCount,
            lastSourceId: $lastSourceId,
            rejectedRows: $rejectedRows,
            rejectionSummary: $rejectionSummary,
            exceptionSummary: $exceptionSummary,
        );

        if ($batch !== null) {
            $batch->forceFill([
                'status' => MigrationBatchStatus::Completed,
                'last_source_id' => $lastSourceId,
                'processed_count' => $processed,
                'imported_count' => $imported,
                'updated_count' => $updated,
                'unchanged_count' => $unchanged,
                'skipped_count' => $skipped,
                'rejected_count' => $rejected,
                'exceptions_count' => $exceptionsCount,
                'report' => $report->toArray(),
                'finished_at' => now()->utc(),
            ])->save();
        }

        return $report;
    }

    /**
     * @param  list<array{source_id: string, code: string, reason: string}>  $rejectedRows
     * @param  array<string, int>  $rejectionSummary
     * @param  array<string, int>  $exceptionSummary
     */
    private function tallyResult(
        RowImportResult $result,
        string $sourceId,
        int &$imported,
        int &$updated,
        int &$unchanged,
        int &$skipped,
        int &$rejected,
        int &$exceptionsCount,
        array &$rejectedRows,
        array &$rejectionSummary,
        array &$exceptionSummary,
    ): void {
        match ($result->outcome) {
            RowImportResult::OUTCOME_IMPORTED => $imported++,
            RowImportResult::OUTCOME_UPDATED => $updated++,
            RowImportResult::OUTCOME_UNCHANGED => $unchanged++,
            RowImportResult::OUTCOME_SKIPPED => $skipped++,
            RowImportResult::OUTCOME_REJECTED => $rejected++,
            default => null,
        };

        if ($result->outcome === RowImportResult::OUTCOME_REJECTED) {
            $code = $result->rejectionCode ?? 'rejected';
            $reason = $result->rejectionReason ?? 'Rejected without details.';
            $rejectedRows[] = ['source_id' => $sourceId, 'code' => $code, 'reason' => $reason];
            $rejectionSummary[$code] = ($rejectionSummary[$code] ?? 0) + 1;
        }

        foreach ($result->exceptions as $exception) {
            $exceptionsCount++;
            $exceptionSummary[$exception->code] = ($exceptionSummary[$exception->code] ?? 0) + 1;
        }
    }

    private function persistRowTracking(
        MigrationBatch $batch,
        string $sourceSystem,
        string $entity,
        string $sourceTable,
        string $sourceId,
        string $checksum,
        RowImportResult $result,
    ): void {
        $recordStatus = match ($result->outcome) {
            RowImportResult::OUTCOME_IMPORTED => MigrationRecordStatus::Imported,
            RowImportResult::OUTCOME_UPDATED, RowImportResult::OUTCOME_UNCHANGED => MigrationRecordStatus::Updated,
            RowImportResult::OUTCOME_SKIPPED => MigrationRecordStatus::Skipped,
            default => MigrationRecordStatus::Rejected,
        };

        $reason = $result->rejectionReason !== null
            ? mb_substr(($result->rejectionCode !== null ? '['.$result->rejectionCode.'] ' : '').$result->rejectionReason, 0, 255)
            : null;

        MigrationRecord::query()->updateOrCreate(
            ['source_system' => $sourceSystem, 'entity' => $entity, 'source_id' => $sourceId],
            [
                'source_table' => $sourceTable,
                'target_type' => $result->targetType,
                'target_id' => $result->targetId,
                'checksum' => $checksum,
                'status' => $recordStatus,
                'rejection_reason' => $reason,
                'last_batch_id' => $batch->getKey(),
                'metadata' => $result->metadata !== [] ? $result->metadata : null,
                'imported_at' => in_array($recordStatus, [MigrationRecordStatus::Imported, MigrationRecordStatus::Updated], true)
                    ? now()->utc()
                    : null,
            ],
        );

        foreach ($result->exceptions as $exception) {
            $this->recordException($batch, $sourceSystem, $entity, $sourceTable, $sourceId, $exception);
        }
    }

    private function recordException(
        MigrationBatch $batch,
        string $sourceSystem,
        string $entity,
        string $sourceTable,
        string $sourceId,
        ImportExceptionData $exception,
    ): void {
        MigrationException::query()->create([
            'batch_id' => $batch->getKey(),
            'source_system' => $sourceSystem,
            'entity' => $entity,
            'source_table' => $sourceTable,
            'source_id' => $sourceId,
            'code' => $exception->code,
            'severity' => $exception->severity,
            'message' => mb_substr($exception->message, 0, 500),
            'context' => $exception->context !== [] ? $exception->context : null,
        ]);
    }
}
