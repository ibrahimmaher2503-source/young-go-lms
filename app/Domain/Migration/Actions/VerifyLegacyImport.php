<?php

declare(strict_types=1);

namespace App\Domain\Migration\Actions;

use App\Domain\Migration\Contracts\LegacySourceReader;
use App\Domain\Migration\Data\VerificationReport;
use App\Domain\Migration\Support\ImporterRegistry;
use App\Domain\Migration\Support\LegacyIdMap;
use Illuminate\Support\Facades\Config;

/**
 * Reconciles source vs mapped vs target counts and verifies deterministic sample records per entity.
 */
final class VerifyLegacyImport
{
    public function __construct(
        private readonly LegacySourceReader $reader,
        private readonly ImporterRegistry $registry,
        private readonly LegacyIdMap $idMap,
    ) {}

    public function __invoke(string $entity = 'all', int $sampleSize = 5, ?string $sourceSystem = null): VerificationReport
    {
        return $this->handle($entity, $sampleSize, $sourceSystem);
    }

    public function handle(string $entity = 'all', int $sampleSize = 5, ?string $sourceSystem = null): VerificationReport
    {
        $resolvedSource = $sourceSystem ?? Config::string('legacy_migration.source_system', 'legacy_academy');
        $resolvedSampleSize = max(1, $sampleSize);

        $results = [];
        foreach ($this->registry->for($entity) as $importer) {
            $results[] = $importer->verifyTarget($this->reader, $this->idMap, $resolvedSampleSize);
        }

        return new VerificationReport($resolvedSource, $results);
    }
}
