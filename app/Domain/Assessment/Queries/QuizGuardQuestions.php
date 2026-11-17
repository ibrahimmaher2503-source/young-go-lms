<?php

declare(strict_types=1);

namespace App\Domain\Assessment\Queries;

use App\Models\Question;
use App\Models\Quiz;
use Illuminate\Support\Collection;

/**
 * The AI hook for quizzes (T-P6-01): the open graded questions of a quiz, as text only, for the assistant quiz guard that
 * must refuse to answer them while an attempt is in progress. It returns prompts and ids; it never returns options,
 * accepted answers, explanations or any answer key.
 */
final class QuizGuardQuestions
{
    /**
     * Every active question prompt of the quiz in every language, whatever its type (T-P7-02: a choice question is as
     * copyable as an open one). Prompt text only: never options, accepted answers or explanations.
     *
     * @return list<string>
     */
    public static function promptsFor(Quiz $quiz): array
    {
        $prompts = [];
        foreach (Question::query()->where('quiz_id', $quiz->getKey())->where('active', true)->orderBy('sort')->orderBy('id')->get(['id', 'prompt']) as $question) {
            foreach ($question->getTranslations('prompt') as $text) {
                if (trim((string) $text) !== '') {
                    $prompts[] = (string) $text;
                }
            }
        }

        return $prompts;
    }

    /** @return Collection<int, array{question_id: int, type: string, prompt: array<array-key, string>}> */
    public static function openFor(Quiz $quiz): Collection
    {
        return Question::query()->where('quiz_id', $quiz->getKey())->where('active', true)
            ->whereIn('type', [Question::SHORT_ANSWER, Question::ESSAY])->orderBy('sort')->orderBy('id')->get(['id', 'type', 'prompt'])
            ->map(fn (Question $question): array => ['question_id' => (int) $question->getKey(), 'type' => $question->type, 'prompt' => array_map('strval', $question->getTranslations('prompt'))]);
    }
}
