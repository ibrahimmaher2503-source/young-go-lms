<?php

declare(strict_types=1);

namespace App\Domain\Assessment;

use App\Models\Question;

/**
 * Grades one answer against its snapshot question. Choice questions are all or nothing (current scoring rule: no partial
 * credit); short answers match an accepted answer after light normalisation (case, spaces, Arabic marks and alef forms);
 * essays are never graded here: they return null and wait for a person (T-P6-04).
 */
final class QuizGrader
{
    /**
     * @param  array<string, mixed>  $question  a snapshot question
     * @param  array<string, mixed>|null  $answer  {selected: [keys]} or {text: string}
     * @return array{is_correct: bool|null, points: float|null}
     */
    public static function grade(array $question, ?array $answer): array
    {
        $type = (string) $question['type'];
        $points = (float) $question['points'];
        if ($type === Question::ESSAY) {
            return ['is_correct' => null, 'points' => null];
        }

        $correct = match ($type) {
            Question::SHORT_ANSWER => self::shortAnswerMatches($question, (string) ($answer['text'] ?? '')),
            default => self::choiceMatches($question, (array) ($answer['selected'] ?? [])),
        };

        return ['is_correct' => $correct, 'points' => $correct ? $points : 0.0];
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  array<array-key, mixed>  $selected
     */
    private static function choiceMatches(array $question, array $selected): bool
    {
        $wanted = [];
        foreach ((array) $question['options'] as $option) {
            if ($option['is_correct']) {
                $wanted[] = (int) $option['key'];
            }
        }
        $given = array_values(array_unique(array_map('intval', $selected)));
        sort($wanted);
        sort($given);

        return $given !== [] && $given === $wanted;
    }

    /** @param array<string, mixed> $question */
    private static function shortAnswerMatches(array $question, string $text): bool
    {
        $given = self::normalise($text);
        if ($given === '') {
            return false;
        }
        foreach ((array) $question['options'] as $option) {
            foreach ((array) $option['text'] as $accepted) {
                if (self::normalise((string) $accepted) === $given) {
                    return true;
                }
            }
        }

        return false;
    }

    public static function normalise(string $text): string
    {
        $text = mb_strtolower(trim($text));
        $text = (string) preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{0640}]/u', '', $text); // Arabic marks and tatweel
        $text = strtr($text, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي']);

        return (string) preg_replace('/\s+/u', ' ', $text);
    }
}
