<?php

declare(strict_types=1);

namespace App\Domain\Migration\Importers;

use App\Domain\Engagement\CourseReview;
use App\Domain\Engagement\ReviewerName;
use App\Domain\Engagement\ReviewStatus;
use App\Domain\Migration\Data\ImportContext;
use App\Domain\Migration\Data\RowImportResult;
use App\Domain\Migration\Enums\LegacyEntity;
use App\Domain\Migration\Support\LegacyDateParser;
use App\Models\User;

final class ReviewImporter extends AbstractEntityImporter
{
    public function entity(): LegacyEntity
    {
        return LegacyEntity::Reviews;
    }

    public function sourceTable(): string
    {
        return 'rating';
    }

    public function checksumPayload(array $row): array
    {
        return [
            'id' => $row['id'] ?? null,
            'rating' => $row['rating'] ?? null,
            'user_id' => $row['user_id'] ?? null,
            'ratable_id' => $row['ratable_id'] ?? null,
            'ratable_type' => $row['ratable_type'] ?? null,
            'review' => $row['review'] ?? null,
        ];
    }

    public function importRow(array $row, ImportContext $context): RowImportResult
    {
        $sourceId = $this->extractSourceId($row);
        $ratableType = strtolower(trim((string) ($row['ratable_type'] ?? '')));

        if ($ratableType !== 'course') {
            return RowImportResult::rejected(
                'unsupported_ratable_type',
                "Legacy rating ratable_type '{$ratableType}' is not supported (only course reviews are mapped).",
            );
        }

        $legacyUserId = (int) ($row['user_id'] ?? 0);
        $legacyCourseId = (int) ($row['ratable_id'] ?? 0);

        $learnerId = $context->mappedId(LegacyEntity::Users, $legacyUserId);
        if ($learnerId === null) {
            return RowImportResult::rejected('missing_user', "Legacy user {$legacyUserId} is not mapped.");
        }

        $courseId = $context->mappedId(LegacyEntity::Courses, $legacyCourseId);
        if ($courseId === null) {
            return RowImportResult::rejected('missing_course', "Legacy course {$legacyCourseId} is not mapped.");
        }

        $rawRating = $row['rating'] ?? null;
        $rating = is_numeric($rawRating) ? (int) round((float) $rawRating) : 0;
        if ($rating < 1 || $rating > 5) {
            return RowImportResult::rejected('invalid_rating', "Legacy rating value '{$rawRating}' is outside 1..5.");
        }

        $existingTargetId = $context->existingRecord?->target_id;
        if ($context->dryRun) {
            $shadowId = $context->idMap->rememberShadow(LegacyEntity::Reviews, $sourceId, CourseReview::class, $existingTargetId, ['rating' => $rating]);

            return $existingTargetId !== null
                ? RowImportResult::updated(CourseReview::class, $shadowId, ['rating' => $rating])
                : RowImportResult::imported(CourseReview::class, $shadowId, ['rating' => $rating]);
        }

        $learner = User::query()->find($learnerId);
        if ($learner === null) {
            return RowImportResult::rejected('missing_user', "Target learner {$learnerId} not found.");
        }

        $review = CourseReview::query()->where('course_id', $courseId)->where('learner_id', $learnerId)->first();
        $isNew = $review === null;
        $review ??= new CourseReview(['course_id' => $courseId, 'learner_id' => $learnerId]);

        $review->forceFill([
            'rating' => $rating,
            'body' => trim((string) ($row['review'] ?? '')),
            'author_name' => ReviewerName::for($learner),
            'status' => ReviewStatus::Published,
            'moderated_at' => $review->moderated_at ?? LegacyDateParser::toUtc($row['date_added'] ?? null) ?? now()->utc(),
        ])->save();

        return $isNew
            ? RowImportResult::imported(CourseReview::class, (int) $review->getKey(), ['rating' => $rating])
            : RowImportResult::updated(CourseReview::class, (int) $review->getKey(), ['rating' => $rating]);
    }

    protected function existingTargetIds(array $targetIds): array
    {
        if ($targetIds === []) {
            return [];
        }

        return CourseReview::query()->whereIn('id', $targetIds)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->values()->all();
    }

    protected function compareSample(array $sourceRow, int $targetId): array
    {
        $review = CourseReview::query()->find($targetId);
        if ($review === null) {
            return ['target_missing'];
        }

        $expectedRating = (int) round((float) ($sourceRow['rating'] ?? 0));
        if ($review->rating !== $expectedRating) {
            return ["rating expected {$expectedRating}, got {$review->rating}"];
        }

        return [];
    }
}
