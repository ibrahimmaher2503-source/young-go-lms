<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

/**
 * One row of the event to template matrix (PRD §20). Mandatory events are transactional or safety related and cannot be
 * opted out of. A pending row has a localized template but no source event yet; the owning task wires it.
 */
final readonly class NotificationEventDefinition
{
    public const string WIRED = 'wired';

    public const string FRAMEWORK = 'framework';

    public const string PENDING = 'pending';

    /**
     * @param  list<string>  $placeholders  the only :tokens a template may use
     * @param  string|null  $titleKey  language key of the shipped title; null when the text is not editable here
     * @param  string|null  $note  for a pending row, the task that owns the source event
     */
    public function __construct(
        public string $key,
        public string $group,
        public bool $mandatory,
        public string $status,
        public array $placeholders,
        public ?string $titleKey,
        public ?string $bodyKey,
        public ?string $note = null,
    ) {}

    public function optional(): bool
    {
        return ! $this->mandatory;
    }

    /** Whether an admin may change the wording (account mails are sent by the framework, not through the dispatcher). */
    public function editable(): bool
    {
        return $this->titleKey !== null && $this->bodyKey !== null;
    }

    /** Form-safe name (no dots) for request fields. */
    public function formKey(): string
    {
        return str_replace('.', '_', $this->key);
    }

    public function label(): string
    {
        return (string) __('notification_events.events.'.$this->formKey());
    }
}
