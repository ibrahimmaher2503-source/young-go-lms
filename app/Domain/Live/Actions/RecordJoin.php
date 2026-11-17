<?php

declare(strict_types=1);

namespace App\Domain\Live\Actions;

use App\Domain\Completion\CompletionTrigger;
use App\Models\LiveAttendance;
use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Config;

/**
 * Notes that a learner used the join link: present, or late when it happened well after the start. It never overwrites
 * a mark that already exists (a person may have marked or corrected it), and a refused join records nothing.
 */
final class RecordJoin
{
    public function __invoke(LiveSession $session, User $learner): void
    {
        $lateAfter = (int) Config::get('live.late_after_minutes', 10);
        $status = now()->gt($session->starts_at->copy()->addMinutes($lateAfter)) ? LiveAttendance::LATE : LiveAttendance::PRESENT;

        try {
            $row = LiveAttendance::query()->firstOrCreate(
                ['live_session_id' => $session->getKey(), 'learner_id' => $learner->getKey()],
                ['status' => $status, 'source' => LiveAttendance::SOURCE_JOIN, 'marked_at' => now()],
            );
            if ($row->wasRecentlyCreated) {
                CompletionTrigger::changed($learner, (int) $session->course_id);
            }
        } catch (UniqueConstraintViolationException) {
            // A concurrent join created the row first; nothing more to do.
        }
    }
}
