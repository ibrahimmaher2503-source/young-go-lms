<?php

declare(strict_types=1);

namespace App\Domain\Live\Notifications;

use App\Domain\Live\LiveSessionTime;
use App\Domain\Notifications\LocalizedNotification;
use App\Models\LiveSession;
use App\Models\User;

/** One notice about a live session: scheduled, rescheduled, cancelled or a reminder. The time is shown in the recipient's own timezone. */
final class LiveSessionNotice extends LocalizedNotification
{
    public const string SCHEDULED = 'scheduled';

    public const string RESCHEDULED = 'rescheduled';

    public const string CANCELLED = 'cancelled';

    public const string REMINDER = 'reminder';

    /** @param string $variant what makes this message distinct for the same kind (a start time, a reminder offset) */
    public function __construct(
        private readonly LiveSession $session,
        private readonly int $courseId,
        private readonly int $itemId,
        private readonly string $kind,
        private readonly string $variant = '',
    ) {}

    public function dedupeKey(): string
    {
        return "live:{$this->session->getKey()}:{$this->kind}".($this->variant === '' ? '' : ':'.$this->variant);
    }

    protected function title(User $recipient): string
    {
        return __("live.notice.{$this->kind}.title", ['title' => (string) $this->session->title]);
    }

    protected function body(User $recipient): string
    {
        return __("live.notice.{$this->kind}.body", ['title' => (string) $this->session->title, 'time' => LiveSessionTime::label($this->session, $recipient)]);
    }

    protected function url(User $recipient): string
    {
        return route('learn.show', ['course' => $this->courseId, 'item' => $this->itemId]);
    }
}
