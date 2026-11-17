<?php

declare(strict_types=1);

namespace App\Domain\Live\Actions;

use App\Domain\Entitlements\EntitlementService;
use App\Domain\Live\JoinRefusedException;
use App\Domain\Live\JoinWindow;
use App\Models\CurriculumItem;
use App\Models\LiveSession;
use App\Models\User;

/**
 * The only way to the meeting link. The learner must pass the same access decision as every other curriculum item
 * (entitlement, published, drip, sequence), hold a seat when the session belongs to a cohort, and ask inside the join
 * window. The link is returned to the caller for a redirect; it is never rendered into a page.
 */
final class GetJoinLink
{
    public function __construct(private readonly EntitlementService $access) {}

    public function __invoke(User $learner, CurriculumItem $item): string
    {
        $session = $item->itemable;
        if (! $session instanceof LiveSession || $session->archived_at !== null || ! $this->access->canAccessItem($learner, $item)->allowed) {
            throw new JoinRefusedException(JoinRefusedException::NO_ACCESS);
        }
        if ($session->cohort_id !== null && ! ($session->cohort?->hasSeat($learner) ?? false)) {
            throw new JoinRefusedException(JoinRefusedException::NO_ACCESS);
        }
        if (JoinWindow::state($session) !== JoinWindow::JOINABLE) {
            throw new JoinRefusedException(JoinRefusedException::NOT_OPEN, __('live.join_not_open'));
        }
        if (blank($session->meeting_url)) {
            throw new JoinRefusedException(JoinRefusedException::NO_LINK, __('live.join_no_link'));
        }

        return (string) $session->meeting_url;
    }
}
