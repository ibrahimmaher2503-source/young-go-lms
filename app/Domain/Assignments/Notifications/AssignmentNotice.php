<?php

declare(strict_types=1);

namespace App\Domain\Assignments\Notifications;

use App\Domain\Notifications\LocalizedNotification;
use App\Domain\Notifications\NotificationTemplates;
use App\Models\Assignment;
use App\Models\User;

final class AssignmentNotice extends LocalizedNotification
{
    public const string PUBLISHED = 'assignment.published';

    public const string DUE = 'assignment.due';

    public const string SUBMITTED = 'assignment.submitted';

    public function __construct(
        private readonly Assignment $assignment,
        private readonly string $eventKey,
        private readonly string $dedupe,
        private readonly string $link,
    ) {}

    public function dedupeKey(): string
    {
        return $this->dedupe;
    }

    public function event(): string
    {
        return $this->eventKey;
    }

    protected function title(User $recipient): string
    {
        return $this->text('title', $recipient);
    }

    protected function body(User $recipient): string
    {
        return $this->text('body', $recipient);
    }

    protected function url(User $recipient): string
    {
        return $this->link;
    }

    private function text(string $field, User $recipient): string
    {
        $locale = app()->getLocale();
        $timezone = $recipient->getAttribute('timezone');
        $timezone = is_string($timezone) && in_array($timezone, timezone_identifiers_list(\DateTimeZone::ALL_WITH_BC), true) ? $timezone : 'UTC';
        $values = [
            'title' => $this->assignment->getTranslation('title', $locale),
            'date' => $this->eventKey === self::DUE
                ? $this->assignment->due_at?->copy()->setTimezone($timezone)->locale($locale)->isoFormat('lll')
                : null,
        ];
        $key = 'notification_events.templates.'.str_replace('.', '_', $this->eventKey).'.'.$field;

        return NotificationTemplates::render($this->eventKey, $field, $values, __($key, $values));
    }
}
