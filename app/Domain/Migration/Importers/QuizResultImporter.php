<?php

declare(strict_types=1);

namespace App\Domain\Migration\Importers;

use App\Domain\Assessment\QuizPassMark;
use App\Domain\Migration\Data\ImportContext;
use App\Domain\Migration\Data\RowImportResult;
use App\Domain\Migration\Enums\LegacyEntity;
use App\Domain\Migration\Support\LegacyDateParser;
use App\Models\Quiz;
use App\Models\QuizAttempt;

final class QuizResultImporter extends AbstractEntityImporter
{
    public function entity(): LegacyEntity
    {
        return LegacyEntity::QuizResults;
    }

    public function sourceTable(): string
    {
        return 'quiz_results';
    }

    public function sourceIdColumn(): string
    {
        return 'quiz_result_id';
    }

    public function checksumPayload(array $row): array
    {
        return [
            'quiz_result_id' => $row['quiz_result_id'] ?? null,
            'quiz_id' => $row['quiz_id'] ?? null,
            'user_id' => $row['user_id'] ?? null,
            'user_answers' => $row['user_answers'] ?? null,
            'correct_answers' => $row['correct_answers'] ?? null,
            'total_obtained_marks' => $row['total_obtained_marks'] ?? null,
            'is_submitted' => $row['is_submitted'] ?? 0,
        ];
    }

    public function importRow(array $row, ImportContext $context): RowImportResult
    {
        $sourceId = $this->extractSourceId($row);
        $legacyQuizId = (int) ($row['quiz_id'] ?? 0);
        $legacyUserId = (int) ($row['user_id'] ?? 0);

        if ((int) ($row['is_submitted'] ?? 0) !== 1) {
            return RowImportResult::skipped(
                'quiz_attempt_not_submitted',
                'Legacy quiz result row is not marked submitted (is_submitted != 1).',
            );
        }

        $quizId = $context->mappedId(LegacyEntity::Quizzes, $legacyQuizId);
        if ($quizId === null) {
            return RowImportResult::rejected('missing_quiz', "Legacy quiz {$legacyQuizId} is not mapped.");
        }

        $learnerId = $context->mappedId(LegacyEntity::Users, $legacyUserId);
        if ($learnerId === null) {
            return RowImportResult::rejected('missing_user', "Legacy user {$legacyUserId} is not mapped.");
        }

        $score = (float) ($row['total_obtained_marks'] ?? 0);
        if ($score < 0) {
            return RowImportResult::rejected('invalid_score', 'Legacy quiz score cannot be negative.');
        }

        $existingTargetId = $context->existingRecord?->target_id;
        $startedAt = LegacyDateParser::toUtc($row['date_added'] ?? null) ?? now()->utc();
        $submittedAt = LegacyDateParser::toUtc($row['date_updated'] ?? null) ?? $startedAt;

        if ($context->dryRun) {
            $shadowId = $context->idMap->rememberShadow(LegacyEntity::QuizResults, $sourceId, QuizAttempt::class, $existingTargetId, ['score' => $score]);

            return $existingTargetId !== null
                ? RowImportResult::updated(QuizAttempt::class, $shadowId, ['score' => $score])
                : RowImportResult::imported(QuizAttempt::class, $shadowId, ['score' => $score]);
        }

        $quiz = Quiz::query()->find($quizId);
        if ($quiz === null) {
            return RowImportResult::rejected('missing_quiz', "Target quiz {$quizId} not found.");
        }

        $attempt = $existingTargetId !== null ? QuizAttempt::query()->find($existingTargetId) : null;
        $isNew = $attempt === null;

        if ($attempt === null) {
            $nextNo = ((int) QuizAttempt::query()->where('quiz_id', $quizId)->where('learner_id', $learnerId)->max('attempt_no')) + 1;
            $attempt = new QuizAttempt([
                'quiz_id' => $quizId,
                'learner_id' => $learnerId,
                'attempt_no' => $nextNo,
            ]);
        }

        $totalMarks = max($quiz->totalMarks(), $score, 1.0);
        $percent = round(min(100.0, ($score / $totalMarks) * 100.0), 2);
        $passMark = QuizPassMark::forQuiz($quiz);

        $attempt->forceFill([
            'status' => QuizAttempt::SUBMITTED,
            'started_at' => $startedAt,
            'submitted_at' => $submittedAt,
            'submitted_by' => 'legacy',
            'snapshot' => [
                'questions' => [],
                'legacy_quiz_result_id' => $sourceId,
                'legacy_user_answers' => json_decode((string) ($row['user_answers'] ?? '[]'), true) ?? [],
                'legacy_correct_answers' => json_decode((string) ($row['correct_answers'] ?? '[]'), true) ?? [],
            ],
            'settings' => [
                'pass_mark_percent' => $passMark,
                'result_visibility' => $quiz->result_visibility,
                'progression' => $quiz->progression,
                'time_limit_minutes' => $quiz->time_limit_minutes,
            ],
            'score' => $score,
            'total' => $totalMarks,
            'percent' => $percent,
            'passed' => $percent >= $passMark,
            'needs_review' => false,
        ])->save();

        return $isNew
            ? RowImportResult::imported(QuizAttempt::class, (int) $attempt->getKey(), ['percent' => $percent])
            : RowImportResult::updated(QuizAttempt::class, (int) $attempt->getKey(), ['percent' => $percent]);
    }

    protected function existingTargetIds(array $targetIds): array
    {
        if ($targetIds === []) {
            return [];
        }

        return QuizAttempt::query()->whereIn('id', $targetIds)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->values()->all();
    }

    protected function compareSample(array $sourceRow, int $targetId): array
    {
        $attempt = QuizAttempt::query()->find($targetId);
        if ($attempt === null) {
            return ['target_missing'];
        }

        $expectedScore = round((float) ($sourceRow['total_obtained_marks'] ?? 0), 2);
        $actualScore = round((float) $attempt->score, 2);
        if (abs($expectedScore - $actualScore) > 0.01) {
            return ["score expected {$expectedScore}, got {$actualScore}"];
        }

        return [];
    }
}
