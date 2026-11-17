<?php

declare(strict_types=1);

namespace App\Domain\Live\Actions;

use App\Domain\Live\LiveSessionTransition;
use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Opens a scheduled session for joining. It needs a meeting link first, so learners are never sent to nothing. */
final class OpenLiveSession
{
    public function __construct(private readonly LiveSessionTransition $transition) {}

    public function __invoke(User $actor, LiveSession $session): LiveSession
    {
        if (blank($session->meeting_url)) {
            throw ValidationException::withMessages(['meeting_url' => __('live.link_needed_to_open')]);
        }

        return $this->transition->apply($actor, $session, [LiveSession::SCHEDULED], LiveSession::OPEN, 'live_session.opened');
    }
}
