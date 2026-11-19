<?php

declare(strict_types=1);

namespace App\Domain\Assessment;

use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Quiz;

/**
 * The frozen copy of a quiz that an attempt works from: active questions with their options and key, in the order the
 * learner will see them (shuffled once, at the start, when the quiz randomizes). Option keys are 1..n inside the
 * question and are what answers refer to, because the option rows of the question bank are rebuilt whenever it is edited.
 */
final class QuizSnapshot
{
    /**
     * @return array{questions: list<array{id: int, version: int, type: string, prompt: array<array-key, string>, explanation: array<array-key, string>, points: float, options: list<array{key: int, text: array<array-key, string>, is_correct: bool}>}>}
     */
    public static function build(Quiz $quiz): array
    {
        $questions = $quiz->activeQuestions()->with('options')->get()->map(function (Question $question) use ($quiz): array {
            $options = $question->options->map(fn (QuestionOption $option): array => [
                'text' => array_map('strval', $option->getTranslations('text')), 'is_correct' => $option->is_correct,
            ])->all();
            if ($quiz->randomize && in_array($question->type, [Question::SINGLE, Question::MULTIPLE], true)) {
                shuffle($options); // true or false keeps its natural order
            }
            $keyed = [];
            foreach (array_values($options) as $index => $option) {
                $keyed[] = ['key' => $index + 1, ...$option];
            }

            return [
                'id' => (int) $question->getKey(), 'version' => $question->version, 'type' => $question->type,
                'prompt' => array_map('strval', $question->getTranslations('prompt')), 'explanation' => array_map('strval', $question->getTranslations('explanation')),
                'points' => (float) $question->points, 'options' => $keyed,
            ];
        })->all();

        if ($quiz->randomize) {
            shuffle($questions);
        }

        return ['questions' => array_values($questions)];
    }

    /** @return array<string, mixed> the settings an attempt is judged by, frozen at the start */
    public static function settings(Quiz $quiz): array
    {
        return [
            'time_limit_minutes' => $quiz->time_limit_minutes, 'pass_mark_percent' => QuizPassMark::forQuiz($quiz), 'max_attempts' => $quiz->max_attempts,
            'result_visibility' => $quiz->result_visibility, 'randomize' => $quiz->randomize, 'progression' => $quiz->progression,
        ];
    }
}
