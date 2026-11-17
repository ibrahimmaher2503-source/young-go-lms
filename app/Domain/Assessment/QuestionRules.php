<?php

declare(strict_types=1);

namespace App\Domain\Assessment;

use App\Models\Question;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * The rules for one question, per type. Input is read-only here: this returns the clean shape or throws.
 *  - single: 2 to 8 options, exactly one correct
 *  - multiple: 2 to 8 options, at least one correct and at least one wrong
 *  - true_false: a boolean answer; the two options are created for the author
 *  - short_answer: 1 to 10 accepted answers (all count as correct)
 *  - essay: no options; a person grades it
 */
final class QuestionRules
{
    public const int MIN_OPTIONS = 2;

    public const int MAX_OPTIONS = 8;

    public const int MAX_ACCEPTED = 10;

    /**
     * @param  array<string, mixed>  $data  type, prompt{ar,en}, explanation{ar,en}, points, options[{text{ar,en}, is_correct}], true_is_correct
     * @return array{type: string, prompt: array<string, string>, explanation: array<string, string>, points: float, options: list<array{text: array<string, string>, is_correct: bool}>}
     */
    public static function clean(array $data): array
    {
        $valid = Validator::make($data, [
            'type' => ['required', 'string', 'in:'.implode(',', Question::TYPES)],
            'prompt' => ['required', 'array'],
            'prompt.ar' => ['nullable', 'string', 'max:5000'],
            'prompt.en' => ['nullable', 'string', 'max:5000'],
            'explanation' => ['nullable', 'array'],
            'explanation.ar' => ['nullable', 'string', 'max:5000'],
            'explanation.en' => ['nullable', 'string', 'max:5000'],
            'points' => ['required', 'numeric', 'min:0.25', 'max:1000'],
            'options' => ['nullable', 'array'],
            'options.*.text' => ['required_with:options', 'array'],
            'options.*.text.ar' => ['nullable', 'string', 'max:1000'],
            'options.*.text.en' => ['nullable', 'string', 'max:1000'],
            'options.*.is_correct' => ['nullable', 'boolean'],
            'true_is_correct' => ['nullable', 'boolean'],
        ])->validate();

        $type = (string) $valid['type'];
        $prompt = self::filled((array) $valid['prompt']);
        if ($prompt === []) {
            throw ValidationException::withMessages(['prompt' => __('quiz.errors.prompt_required')]);
        }
        $explanation = self::filled((array) ($valid['explanation'] ?? []));

        $options = match ($type) {
            Question::TRUE_FALSE => self::trueFalse($valid),
            Question::ESSAY => [],
            default => self::fromInput((array) ($valid['options'] ?? [])),
        };
        self::assertShape($type, $options);

        return ['type' => $type, 'prompt' => $prompt, 'explanation' => $explanation, 'points' => round((float) $valid['points'], 2), 'options' => $options];
    }

    /**
     * @param  array<string, mixed>  $valid
     * @return list<array{text: array<string, string>, is_correct: bool}>
     */
    private static function trueFalse(array $valid): array
    {
        if (! array_key_exists('true_is_correct', $valid) || $valid['true_is_correct'] === null) {
            throw ValidationException::withMessages(['true_is_correct' => __('quiz.errors.true_false_needs_answer')]);
        }
        $trueIsCorrect = (bool) $valid['true_is_correct'];

        return [
            ['text' => ['ar' => __('quiz.true', [], 'ar'), 'en' => __('quiz.true', [], 'en')], 'is_correct' => $trueIsCorrect],
            ['text' => ['ar' => __('quiz.false', [], 'ar'), 'en' => __('quiz.false', [], 'en')], 'is_correct' => ! $trueIsCorrect],
        ];
    }

    /**
     * @param  array<int|string, mixed>  $options
     * @return list<array{text: array<string, string>, is_correct: bool}>
     */
    private static function fromInput(array $options): array
    {
        $clean = [];
        foreach ($options as $option) {
            $option = (array) $option;
            $text = self::filled((array) ($option['text'] ?? []));
            if ($text === []) {
                continue; // an empty row is ignored, not an error
            }
            $clean[] = ['text' => $text, 'is_correct' => (bool) ($option['is_correct'] ?? false)];
        }

        return $clean;
    }

    /** @param list<array{text: array<string, string>, is_correct: bool}> $options */
    private static function assertShape(string $type, array $options): void
    {
        $correct = count(array_filter($options, static fn (array $o): bool => $o['is_correct']));
        $count = count($options);

        $error = match ($type) {
            Question::SINGLE => ($count < self::MIN_OPTIONS || $count > self::MAX_OPTIONS) ? 'options_range' : ($correct !== 1 ? 'single_one_correct' : null),
            Question::MULTIPLE => ($count < self::MIN_OPTIONS || $count > self::MAX_OPTIONS) ? 'options_range' : ($correct < 1 ? 'multiple_needs_correct' : ($correct === $count ? 'multiple_needs_wrong' : null)),
            Question::SHORT_ANSWER => ($count < 1 || $count > self::MAX_ACCEPTED) ? 'accepted_range' : null,
            default => null,
        };
        if ($error !== null) {
            throw ValidationException::withMessages(['options' => __('quiz.errors.'.$error, ['min' => self::MIN_OPTIONS, 'max' => self::MAX_OPTIONS, 'accepted' => self::MAX_ACCEPTED])]);
        }
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return array<string, string>
     */
    private static function filled(array $values): array
    {
        $out = [];
        foreach (['ar', 'en'] as $locale) {
            $text = $values[$locale] ?? null;
            if (is_string($text) && trim($text) !== '') {
                $out[$locale] = trim($text);
            }
        }

        return $out;
    }
}
