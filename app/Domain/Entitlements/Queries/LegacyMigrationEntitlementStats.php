<?php

declare(strict_types=1);

namespace App\Domain\Entitlements\Queries;

use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementSource;

/**
 * Read-only verification queries for legacy-migrated access rows. Lives inside Entitlements so I01 stays intact.
 */
final class LegacyMigrationEntitlementStats
{
    public function countBySourceRefPrefix(string $prefix = 'legacy:'): int
    {
        return Entitlement::query()
            ->where('source_type', EntitlementSource::LegacyMigration->value)
            ->where('source_ref', 'like', $prefix.'%')
            ->count();
    }

    /**
     * @param  list<int>  $ids
     * @return list<int>
     */
    public function existingIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Entitlement::query()
            ->where('source_type', EntitlementSource::LegacyMigration->value)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, learner_id: int, course_id: int, source_type: string, source_ref: string, starts_at: string, ends_at: ?string, status: string}|null
     */
    public function findSummaryById(int $id): ?array
    {
        $row = Entitlement::query()
            ->where('source_type', EntitlementSource::LegacyMigration->value)
            ->find($id);

        if ($row === null) {
            return null;
        }

        return [
            'id' => (int) $row->getKey(),
            'learner_id' => (int) $row->learner_id,
            'course_id' => (int) $row->course_id,
            'source_type' => (string) $row->source_type,
            'source_ref' => (string) $row->source_ref,
            'starts_at' => $row->starts_at->toIso8601String(),
            'ends_at' => $row->ends_at?->toIso8601String(),
            'status' => (string) $row->status,
        ];
    }
}
