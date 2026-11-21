<?php

declare(strict_types=1);

namespace App\Domain\Support;

use App\Domain\Identity\Models\ViewAsSession;
use App\Domain\Support\Actions\StopViewAs;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/** The signed-in staff member's live view-as session, if any. A session past its time limit is ended here and audited. */
final class ViewAsState
{
    public const string SESSION_KEY = 'view_as_session_id';

    public function __construct(private readonly StopViewAs $stop, private readonly ViewAsEligibility $eligibility) {}

    public function current(): ?ViewAsSession
    {
        $user = Auth::user();
        $id = Session::get(self::SESSION_KEY);
        if (! $user instanceof User || ! is_int($id)) {
            return null;
        }

        $session = ViewAsSession::query()->with('learner')->find($id);
        if ($session === null || $session->staff_id !== $user->getKey() || $session->ended_at !== null) {
            Session::forget(self::SESSION_KEY);

            return null;
        }

        if ($session->isExpired()) {
            ($this->stop)($session, ViewAsSession::END_EXPIRED);

            return null;
        }

        $learner = $session->learner;
        if (! $learner instanceof User || $this->eligibility->redeemRefusal($user, $learner) !== null) {
            ($this->stop)($session, ViewAsSession::END_REVOKED);

            return null;
        }

        return $session;
    }
}
