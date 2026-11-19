<?php

declare(strict_types=1);

namespace App\Domain\Migration\Importers;

use App\Domain\Certificates\Certificate;
use App\Domain\Completion\CourseCompletion;
use App\Domain\Migration\Data\ImportContext;
use App\Domain\Migration\Data\RowImportResult;
use App\Domain\Migration\Enums\LegacyEntity;
use App\Domain\Migration\Support\LegacyDateParser;
use App\Models\Course;
use App\Models\User;

final class CertificateImporter extends AbstractEntityImporter
{
    public function entity(): LegacyEntity
    {
        return LegacyEntity::Certificates;
    }

    public function sourceTable(): string
    {
        return 'certificates';
    }

    public function tableIsOptional(): bool
    {
        return true;
    }

    public function checksumPayload(array $row): array
    {
        return [
            'id' => $row['id'] ?? null,
            'student_id' => $row['student_id'] ?? ($row['user_id'] ?? null),
            'course_id' => $row['course_id'] ?? null,
            'shareable_url' => $row['shareable_url'] ?? null,
            'issued_at' => $row['issued_at'] ?? ($row['date_added'] ?? null),
        ];
    }

    public function importRow(array $row, ImportContext $context): RowImportResult
    {
        $sourceId = $this->extractSourceId($row);
        $legacyUserId = (int) ($row['student_id'] ?? ($row['user_id'] ?? 0));
        $legacyCourseId = (int) ($row['course_id'] ?? 0);

        $learnerId = $context->mappedId(LegacyEntity::Users, $legacyUserId);
        if ($learnerId === null) {
            return RowImportResult::rejected('missing_user', "Legacy student {$legacyUserId} is not mapped.");
        }

        $courseId = $context->mappedId(LegacyEntity::Courses, $legacyCourseId);
        if ($courseId === null) {
            return RowImportResult::rejected('missing_course', "Legacy course {$legacyCourseId} is not mapped.");
        }

        $shareable = trim((string) ($row['shareable_url'] ?? ''));
        if ($shareable === '') {
            return RowImportResult::rejected('missing_certificate_token', 'Legacy certificate has an empty shareable_url.');
        }

        $token = str_contains($shareable, '/') ? basename((string) parse_url($shareable, PHP_URL_PATH)) : $shareable;
        $token = trim($token) !== '' ? mb_substr(trim($token), 0, 64) : mb_substr($shareable, 0, 64);
        $issuedAt = LegacyDateParser::toUtc($row['issued_at'] ?? ($row['date_added'] ?? null)) ?? now()->utc();

        $metadata = ['shareable_url' => $shareable, 'token' => $token];
        $existingTargetId = $context->existingRecord?->target_id;

        if ($context->dryRun) {
            $shadowId = $context->idMap->rememberShadow(LegacyEntity::Certificates, $sourceId, Certificate::class, $existingTargetId, $metadata);

            return $existingTargetId !== null
                ? RowImportResult::updated(Certificate::class, $shadowId, $metadata)
                : RowImportResult::imported(Certificate::class, $shadowId, $metadata);
        }

        $learner = User::query()->find($learnerId);
        $course = Course::query()->find($courseId);
        if ($learner === null || $course === null) {
            return RowImportResult::rejected('missing_target_entity', 'Target learner or course record does not exist.');
        }

        $completion = CourseCompletion::query()->firstOrCreate(
            ['course_id' => $courseId, 'learner_id' => $learnerId],
            [
                'completed_at' => $issuedAt,
                'source' => CourseCompletion::MANUAL,
                'reason' => 'legacy_migration',
                'snapshot' => ['legacy_certificate_id' => $sourceId],
            ],
        );

        $existingCert = Certificate::query()->where('course_completion_id', $completion->getKey())->where('active', 1)->first()
            ?? Certificate::query()->where('token', $token)->first();

        $isNew = $existingCert === null;
        $cert = $existingCert ?? new Certificate([
            'course_completion_id' => $completion->getKey(),
            'course_id' => $courseId,
            'learner_id' => $learnerId,
            'number' => 'YG-LEG-'.str_pad($sourceId, 8, '0', STR_PAD_LEFT),
            'token' => $token,
            'active' => 1,
        ]);

        $displayName = trim((string) ($learner->getAttribute('name_ar') ?: $learner->getAttribute('name_en') ?: $learner->getAttribute('name')));
        $cert->forceFill([
            'issued_at' => $issuedAt,
            'completed_on' => $issuedAt->toDateString(),
            'learner_name' => mb_substr($displayName, 0, 191),
            'learner_name_ar' => $learner->getAttribute('name_ar'),
            'learner_name_en' => $learner->getAttribute('name_en'),
            'course_title_ar' => $course->getTranslation('title', 'ar', false) ?: null,
            'course_title_en' => $course->getTranslation('title', 'en', false) ?: null,
        ])->save();

        if ($learner->getAttribute('certificate_names_locked_at') === null) {
            $learner->forceFill(['certificate_names_locked_at' => $issuedAt])->save();
        }

        return $isNew
            ? RowImportResult::imported(Certificate::class, (int) $cert->getKey(), $metadata)
            : RowImportResult::updated(Certificate::class, (int) $cert->getKey(), $metadata);
    }

    protected function existingTargetIds(array $targetIds): array
    {
        if ($targetIds === []) {
            return [];
        }

        return Certificate::query()->whereIn('id', $targetIds)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->values()->all();
    }

    protected function compareSample(array $sourceRow, int $targetId): array
    {
        $cert = Certificate::query()->find($targetId);
        if ($cert === null) {
            return ['target_missing'];
        }

        $shareable = trim((string) ($sourceRow['shareable_url'] ?? ''));
        $expectedToken = str_contains($shareable, '/') ? basename((string) parse_url($shareable, PHP_URL_PATH)) : $shareable;
        if ($expectedToken !== '' && $cert->token !== mb_substr(trim($expectedToken), 0, 64)) {
            return ["token expected '{$expectedToken}', got '{$cert->token}'"];
        }

        return [];
    }
}
