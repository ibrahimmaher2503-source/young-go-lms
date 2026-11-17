<?php

declare(strict_types=1);

namespace App\Domain\Assessment\Queries;

use App\Models\Question;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;

/**
 * What the browser may see of an attempt. The snapshot holds the answer key, so it is never sent as is.
 *  - questions(): for an open attempt: prompts, points and the choices (text only), plus the learner saved answers.
 *    Short answers carry no accepted answers; no explanation, no is_correct, no points awarded.
 *  - result(): for a submitted attempt, shaped by the visibility the quiz had when the attempt started:
 *      full: score, pass state, and per question the learner answer, the correct answer and the explanation;
 *      score_only: score and pass state only;
 *      hidden: nothing but "submitted" until staff release it (released_at).
 */
final class QuizAttemptView
{
    /**
     * The text in the current language, falling back to the other one.
     *
     * @param  array<array-key, string>  $translations
     */
    public static function text(array $translations): string
    {
        $text = $translations[app()->getLocale()] ?? '';

        return $text !== '' ? $text : (string) collect($translations)->first(fn (string $value): bool => $value !== '', '');
    }

    /** @return list<array{id: int, type: string, prompt: array<array-key, string>, points: float, options: list<array{key: int, text: array<array-key, string>}>, answer: array<string, mixed>|null}> */
    public static function questions(QuizAttempt $attempt): array
    {
        $saved = QuizAnswer::query()->where('quiz_attempt_id', $attempt->getKey())->get()->keyBy('question_id');
        $out = [];

        foreach ($attempt->snapshot['questions'] as $question) {
            $options = [];
            if (in_array($question['type'], [Question::SINGLE, Question::MULTIPLE, Question::TRUE_FALSE], true)) {
                foreach ($question['options'] as $option) {
                    $options[] = ['key' => (int) $option['key'], 'text' => $option['text']];
                }
            }
            $out[] = [
                'id' => (int) $question['id'], 'type' => (string) $question['type'], 'prompt' => $question['prompt'], 'points' => (float) $question['points'],
                'options' => $options, 'answer' => $saved->get((int) $question['id'])?->answer,
            ];
        }

        return $out;
    }

    /**
     * @return array{state: string, visible: bool, score: float|null, total: float|null, percent: float|null, passed: bool|null, needs_review: bool, submitted_by: string|null, questions: list<array<string, mixed>>}
     */
    public static function result(QuizAttempt $attempt): array
    {
        $visibility = (string) ($attempt->settings['result_visibility'] ?? 'full');
        $visible = $visibility !== 'hidden' || $attempt->released_at !== null;
        $base = ['state' => $attempt->status, 'visible' => $visible, 'needs_review' => $attempt->needs_review, 'submitted_by' => $attempt->submitted_by];

        if (! $visible) {
            return [...$base, 'score' => null, 'total' => null, 'percent' => null, 'passed' => null, 'questions' => []];
        }

        $result = [
            ...$base, 'score' => (float) $attempt->score, 'total' => (float) $attempt->total, 'percent' => (float) $attempt->percent,
            'passed' => $attempt->passed, 'questions' => [],
        ];
        if ($visibility !== 'full' && $attempt->released_at === null) {
            return $result;
        }

        $answers = QuizAnswer::query()->where('quiz_attempt_id', $attempt->getKey())->get()->keyBy('question_id');
        foreach ($attempt->snapshot['questions'] as $question) {
            $row = $answers->get((int) $question['id']);
            $result['questions'][] = [
                'id' => (int) $question['id'], 'type' => $question['type'], 'prompt' => $question['prompt'], 'points' => (float) $question['points'],
                'answer' => $row?->answer, 'is_correct' => $row?->is_correct, 'points_awarded' => $row?->points_awarded === null ? null : (float) $row->points_awarded,
                'options' => array_map(fn (array $o): array => ['key' => (int) $o['key'], 'text' => $o['text'], 'is_correct' => (bool) $o['is_correct']], $question['type'] === Question::SHORT_ANSWER ? [] : $question['options']),
                'accepted' => $question['type'] === Question::SHORT_ANSWER ? array_map(fn (array $o): array => $o['text'], $question['options']) : [],
                'explanation' => $question['explanation'],
            ];
        }

        return $result;
    }
}
