<?php

declare(strict_types=1);

namespace App\Domain\Migration\Support;

use App\Domain\Migration\Enums\LegacyEntity;
use App\Domain\Migration\Enums\MigrationRecordStatus;
use App\Domain\Migration\Models\MigrationRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;

/**
 * Source-to-target ID map backed by `migration_records`, with in-memory shadow IDs for zero-write dry runs.
 */
final class LegacyIdMap
{
    /** @var array<string, array<string, array{target_type: string, target_id: int, metadata: array<string, mixed>}>> */
    private array $shadows = [];

    /** @var array<string, array<string, string>> */
    private array $uniqueClaims = [];

    private ?string $activeSourceSystem = null;

    private int $nextShadowId = -1;

    public function clearShadows(?string $sourceSystem = null): void
    {
        $this->shadows = [];
        $this->uniqueClaims = [];
        $this->nextShadowId = -1;
        $this->activeSourceSystem = $sourceSystem;
    }

    public function activeSourceSystem(): string
    {
        return $this->activeSourceSystem ?? Config::string('legacy_migration.source_system', 'legacy_academy');
    }

    public function claimUniqueKey(string $scope, string $value, string $sourceId): bool
    {
        if (isset($this->uniqueClaims[$scope][$value]) && $this->uniqueClaims[$scope][$value] !== $sourceId) {
            return false;
        }

        $this->uniqueClaims[$scope][$value] = $sourceId;

        return true;
    }

    public function findRecord(string $sourceSystem, LegacyEntity $entity, string $sourceId): ?MigrationRecord
    {
        return MigrationRecord::query()
            ->where('source_system', $sourceSystem)
            ->where('entity', $entity->value)
            ->where('source_id', $sourceId)
            ->first();
    }

    public function findTargetId(string $sourceSystem, LegacyEntity $entity, string $sourceId): ?int
    {
        if (isset($this->shadows[$entity->value][$sourceId])) {
            return $this->shadows[$entity->value][$sourceId]['target_id'];
        }

        $record = MigrationRecord::query()
            ->where('source_system', $sourceSystem)
            ->where('entity', $entity->value)
            ->where('source_id', $sourceId)
            ->whereIn('status', [MigrationRecordStatus::Imported->value, MigrationRecordStatus::Updated->value])
            ->first();

        return $record?->target_id;
    }

    /** @return array<string, mixed> */
    public function findMetadata(string $sourceSystem, LegacyEntity $entity, string $sourceId): array
    {
        if (isset($this->shadows[$entity->value][$sourceId])) {
            return $this->shadows[$entity->value][$sourceId]['metadata'];
        }

        $record = $this->findRecord($sourceSystem, $entity, $sourceId);

        return is_array($record?->metadata) ? $record->metadata : [];
    }

    /** @param array<string, mixed> $metadata */
    public function rememberShadow(
        LegacyEntity $entity,
        string $sourceId,
        string $targetType,
        ?int $existingTargetId = null,
        array $metadata = [],
    ): int {
        $targetId = $existingTargetId ?? $this->nextShadowId--;

        $this->shadows[$entity->value][$sourceId] = [
            'target_type' => $targetType,
            'target_id' => $targetId,
            'metadata' => $metadata,
        ];

        return $targetId;
    }

    public function isShadowId(?int $id): bool
    {
        return $id !== null && $id < 0;
    }

    /** @return Collection<int, MigrationRecord> */
    public function mappedRecords(string $sourceSystem, LegacyEntity $entity): Collection
    {
        return MigrationRecord::query()
            ->where('source_system', $sourceSystem)
            ->where('entity', $entity->value)
            ->whereIn('status', [MigrationRecordStatus::Imported->value, MigrationRecordStatus::Updated->value])
            ->whereNotNull('target_id')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{mapped: int, skipped: int, rejected: int}
     */
    public function countsByStatus(string $sourceSystem, LegacyEntity $entity): array
    {
        $rows = MigrationRecord::query()
            ->selectRaw('status, count(*) as total')
            ->where('source_system', $sourceSystem)
            ->where('entity', $entity->value)
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'mapped' => (int) ($rows[MigrationRecordStatus::Imported->value] ?? 0) + (int) ($rows[MigrationRecordStatus::Updated->value] ?? 0),
            'skipped' => (int) ($rows[MigrationRecordStatus::Skipped->value] ?? 0),
            'rejected' => (int) ($rows[MigrationRecordStatus::Rejected->value] ?? 0),
        ];
    }
}
