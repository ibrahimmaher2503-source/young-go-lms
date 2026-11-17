<?php

declare(strict_types=1);

namespace App\Domain\Assessment\Actions;

use App\Domain\Assessment\QuizRefusedException;
use App\Domain\Assessment\QuizSnapshot;
use App\Domain\Audit\AuditLogger;
use App\Domain\Entitlements\EntitlementService;
use App\Models\CurriculumItem;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Starts (or resumes) an attempt. The learner must pass the same access decision as any curriculum item. An open attempt
 * that is still inside its time is resumed, never duplicated; one that ran out of time is submitted first. The attempt
 * limit counts every attempt, started or finished. The deadline is fixed here, on the server, from the time limit.
 */
final class StartQuizAttempt
{
    public function __construct(
        private readonly EntitlementService $access,
        private readonly SubmitQuizAttempt $submit,
        private readonly AuditLogger $audit,
    ) {}

    public function __invoke(User $learner, CurriculumItem $item): QuizAttempt
    {
        $quiz = $item->itemable;
        if (! $quiz instanceof Quiz || $quiz->archived_at !== null || ! $this->access->canAccessItem($learner, $item)->allowed) {
            throw new QuizRefusedException(QuizRefusedException::NO_ACCESS);
        }

        return DB::transaction(function () use ($learner, $quiz): QuizAttempt {
            $locked = Quiz::query()->whereKey($quiz->getKey())->lockForUpdate()->firstOrFail();

            $open = QuizAttempt::query()->where('quiz_id', $locked->getKey())->where('learner_id', $learner->getKey())->where('status', QuizAttempt::IN_PROGRESS)->first();
            if ($open !== null) {
                if (! $this->pastGrace($open)) {
                    return $open;
                }
                $this->submit->__invoke($learner, $open, null, true);
            }

            $used = QuizAttempt::query()->where('quiz_id', $locked->getKey())->where('learner_id', $learner->getKey())->count();
            if ($locked->max_attempts !== null && $used >= $locked->max_attempts) {
                throw new QuizRefusedException(QuizRefusedException::ATTEMPTS_EXHAUSTED);
            }

            $snapshot = QuizSnapshot::build($locked);
            if ($snapshot['questions'] === []) {
                throw new QuizRefusedException(QuizRefusedException::NO_QUESTIONS);
            }

            $now = now();
            $attempt = QuizAttempt::query()->create([
                'quiz_id' => $locked->getKey(), 'learner_id' => $learner->getKey(), 'attempt_no' => $used + 1, 'status' => QuizAttempt::IN_PROGRESS,
                'started_at' => $now, 'deadline_at' => $locked->time_limit_minutes === null ? null : $now->copy()->addMinutes($locked->time_limit_minutes),
                'snapshot' => $snapshot, 'settings' => QuizSnapshot::settings($locked),
                'total' => array_sum(array_column($snapshot['questions'], 'points')),
            ]);

            if ($this->audit->record('quiz.attempt_started', $locked, (int) $learner->getKey(), null, ['attempt_no' => $attempt->attempt_no]) === null) {
                throw new RuntimeException('Unable to record quiz audit.');
            }

            return $attempt;
        });
    }

    private function pastGrace(QuizAttempt $attempt): bool
    {
        return $attempt->deadline_at !== null && now()->gt($attempt->deadline_at->copy()->addSeconds((int) config('assessment.grace_seconds')));
    }
}
