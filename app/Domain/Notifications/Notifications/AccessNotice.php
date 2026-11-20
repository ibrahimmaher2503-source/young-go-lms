<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Notifications;

use App\Domain\Notifications\LocalizedNotification;
use App\Domain\Notifications\NotificationTemplates;
use App\Models\Course;
use App\Models\User;
use Carbon\CarbonInterface;

final class AccessNotice extends LocalizedNotification
{
    public function __construct(private readonly string $eventKey, private readonly Course $course, private readonly string $dedupeSuffix, private readonly ?CarbonInterface $endsAt = null) {}

    public function event(): string
    {
        return $this->eventKey;
    }

    public function dedupeKey(): string
    {
        return $this->eventKey.':'.$this->dedupeSuffix;
    }

    protected function title(User $recipient): string
    {
        return $this->text('title', $recipient);
    }

    protected function body(User $recipient): string
    {
        return $this->text('body', $recipient);
    }

    protected function url(User $recipient): ?string
    {
        return $this->eventKey !== 'access.revoked' ? route('learn.course', ['course' => $this->course->getKey()]) : null;
    }

    private function text(string $field, User $recipient): string
    {
        $values = ['course' => $this->course->getTranslation('title', app()->getLocale())];
        if ($this->endsAt !== null) {
            $timezone = is_string($recipient->timezone) && in_array($recipient->timezone, timezone_identifiers_list(), true)
                ? $recipient->timezone
                : (string) config('app.timezone');
            $values['date'] = $this->endsAt->copy()->setTimezone($timezone)->locale(app()->getLocale())->isoFormat('LL');
        }
        $key = 'notification_events.templates.'.str_replace('.', '_', $this->eventKey).'.'.$field;

        return NotificationTemplates::render($this->eventKey, $field, $values, (string) __($key, $values));
    }
}
