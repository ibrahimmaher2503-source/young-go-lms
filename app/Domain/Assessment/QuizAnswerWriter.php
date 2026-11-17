<?php

declare(strict_types=1);

namespace App\Domain\Assessment;

use App\Models\Question;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use Illuminate\Support\Facades\DB;

/**
 * Validates and stores answers against the snapshot of one attempt. An answer may only name a question of the attempt and
 * option keys of its snapshot; a wrong shape is refused as a whole. It never grades and never looks at the deadline:
 * the Actions that call it decide whether the attempt may still change.
 */
final class QuizAnswerWriter
{
    /**
     * @param  array<array-key, mixed>  $answers  question id => {selected: [keys]} or {text: string}
     * @return int how many answers were stored or cleared
     */
    public function apply(QuizAttempt $attempt, array $answers): int
    {
        $questions = collect($attempt->snapshot['questions'])->keyBy(fn (array $q): int => (int) $q['id']);
        $clean = [];

        foreach ($answers as $questionId => $answer) {
            $question = $questions->get((int) $questionId);
            if ($question === null || ! is_array($answer)) {
                throw new QuizRefusedException(QuizRefusedException::INVALID_ANSWER);
            }
            $clean[(int) $questionId] = [(int) $question['version'], $this->cleanAnswer($question, $answer)];
        }

        DB::transaction(function () use ($attempt, $clean): void {
            foreach ($clean as $questionId => [$version, $stored]) {
                if ($stored === null) {
                    QuizAnswer::query()->where('quiz_attempt_id', $attempt->getKey())->where('question_id', $questionId)->delete();

                    continue;
                }
                QuizAnswer::query()->updateOrCreate(
                    ['quiz_attempt_id' => $attempt->getKey(), 'question_id' => $questionId],
                    ['question_version' => $version, 'answer' => $stored],
                );
            }
        });

        return count($clean);
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  array<array-key, mixed>  $answer
     * @return array<string, mixed>|null null when the answer is empty (clears a saved one)
     */
    private function cleanAnswer(array $question, array $answer): ?array
    {
        $type = (string) $question['type'];
        if (in_array($type, [Question::SINGLE, Question::MULTIPLE, Question::TRUE_FALSE], true)) {
            $keys = array_column((array) $question['options'], 'key');
            $selected = [];
            foreach ((array) ($answer['selected'] ?? []) as $key) {
                if (! is_scalar($key) || ! in_array((int) $key, $keys, true)) {
                    throw new QuizRefusedException(QuizRefusedException::INVALID_ANSWER);
                }
                $selected[] = (int) $key;
            }
            $selected = array_values(array_unique($selected));
            if ($type !== Question::MULTIPLE && count($selected) > 1) {
                throw new QuizRefusedException(QuizRefusedException::INVALID_ANSWER);
            }

            return $selected === [] ? null : ['selected' => $selected];
        }

        $text = $answer['text'] ?? '';
        $max = $type === Question::SHORT_ANSWER ? 500 : (int) config('assessment.max_text_length');
        if (! is_string($text) || mb_strlen($text) > $max) {
            throw new QuizRefusedException(QuizRefusedException::INVALID_ANSWER);
        }
        $text = trim($text);

        return $text === '' ? null : ['text' => $text];
    }
}
