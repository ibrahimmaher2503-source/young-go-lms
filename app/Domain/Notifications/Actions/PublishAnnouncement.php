<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Notifications\Announcement;
use App\Domain\Notifications\NotificationDispatcher;
use App\Domain\Notifications\Notifications\AnnouncementNotice;
use App\Domain\Notifications\Queries\AnnouncementAudience;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Publishes a draft and notifies its audience once (dedupe key announcement:{id}). The publish is saved and audited first;
 * notices go out after commit, so a failing mailbox is reported and never undoes it. Publishing twice does nothing.
 */
final class PublishAnnouncement
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly AnnouncementAudience $audience,
        private readonly NotificationDispatcher $dispatcher,
    ) {}

    public function __invoke(User $actor, Announcement $announcement): Announcement
    {
        $course = $announcement->course;
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('update', $course)) {
            throw new AuthorizationException;
        }

        $published = DB::transaction(function () use ($actor, $announcement): ?Announcement {
            $current = Announcement::query()->lockForUpdate()->findOrFail($announcement->getKey());
            if ($current->isPublished()) {
                return null;
            }

            $current->forceFill(['status' => Announcement::PUBLISHED, 'published_at' => now()])->save();
            if ($this->audit->record('announcement.published', $current, (int) $actor->getKey(), ['status' => Announcement::DRAFT], ['status' => Announcement::PUBLISHED], ['course_id' => $current->course_id, 'cohort_id' => $current->cohort_id]) === null) {
                throw new RuntimeException('Unable to record announcement audit.');
            }

            return $current;
        });

        if ($published === null) {
            return $announcement->refresh();
        }

        $sent = 0;
        foreach ($this->audience->for($published) as $recipient) {
            try {
                $sent += $this->dispatcher->send($recipient, new AnnouncementNotice($published)) ? 1 : 0;
            } catch (Throwable $exception) {
                report($exception);
            }
        }
        $published->forceFill(['recipients_count' => $sent])->save();

        return $published;
    }
}
