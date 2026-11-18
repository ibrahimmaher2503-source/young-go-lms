<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Notifications;

use App\Domain\Notifications\Announcement;
use App\Domain\Notifications\LocalizedNotification;
use App\Domain\Notifications\NotificationMatrix;
use App\Domain\Notifications\NotificationTemplates;
use App\Models\User;

/** One course announcement, in the recipient's language. Optional: learners may turn it off. */
final class AnnouncementNotice extends LocalizedNotification
{
    public function __construct(private readonly Announcement $announcement) {}

    public function dedupeKey(): string
    {
        return 'announcement:'.$this->announcement->getKey();
    }

    public function event(): string
    {
        return NotificationMatrix::ANNOUNCEMENT;
    }

    protected function title(User $recipient): string
    {
        return $this->text('title');
    }

    protected function body(User $recipient): string
    {
        return $this->text('body');
    }

    protected function url(User $recipient): string
    {
        return route('learn.course', ['course' => $this->announcement->course_id]);
    }

    private function text(string $field): string
    {
        $locale = app()->getLocale() === 'ar' ? 'ar' : 'en';
        $values = [
            'course' => (string) $this->announcement->course->title,
            'title' => $this->announcement->titleIn($locale),
            'message' => $this->announcement->bodyIn($locale),
        ];
        $default = $field === 'title' ? $values['title'] : $values['message'];

        return NotificationTemplates::render(NotificationMatrix::ANNOUNCEMENT, $field, $values, $default);
    }
}
