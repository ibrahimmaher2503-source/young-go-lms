<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Migration\Actions\VerifyLegacyImport;
use App\Domain\Migration\Enums\LegacyEntity;
use Illuminate\Console\Command;
use InvalidArgumentException;
use Throwable;

final class LegacyVerifyCommand extends Command
{
    protected $signature = 'legacy:verify
        {entity=all : Entity key or "all"}
        {--sample=5 : Number of mapped records to sample per entity}
        {--source= : Override source_system identifier}
        {--json : Emit machine-readable JSON verification report}';

    protected $description = 'Compare legacy source counts and sample records against migrated target data';

    public function handle(VerifyLegacyImport $verifier): int
    {
        $entity = (string) $this->argument('entity');
        $sampleSize = max(1, (int) $this->option('sample'));
        $sourceOverride = $this->option('source');
        $sourceSystem = is_string($sourceOverride) && $sourceOverride !== '' ? $sourceOverride : null;

        try {
            $report = $verifier->handle($entity, $sampleSize, $sourceSystem);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage().' Supported: '.implode(', ', LegacyEntity::names()));

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($report->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return $report->passed() ? self::SUCCESS : self::FAILURE;
        }

        $tableRows = [];
        foreach ($report->entities as $entityResult) {
            $tableRows[] = [
                $entityResult->entity,
                $entityResult->sourceCount,
                $entityResult->mappedCount,
                $entityResult->targetFoundCount,
                $entityResult->skippedCount,
                $entityResult->rejectedCount,
                $entityResult->countsReconcile ? 'YES' : 'NO',
                "{$entityResult->samplesPassed}/{$entityResult->samplesChecked}",
                $entityResult->passed() ? 'PASS' : 'FAIL',
            ];
        }

        $this->table([
            'Entity',
            'Source',
            'Mapped',
            'Target',
            'Skipped',
            'Rejected',
            'Counts OK',
            'Samples',
            'Status',
        ], $tableRows);

        foreach ($report->entities as $entityResult) {
            foreach ($entityResult->notes as $note) {
                $this->line("[{$entityResult->entity}] {$note}");
            }

            foreach ($entityResult->samples as $sample) {
                if (! $sample->passed) {
                    $this->error(
                        "[{$entityResult->entity}] Sample source_id={$sample->sourceId} target_id=".($sample->targetId ?? 'null')
                        .' failed: '.implode('; ', $sample->mismatches),
                    );
                }
            }
        }

        return $report->passed() ? self::SUCCESS : self::FAILURE;
    }
}
