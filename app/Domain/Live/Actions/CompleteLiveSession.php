<?php

declare(strict_types=1);

namespace App\Domain\Live\Actions;

use App\Domain\Live\LiveSessionTransition;
use App\Models\LiveSession;
use App\Models\User;

/** Marks an open session as finished. */
final class CompleteLiveSession
{
    public function __construct(private readonly LiveSessionTransition $transition) {}

    public function __invoke(User $actor, LiveSession $session): LiveSession
    {
        return $this->transition->apply($actor, $session, [LiveSession::OPEN], LiveSession::COMPLETED, 'live_session.completed');
    }
}
