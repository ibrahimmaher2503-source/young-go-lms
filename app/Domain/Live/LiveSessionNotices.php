<?php

declare(strict_types=1);

namespace App\Domain\Live;

use App\Domain\Entitlements\CourseLearners;
use App\Domain\Live\Notifications\LiveSessionNotice;
use App\Domain\Notifications\NotificationDispatcher;
use App\Models\CurriculumItem;
use App\Models\LiveSession;
use App\Models\User;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Tells the people who may join a session about it. The audience is the learners with current access to the course who
 * hold a seat in the session cohort (when it has one). Nothing is sent for a session that learners cannot see yet
 * (a draft item or an unpublished section). One failing recipient never stops the others.
 */
final class LiveSessionNotices
{
    public function __construct(private readonly NotificationDispatcher $dispatcher) {}

    /** @return int how many people were notified now (people already told are skipped by the dedupe key) */
    public function send(LiveSession $session, string $kind, string $variant = ''): int
    {
        $item = $session->item()->with('section')->first();
        if (! $item instanceof CurriculumItem || $item->status !== CurriculumItem::STATUS_PUBLISHED || ! $item->section?->published) {
            return 0;
        }

        $sent = 0;
        foreach ($this->audience($session) as $recipient) {
            try {
                $notice = new LiveSessionNotice($session, (int) $session->course_id, (int) $item->getKey(), $kind, $variant);
                $sent += $this->dispatcher->send($recipient, $notice) ? 1 : 0;
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        return $sent;
    }

    /** @return Collection<int, User> */
    public function audience(LiveSession $session): Collection
    {
        return CourseLearners::query($session->course)->get()
            ->filter(fn (User $learner): bool => $learner->getAttribute('access_status') === 'active')
            ->filter(fn (User $learner): bool => $session->cohort_id === null || ($session->cohort?->hasSeat($learner) ?? false))
            ->values();
    }
}
