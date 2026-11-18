<?php

declare(strict_types=1);

namespace App\Domain\Assessment\Actions;

use App\Domain\Assessment\QuizAccess;
use App\Domain\Assignments\ReviewAccess;
use App\Domain\Audit\AuditLogger;
use App\Models\QuizAttempt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class ReleaseQuizResult
{
    public function __construct(private readonly AuditLogger $audit) {}

    public static function canRelease(QuizAttempt $attempt): bool
    {
        return $attempt->status === QuizAttempt::SUBMITTED && ! $attempt->needs_review
            && ($attempt->settings['result_visibility'] ?? null) === 'hidden' && $attempt->released_at === null;
    }

    public function __invoke(User $reviewer, QuizAttempt $attempt): void
    {
        DB::transaction(function () use ($reviewer, $attempt): void {
            $locked = QuizAttempt::query()->lockForUpdate()->findOrFail($attempt->getKey());
            ReviewAccess::authorize($reviewer, QuizAccess::courseOf($locked->quiz));
            if (! self::canRelease($locked)) {
                return;
            }

            $locked->update(['released_at' => now()]);
            if ($this->audit->record('quiz.result_released', $locked->quiz, (int) $reviewer->getKey(), null, null, ['attempt_id' => $locked->getKey()]) === null) {
                throw new RuntimeException('Unable to record quiz audit.');
            }
        });
    }
}
