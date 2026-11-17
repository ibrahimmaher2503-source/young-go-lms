<?php

declare(strict_types=1);

namespace App\Domain\Live\Actions;

use App\Domain\Live\LiveSessionNotices;
use App\Domain\Live\LiveSessionTransition;
use App\Domain\Live\Notifications\LiveSessionNotice;
use App\Models\LiveSession;
use App\Models\User;

/** Cancels a session that has not finished. The record stays for the audit trail. */
final class CancelLiveSession
{
    public function __construct(private readonly LiveSessionTransition $transition, private readonly LiveSessionNotices $notices) {}

    public function __invoke(User $actor, LiveSession $session): LiveSession
    {
        $cancelled = $this->transition->apply($actor, $session, [LiveSession::SCHEDULED, LiveSession::OPEN], LiveSession::CANCELLED, 'live_session.cancelled');
        $this->notices->send($cancelled, LiveSessionNotice::CANCELLED);

        return $cancelled;
    }
}
