<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Notifications\NotificationMatrix;
use App\Domain\Notifications\NotificationTemplate;
use App\Domain\Notifications\NotificationTemplates;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Saves the admin wording for one matrix event (AR and EN). Needs `system.settings.manage`. Only the placeholders the event
 * allows may appear, no markup, bounded length; a blank text restores the shipped default. Audited.
 */
final class SaveNotificationTemplate
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param array{title_ar?: string|null, title_en?: string|null, body_ar?: string|null, body_en?: string|null, is_active?: bool} $data */
    public function __invoke(User $actor, string $eventKey, array $data): NotificationTemplate
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('system.settings.manage')) {
            throw new AuthorizationException;
        }

        $row = NotificationMatrix::find($eventKey);
        if ($row === null || ! $row->editable()) {
            throw ValidationException::withMessages(['event_key' => __('notification_events.template_not_editable')]);
        }

        $values = [
            'title_ar' => NotificationTemplates::clean($data['title_ar'] ?? null, $row, 'title_ar', NotificationTemplates::TITLE_MAX),
            'title_en' => NotificationTemplates::clean($data['title_en'] ?? null, $row, 'title_en', NotificationTemplates::TITLE_MAX),
            'body_ar' => NotificationTemplates::clean($data['body_ar'] ?? null, $row, 'body_ar', NotificationTemplates::BODY_MAX),
            'body_en' => NotificationTemplates::clean($data['body_en'] ?? null, $row, 'body_en', NotificationTemplates::BODY_MAX),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ];

        return DB::transaction(function () use ($actor, $eventKey, $values): NotificationTemplate {
            $template = NotificationTemplate::query()->lockForUpdate()->firstOrNew(['event_key' => $eventKey]);
            $before = $template->exists ? $template->only(['title_ar', 'title_en', 'body_ar', 'body_en', 'is_active']) : null;
            $template->fill($values);
            $template->setAttribute('updated_by', $actor->getKey());
            $template->save();

            if ($this->audit->record('notification_template.updated', $template, (int) $actor->getKey(), $before, $values, ['event' => $eventKey]) === null) {
                throw new RuntimeException('Unable to record notification template audit.');
            }

            return $template;
        });
    }
}
