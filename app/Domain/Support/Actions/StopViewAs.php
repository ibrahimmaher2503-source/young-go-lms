<?php

declare(strict_types=1);

namespace App\Domain\Support\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\Models\ViewAsSession;
use App\Domain\Support\ViewAsState;
use Illuminate\Support\Facades\Session;

/** Ends a view-as session (staff pressed Stop, the time limit passed, or a new session replaced it) and audits it once. */
final class StopViewAs
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(ViewAsSession $session, string $cause = ViewAsSession::END_MANUAL): void
    {
        if ($session->ended_at === null) {
            // Close first so a concurrent request cannot audit the same end twice.
            $closed = ViewAsSession::query()->whereKey($session->getKey())->whereNull('ended_at')
                ->update(['ended_at' => now(), 'end_cause' => $cause]);

            if ($closed === 1) {
                $session->refresh();
                $this->audit->record('support.view_as_stopped', $session, $session->staff_id, null, null, [
                    'learner_id' => $session->learner_id, 'cause' => $cause, 'page_views' => $session->page_views,
                ]);
            }
        }

        if (Session::get(ViewAsState::SESSION_KEY) === $session->getKey()) {
            Session::forget(ViewAsState::SESSION_KEY);
        }
    }
}
