<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Notifications;

use App\Domain\Notifications\LocalizedNotification;
use App\Domain\Notifications\NotificationMatrix;
use App\Domain\Notifications\NotificationTemplates;
use App\Models\User;

/** A notice for one matrix event, built from its (overridable) template and plain placeholder values. */
final class TemplatedNotice extends LocalizedNotification
{
    /** @param array<string, scalar|null> $values */
    public function __construct(private readonly string $eventKey, private readonly array $values, private readonly string $dedupe, private readonly ?string $link = null) {}

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
        return $this->text('title');
    }

    protected function body(User $recipient): string
    {
        return $this->text('body');
    }

    protected function url(User $recipient): ?string
    {
        return $this->link;
    }

    private function text(string $field): string
    {
        $row = NotificationMatrix::find($this->eventKey);
        $key = $field === 'title' ? $row?->titleKey : $row?->bodyKey;
        $default = $key === null ? $this->eventKey : (string) __($key, array_map(static fn (mixed $v): string => (string) $v, $this->values));

        return NotificationTemplates::render($this->eventKey, $field, $this->values, $default);
    }
}
