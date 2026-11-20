<?php

declare(strict_types=1);

namespace App\Domain\Migration\Data;

use App\Domain\Migration\Contracts\LegacySourceReader;
use App\Domain\Migration\Enums\LegacyEntity;
use App\Domain\Migration\Models\MigrationRecord;
use App\Domain\Migration\Support\LegacyIdMap;

final readonly class ImportContext
{
    public function __construct(
        public string $sourceSystem,
        public bool $dryRun,
        public LegacySourceReader $reader,
        public LegacyIdMap $idMap,
        public ?MigrationRecord $existingRecord,
        public string $checksum,
        public string $defaultLocale,
        public string $defaultMarket,
        public string $defaultCurrency,
        public string $defaultTimezone,
        public ?string $storageRoot = null,
    ) {}

    public function mappedId(LegacyEntity $entity, int|string|null $sourceId): ?int
    {
        if ($sourceId === null || (string) $sourceId === '' || (string) $sourceId === '0') {
            return null;
        }

        return $this->idMap->findTargetId($this->sourceSystem, $entity, (string) $sourceId);
    }

    /** @return array<string, mixed> */
    public function mappedMetadata(LegacyEntity $entity, int|string $sourceId): array
    {
        return $this->idMap->findMetadata($this->sourceSystem, $entity, (string) $sourceId);
    }
}
