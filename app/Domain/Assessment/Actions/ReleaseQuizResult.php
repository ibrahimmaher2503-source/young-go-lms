<?php

declare(strict_types=1);

namespace App\Domain\Assessment\Actions;

use App\Domain\Assessment\Notifications\EssayReviewedNotice;
use App\Domain\Assessment\Notifications\QuizResultNotice;
use App\Domain\Assessment\QuizAccess;
use App\Domain\Assignments\ReviewAccess;
use App\Domain\Audit\AuditLogger;
use App\Domain\Notifications\NotificationDispatcher;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class ReleaseQuizResult
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotificationDispatcher $notifications,
    ) {}

    public static function canRelease(QuizAttempt $attempt): bool
    {
        return $attempt->status === QuizAttempt::SUBMITTED && ! $attempt->needs_review
            && ($attempt->settings['result_visibility'] ?? null) === 'hidden' && $attempt->released_at === null;
    }

    public function __invoke(User $reviewer, QuizAttempt $attempt): void
    {
        $released = DB::transaction(function () use ($reviewer, $attempt): ?QuizAttempt {
            $locked = QuizAttempt::query()->lockForUpdate()->findOrFail($attempt->getKey());
            ReviewAccess::authorize($reviewer, QuizAccess::courseOf($locked->quiz));
            if (! self::canRelease($locked)) {
                return null;
            }

            $locked->update(['released_at' => now()]);
            if ($this->audit->record('quiz.result_released', $locked->quiz, (int) $reviewer->getKey(), null, null, ['attempt_id' => $locked->getKey()]) === null) {
                throw new RuntimeException('Unable to record quiz audit.');
            }

            return $locked;
        });

        if ($released !== null) {
            $this->notify($released);
        }
    }

    /** Sent after the release commits; a notification failure never rolls back release. */
    private function notify(QuizAttempt $attempt): void
    {
        $learner = $attempt->learner;
        $item = $attempt->quiz->item()->first();
        if (! $learner instanceof User || $item === null) {
            return;
        }

        try {
            $hadEssayReview = $attempt->answers()->whereNotNull('graded_at')->exists(); // graded_at is only set by GradeEssayAnswer (human review)
            $notice = $hadEssayReview
                ? new EssayReviewedNotice($attempt, (int) $item->course_id, (int) $item->getKey())
                : new QuizResultNotice($attempt, (int) $item->course_id, (int) $item->getKey());

            $this->notifications->send($learner, $notice);
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
