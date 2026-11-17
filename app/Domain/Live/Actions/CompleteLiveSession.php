<?php

declare(strict_types=1);

namespace App\Domain\Live\Actions;

use App\Domain\Completion\CompletionTrigger;
use App\Domain\Live\LiveSessionTransition;
use App\Models\LiveAttendance;
use App\Models\LiveSession;
use App\Models\User;

/** Marks an open session as finished. A finished session starts to count for attendance, so marked learners are re-checked. */
final class CompleteLiveSession
{
    public function __construct(private readonly LiveSessionTransition $transition) {}

    public function __invoke(User $actor, LiveSession $session): LiveSession
    {
        $done = $this->transition->apply($actor, $session, [LiveSession::OPEN], LiveSession::COMPLETED, 'live_session.completed');

        foreach (LiveAttendance::query()->where('live_session_id', $done->getKey())->pluck('learner_id') as $learnerId) {
            CompletionTrigger::changed((int) $learnerId, (int) $done->course_id);
        }

        return $done;
    }
}
