<?php

declare(strict_types=1);

namespace App\Domain\Assessment\Actions;

use App\Domain\Assessment\QuizAccess;
use App\Domain\Audit\AuditLogger;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Saves the quiz settings: time limit, pass mark (percent of total marks), attempts, what a learner sees after submitting,
 * shuffling, and whether passing is needed to move on. Empty means "no limit" (time, attempts) or "no pass mark".
 * Which result visibility values and what unit the pass mark uses are open owner decisions (O-021, O-022).
 */
final class ConfigureQuiz
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array<string, mixed> $data */
    public function __invoke(User $actor, Quiz $quiz, array $data): Quiz
    {
        QuizAccess::authorize($actor, $quiz);

        /** @var array{time_limit_minutes?: int|null, pass_mark_percent?: int|null, max_attempts?: int|null, result_visibility: string, randomize?: bool|null, progression: string} $clean */
        $clean = Validator::make($data, [
            'time_limit_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'pass_mark_percent' => ['nullable', 'integer', 'min:1', 'max:100'],
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:100'],
            'result_visibility' => ['required', 'in:'.implode(',', Quiz::RESULT_VISIBILITY)],
            'randomize' => ['nullable', 'boolean'],
            'progression' => ['required', 'in:'.implode(',', Quiz::PROGRESSION)],
        ])->validate();

        // "Require a pass" has no meaning without a pass mark; refuse instead of saving a quiz nobody can pass or everybody passes.
        if ($clean['progression'] === 'require_pass' && ($clean['pass_mark_percent'] ?? null) === null) {
            throw ValidationException::withMessages(['pass_mark_percent' => __('quiz.errors.pass_mark_needed')]);
        }

        $before = $quiz->only(['time_limit_minutes', 'pass_mark_percent', 'max_attempts', 'result_visibility', 'randomize', 'progression']);
        $quiz->update([
            'time_limit_minutes' => $clean['time_limit_minutes'] ?? null, 'pass_mark_percent' => $clean['pass_mark_percent'] ?? null, 'max_attempts' => $clean['max_attempts'] ?? null,
            'result_visibility' => $clean['result_visibility'], 'randomize' => (bool) ($clean['randomize'] ?? false), 'progression' => $clean['progression'],
        ]);

        if ($this->audit->record('quiz.settings_changed', $quiz, (int) $actor->getKey(), $before, $quiz->only(array_keys($before))) === null) {
            throw new RuntimeException('Unable to record quiz audit.');
        }

        return $quiz;
    }
}
