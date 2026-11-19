<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Migration\Actions\RunLegacyImport;
use App\Domain\Migration\Data\ImportOptions;
use App\Domain\Migration\Enums\LegacyEntity;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

final class LegacyImportCommand extends Command
{
    protected $signature = 'legacy:import
        {entity=all : Entity key (all, or one of: users, categories, courses, sections, lessons, resources, quizzes, live_sessions, enrollments, progress, quiz_results, orders, reviews, certificates, redirects)}
        {--dry-run : Simulate the import without writing any target or tracking records (default)}
        {--apply : Persist migrated records to the target database}
        {--chunk=100 : Number of legacy rows per batch chunk}
        {--resume= : Resume a failed batch ID (requires single entity and --apply)}
        {--source= : Override source_system identifier}
        {--json : Emit machine-readable JSON report}';

    protected $description = 'Run the idempotent legacy LMS importer (--dry-run is the default)';

    public function handle(RunLegacyImport $runner): int
    {
        $dryRunOption = (bool) $this->option('dry-run');
        $applyOption = (bool) $this->option('apply');

        if ($dryRunOption && $applyOption) {
            $this->error(__('migration.errors.mutually_exclusive_mode'));

            return self::FAILURE;
        }

        $dryRun = ! $applyOption;
        $entity = (string) $this->argument('entity');
        $chunkSize = max(1, (int) $this->option('chunk'));
        $resumeRaw = $this->option('resume');
        $resumeBatchId = $resumeRaw !== null && $resumeRaw !== '' ? (int) $resumeRaw : null;
        $sourceOverride = $this->option('source');
        $sourceSystem = is_string($sourceOverride) && $sourceOverride !== '' ? $sourceOverride : null;

        try {
            $results = $runner->handle(new ImportOptions(
                entity: $entity,
                dryRun: $dryRun,
                chunkSize: $chunkSize,
                resumeBatchId: $resumeBatchId,
                sourceSystem: $sourceSystem,
            ));
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage().' Supported: '.implode(', ', LegacyEntity::names()));

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode([
                'dry_run' => $dryRun,
                'results' => array_map(static fn ($r): array => $r->toArray(), $results),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info($dryRun ? __('migration.mode.dry_run_banner') : __('migration.mode.apply_banner'));

        $rows = [];
        foreach ($results as $result) {
            $rows[] = [
                $result->entity,
                $result->dryRun ? 'dry_run' : 'apply',
                $result->batchId ?? '-',
                $result->sourceCount,
                $result->importedCount,
                $result->updatedCount,
                $result->unchangedCount,
                $result->skippedCount,
                $result->rejectedCount,
                $result->exceptionsCount,
                $result->lastSourceId ?? '-',
            ];
        }

        $this->table([
            'Entity',
            'Mode',
            'Batch',
            'Source',
            'Imported',
            'Updated',
            'Unchanged',
            'Skipped',
            'Rejected',
            'Exceptions',
            'Checkpoint',
        ], $rows);

        foreach ($results as $result) {
            if ($result->rejectedRows !== []) {
                $this->warn("Rejected rows for {$result->entity}:");
                $this->table(
                    ['Source ID', 'Code', 'Reason'],
                    array_map(static fn (array $r): array => [$r['source_id'], $r['code'], $r['reason']], $result->rejectedRows),
                );
            }

            if ($result->exceptionSummary !== []) {
                $parts = [];
                foreach ($result->exceptionSummary as $code => $count) {
                    $parts[] = "{$code}={$count}";
                }
                $this->line("Exceptions ({$result->entity}): ".implode(', ', $parts));
            }
        }

        return self::SUCCESS;
    }
}
