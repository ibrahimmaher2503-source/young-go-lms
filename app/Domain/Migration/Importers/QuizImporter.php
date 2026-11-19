<?php

declare(strict_types=1);

namespace App\Domain\Migration\Importers;

use App\Domain\Migration\Contracts\LegacySourceReader;
use App\Domain\Migration\Data\ImportContext;
use App\Domain\Migration\Data\ImportExceptionData;
use App\Domain\Migration\Data\RowImportResult;
use App\Domain\Migration\Enums\LegacyEntity;
use App\Domain\Migration\Enums\MigrationExceptionSeverity;
use App\Domain\Migration\Support\ScriptNameMapper;
use App\Models\CurriculumItem;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;
use Closure;
use Illuminate\Database\Query\Builder;

final class QuizImporter extends AbstractEntityImporter
{
    public function entity(): LegacyEntity
    {
        return LegacyEntity::Quizzes;
    }

    public function sourceTable(): string
    {
        return 'lesson';
    }

    protected function sourceScope(): ?Closure
    {
        return static function (Builder $query): void {
            $query->whereRaw("LOWER(TRIM(COALESCE(lesson_type, ''))) = 'quiz'");
        };
    }

    public function checksumPayload(array $row): array
    {
        return [
            'id' => $row['id'] ?? null,
            'title' => $row['title'] ?? null,
            'course_id' => $row['course_id'] ?? null,
            'section_id' => $row['section_id'] ?? null,
            'attachment' => $row['attachment'] ?? null,
            'summary' => $row['summary'] ?? null,
            'order' => $row['order'] ?? 0,
            'quiz_attempt' => $row['quiz_attempt'] ?? 0,
        ];
    }

    public function importRow(array $row, ImportContext $context): RowImportResult
    {
        $sourceId = $this->extractSourceId($row);
        $titleText = trim((string) ($row['title'] ?? ''));

        if ($titleText === '') {
            return RowImportResult::rejected('empty_title', 'Legacy quiz has an empty title.');
        }

        $legacyCourseId = (int) ($row['course_id'] ?? 0);
        $legacySectionId = (int) ($row['section_id'] ?? 0);

        $courseId = $context->mappedId(LegacyEntity::Courses, $legacyCourseId);
        if ($courseId === null) {
            return RowImportResult::rejected('missing_course', "Legacy course {$legacyCourseId} is not mapped.");
        }

        $sectionId = $context->mappedId(LegacyEntity::Sections, $legacySectionId);
        if ($sectionId === null) {
            return RowImportResult::rejected('missing_section', "Legacy section {$legacySectionId} is not mapped.");
        }

        $exceptions = [];
        $timeLimitMinutes = null;
        $passMarkPercent = null;
        $rawMeta = trim((string) ($row['attachment'] ?? ''));

        if ($rawMeta !== '') {
            $decoded = json_decode($rawMeta, true);
            if (! is_array($decoded)) {
                $exceptions[] = new ImportExceptionData(
                    'invalid_quiz_metadata_json',
                    MigrationExceptionSeverity::Warning,
                    'Legacy quiz attachment metadata JSON could not be parsed; using defaults.',
                );
            } else {
                if (isset($decoded['duration']) && is_numeric($decoded['duration']) && (int) $decoded['duration'] > 0) {
                    $timeLimitMinutes = (int) $decoded['duration'];
                }
                if (isset($decoded['pass_mark']) && is_numeric($decoded['pass_mark'])) {
                    $mark = (int) $decoded['pass_mark'];
                    if ($mark >= 1 && $mark <= 100) {
                        $passMarkPercent = $mark;
                    }
                }
            }
        }

        $questions = $this->loadQuestions($context->reader, (int) $sourceId);
        $courseMeta = $context->mappedMetadata(LegacyEntity::Courses, $legacyCourseId);
        $coursePublished = ($courseMeta['status'] ?? '') === 'published';
        $sort = max(0, (int) ($row['order'] ?? 0));
        $maxAttempts = (int) ($row['quiz_attempt'] ?? 0) > 0 ? (int) $row['quiz_attempt'] : null;

        $metadata = [
            'question_count' => count($questions),
            'sort' => $sort,
        ];

        $existingTargetId = $context->existingRecord?->target_id;
        if ($context->dryRun) {
            $shadowId = $context->idMap->rememberShadow(LegacyEntity::Quizzes, $sourceId, Quiz::class, $existingTargetId, $metadata);

            return $existingTargetId !== null
                ? RowImportResult::updated(Quiz::class, $shadowId, $metadata, $exceptions)
                : RowImportResult::imported(Quiz::class, $shadowId, $metadata, $exceptions);
        }

        $quiz = $existingTargetId !== null ? Quiz::query()->find($existingTargetId) : null;
        $isNew = $quiz === null;
        $quiz ??= new Quiz;

        $summaryText = trim((string) ($row['summary'] ?? ''));
        $quiz->forceFill([
            'title' => ScriptNameMapper::bilingual($titleText, $context->defaultLocale),
            'description' => $summaryText !== '' ? ScriptNameMapper::bilingual(strip_tags($summaryText), $context->defaultLocale) : null,
            'time_limit_minutes' => $timeLimitMinutes,
            'pass_mark_percent' => $passMarkPercent,
            'max_attempts' => $maxAttempts,
            'result_visibility' => 'full',
            'randomize' => false,
            'progression' => 'after_submission',
        ])->save();

        $item = CurriculumItem::query()->updateOrCreate(
            ['itemable_type' => (new Quiz)->getMorphClass(), 'itemable_id' => $quiz->getKey()],
            [
                'course_id' => $courseId,
                'section_id' => $sectionId,
                'sort' => $sort,
                'status' => $coursePublished ? CurriculumItem::STATUS_PUBLISHED : CurriculumItem::STATUS_DRAFT,
                'visibility' => 'enrolled',
                'unlock_rule' => 'none',
                'required' => true,
                'ai_enabled' => false,
            ],
        );

        // Replace question bank rows deterministically when syncing quiz definition.
        $quiz->questions()->delete();
        foreach ($questions as $index => $qRow) {
            $this->persistQuestion($quiz, $qRow, $index, $context->defaultLocale, $exceptions);
        }

        $metadata['curriculum_item_id'] = (int) $item->getKey();

        return $isNew
            ? RowImportResult::imported(Quiz::class, (int) $quiz->getKey(), $metadata, $exceptions)
            : RowImportResult::updated(Quiz::class, (int) $quiz->getKey(), $metadata, $exceptions);
    }

