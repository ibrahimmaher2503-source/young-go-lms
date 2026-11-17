<?php

declare(strict_types=1);

namespace App\Domain\Live\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Completion\CompletionTrigger;
use App\Domain\Live\LiveAccess;
use App\Models\LiveAttendance;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Changes an existing mark. A reason is required, and the audit trail keeps who changed what, from which value, and why. */
final class CorrectAttendance
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, LiveAttendance $attendance, string $status, string $reason): LiveAttendance
    {
        LiveAccess::authorize($actor, $attendance->session->course);
        if (! in_array($status, LiveAttendance::STATUSES, true)) {
            throw ValidationException::withMessages(['status' => __('live.attendance.invalid_status')]);
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => __('live.attendance.reason_required')]);
        }

        $row = DB::transaction(function () use ($actor, $attendance, $status, $reason): LiveAttendance {
            $locked = LiveAttendance::query()->whereKey($attendance->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->status === $status) {
                throw ValidationException::withMessages(['status' => __('live.attendance.no_change')]);
            }
            $before = ['status' => $locked->status, 'source' => $locked->source, 'marked_by' => $locked->marked_by];
            $locked->update(['status' => $status, 'source' => LiveAttendance::SOURCE_MANUAL, 'marked_by' => $actor->getKey(), 'marked_at' => now(), 'note' => $reason]);

            if ($this->audit->record('live_attendance.corrected', $locked->session, (int) $actor->getKey(), $before, ['learner_id' => $locked->learner_id, 'status' => $status, 'reason' => $reason]) === null) {
                throw new RuntimeException('Unable to record attendance audit.');
            }

            return $locked;
        });

        CompletionTrigger::changed((int) $row->learner_id, (int) $attendance->session->course_id);

        return $row;
    }
}
