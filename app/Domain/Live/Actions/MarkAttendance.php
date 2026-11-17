<?php

declare(strict_types=1);

namespace App\Domain\Live\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Completion\CompletionTrigger;
use App\Domain\Live\LiveAccess;
use App\Domain\Live\LiveSessionNotices;
use App\Models\LiveAttendance;
use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Marks a learner for a session that is open or finished. One mark per learner: changing it afterwards is a correction
 * (CorrectAttendance), which needs a reason and keeps the earlier value in the audit trail.
 */
final class MarkAttendance
{
    public function __construct(private readonly AuditLogger $audit, private readonly LiveSessionNotices $audience) {}

    public function __invoke(User $actor, LiveSession $session, User $learner, string $status, ?string $note = null): LiveAttendance
    {
        LiveAccess::authorize($actor, $session->course);
        if (! in_array($status, LiveAttendance::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => __('live.attendance.invalid_status')]);
        }
        if (! in_array($session->status, [LiveSession::OPEN, LiveSession::COMPLETED], true)) {
            throw ValidationException::withMessages(['status' => __('live.attendance.session_not_started')]);
        }
        $note = $note === null ? null : mb_substr(trim($note), 0, 500);

        $row = DB::transaction(function () use ($actor, $session, $learner, $status, $note): LiveAttendance {
            $existing = LiveAttendance::query()->where('live_session_id', $session->getKey())->where('learner_id', $learner->getKey())->first();
            if ($existing !== null) {
                throw ValidationException::withMessages(['learner' => __('live.attendance.already_marked')]);
            }
            if (! $this->audience->audience($session)->contains(fn (User $user): bool => $user->is($learner))) {
                throw ValidationException::withMessages(['learner' => __('live.attendance.not_in_audience')]);
            }

            try {
                $row = LiveAttendance::query()->create([
                    'live_session_id' => $session->getKey(), 'learner_id' => $learner->getKey(), 'status' => $status,
                    'source' => LiveAttendance::SOURCE_MANUAL, 'marked_by' => $actor->getKey(), 'marked_at' => now(), 'note' => $note,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['learner' => __('live.attendance.already_marked')]);
            }

            if ($this->audit->record('live_attendance.marked', $session, (int) $actor->getKey(), null, ['learner_id' => $learner->getKey(), 'status' => $status]) === null) {
                throw new RuntimeException('Unable to record attendance audit.');
            }

            return $row;
        });

        CompletionTrigger::changed($learner, (int) $session->course_id);

        return $row;
    }
}