    protected function existingTargetIds(array $targetIds): array
    {
        if ($targetIds === []) {
            return [];
        }

        return Quiz::query()->whereIn('id', $targetIds)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->values()->all();
    }

    protected function compareSample(array $sourceRow, int $targetId): array
    {
        $quiz = Quiz::query()->with(['item', 'questions'])->find($targetId);
        if ($quiz === null) {
            return ['target_missing'];
        }

        $mismatches = [];
        $expectedSort = max(0, (int) ($sourceRow['order'] ?? 0));
        if ($quiz->item === null || (int) $quiz->item->sort !== $expectedSort) {
            $mismatches[] = "curriculum_item.sort expected {$expectedSort}";
        }

        return $mismatches;
    }

    /** @return list<array<string, mixed>> */
    private function loadQuestions(LegacySourceReader $reader, int $legacyQuizId): array
    {
        if (! $reader->tableExists('question')) {
            return [];
        }

        return $reader->selectAll('question', static function (Builder $query) use ($legacyQuizId): void {
            $query->where('quiz_id', $legacyQuizId)->orderBy('order')->orderBy('id');
        });
    }

    /**
     * @param  array<string, mixed>  $qRow
     * @param  list<ImportExceptionData>  $exceptions
     */
    private function persistQuestion(
        Quiz $quiz,
        array $qRow,
        int $fallbackSort,
        string $defaultLocale,
        array &$exceptions,
    ): void {
        $promptText = trim(strip_tags((string) ($qRow['title'] ?? '')));
        if ($promptText === '') {
            $promptText = 'Question '.($fallbackSort + 1);
        }

        $rawOptions = json_decode((string) ($qRow['options'] ?? '[]'), true);
        $rawCorrect = json_decode((string) ($qRow['correct_answers'] ?? '[]'), true);
        if (! is_array($rawOptions)) {
            $rawOptions = [];
            $exceptions[] = new ImportExceptionData(
                'malformed_question_options',
                MigrationExceptionSeverity::Warning,
                'Legacy question options JSON could not be decoded.',
                ['legacy_question_id' => $qRow['id'] ?? null],
            );
        }
        if (! is_array($rawCorrect)) {
            $rawCorrect = [];
        }

        $correctIndexes = array_map(static fn (mixed $v): string => trim((string) $v), $rawCorrect);
        $rawType = strtolower(trim((string) ($qRow['type'] ?? 'mcq')));

        $type = match ($rawType) {
            'fill_in_the_blank', 'short_answer' => Question::SHORT_ANSWER,
            'true_false' => Question::TRUE_FALSE,
            'essay', 'text' => Question::ESSAY,
            default => count($correctIndexes) > 1 ? Question::MULTIPLE : Question::SINGLE,
        };

        $question = Question::query()->create([
            'quiz_id' => $quiz->getKey(),
            'type' => $type,
            'prompt' => ScriptNameMapper::bilingual($promptText, $defaultLocale),
            'points' => 1,
            'sort' => max(0, (int) ($qRow['order'] ?? $fallbackSort)),
            'active' => true,
            'version' => 1,
        ]);

        if ($type === Question::SHORT_ANSWER) {
            foreach ($correctIndexes as $idx => $acceptedText) {
                if ($acceptedText === '') {
                    continue;
                }
                QuestionOption::query()->create([
                    'question_id' => $question->getKey(),
                    'text' => ScriptNameMapper::bilingual($acceptedText, $defaultLocale),
                    'is_correct' => true,
                    'sort' => $idx,
                ]);
            }

            return;
        }

        foreach (array_values($rawOptions) as $idx => $optionText) {
            if (! is_string($optionText) || trim($optionText) === '') {
                continue;
            }
            // Academy LMS ASSUMPTION: correct_answers stores 1-based option indices as strings.
            $oneBased = (string) ($idx + 1);
            QuestionOption::query()->create([
                'question_id' => $question->getKey(),
                'text' => ScriptNameMapper::bilingual(trim($optionText), $defaultLocale),
                'is_correct' => in_array($oneBased, $correctIndexes, true),
                'sort' => $idx,
            ]);
        }
    }
}
