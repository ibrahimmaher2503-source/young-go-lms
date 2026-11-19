<?php

declare(strict_types=1);

namespace App\Domain\Migration\Importers;

use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Entitlements\Queries\LegacyMigrationEntitlementStats;
use App\Domain\Migration\Data\ImportContext;
use App\Domain\Migration\Data\RowImportResult;
use App\Domain\Migration\Enums\LegacyEntity;
use App\Domain\Migration\Support\LegacyDateParser;
use App\Models\Course;
use App\Models\User;

/**
 * Imports legacy enrollment rows exclusively through the Entitlements module (I01).
 */
final class EnrollmentImporter extends AbstractEntityImporter
{
    public const string TARGET_TYPE = 'course_access';

    public function __construct(
        private readonly GrantEntitlement $grantAccess,
        private readonly EntitlementService $accessService,
        private readonly LegacyMigrationEntitlementStats $stats,
    ) {}

    public function entity(): LegacyEntity
    {
        return LegacyEntity::Enrollments;
    }

    public function sourceTable(): string
    {
        return 'enrol';
    }

    public function checksumPayload(array $row): array
    {
        return [
            'id' => $row['id'] ?? null,
            'user_id' => $row['user_id'] ?? null,
            'course_id' => $row['course_id'] ?? null,
            'gifted_by' => $row['gifted_by'] ?? 0,
            'expiry_date' => $row['expiry_date'] ?? null,
            'date_added' => $row['date_added'] ?? null,
        ];
    }

    public function importRow(array $row, ImportContext $context): RowImportResult
    {
        $sourceId = $this->extractSourceId($row);
        $legacyUserId = (int) ($row['user_id'] ?? 0);
        $legacyCourseId = (int) ($row['course_id'] ?? 0);

        $learnerId = $context->mappedId(LegacyEntity::Users, $legacyUserId);
        if ($learnerId === null) {
            return RowImportResult::rejected('missing_user', "Legacy user {$legacyUserId} is not mapped.");
        }

        $courseId = $context->mappedId(LegacyEntity::Courses, $legacyCourseId);
        if ($courseId === null) {
            return RowImportResult::rejected('missing_course', "Legacy course {$legacyCourseId} is not mapped.");
        }

        $startsAt = LegacyDateParser::toUtc($row['date_added'] ?? null) ?? now()->utc();
        $endsAt = LegacyDateParser::toUtc($row['expiry_date'] ?? null);

        if ($endsAt !== null && $endsAt->lte($startsAt)) {
            return RowImportResult::rejected(
                'expiry_before_start',
                'Legacy enrollment expiry_date is earlier than or equal to date_added.',
            );
        }

        $sourceRef = 'legacy:enrol:'.$sourceId;
        $giftedBy = (int) ($row['gifted_by'] ?? 0);
        $reason = $giftedBy > 0
            ? "legacy:enrol:{$sourceId}|gifted_by:{$giftedBy}"
            : "legacy:enrol:{$sourceId}";

        $metadata = [
            'source_ref' => $sourceRef,
            'gifted_by' => $giftedBy,
            'starts_at' => $startsAt->toIso8601String(),
            'ends_at' => $endsAt?->toIso8601String(),
        ];

        if ($context->dryRun) {
            $existingId = null;
            if (! $context->idMap->isShadowId($learnerId) && ! $context->idMap->isShadowId($courseId)) {
                $learner = User::query()->find($learnerId);
                $course = Course::query()->find($courseId);
                if ($learner !== null && $course !== null) {
                    $existingId = $this->accessService->idForSource($learner, $course, EntitlementSource::LegacyMigration, $sourceRef);
                }
            }

            $shadowId = $context->idMap->rememberShadow(
                LegacyEntity::Enrollments,
                $sourceId,
                self::TARGET_TYPE,
                $existingId,
                $metadata,
            );

            return $existingId !== null && $context->existingRecord?->checksum === $context->checksum
                ? RowImportResult::unchanged(self::TARGET_TYPE, $existingId, $metadata)
                : RowImportResult::imported(self::TARGET_TYPE, $shadowId, $metadata);
        }

        $learner = User::query()->find($learnerId);
        $course = Course::query()->find($courseId);
        if ($learner === null || $course === null) {
            return RowImportResult::rejected('missing_target_entity', 'Target learner or course record does not exist.');
        }

        $existingId = $this->accessService->idForSource($learner, $course, EntitlementSource::LegacyMigration, $sourceRef);
        $granted = ($this->grantAccess)(
            $learner,
            $course,
            EntitlementSource::LegacyMigration,
            $sourceRef,
            $startsAt,
            $endsAt,
            null,
            $reason,
        );

        $grantedId = (int) $granted->getKey();

        return $existingId === null
            ? RowImportResult::imported(self::TARGET_TYPE, $grantedId, $metadata)
            : RowImportResult::updated(self::TARGET_TYPE, $grantedId, $metadata);
    }

    protected function existingTargetIds(array $targetIds): array
    {
        return $this->stats->existingIds($targetIds);
    }

    protected function compareSample(array $sourceRow, int $targetId): array
    {
        $summary = $this->stats->findSummaryById($targetId);
        if ($summary === null) {
            return ['target_missing'];
        }

        $expectedRef = 'legacy:enrol:'.$this->extractSourceId($sourceRow);
        if ($summary['source_ref'] !== $expectedRef) {
            return ["source_ref expected '{$expectedRef}', got '{$summary['source_ref']}'"];
        }

        return [];
    }
}
