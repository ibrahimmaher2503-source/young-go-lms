<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use App\Models\User;

/**
 * What a user has chosen for optional notices. Mandatory and unlabelled notices are never suppressed: a notice without an
 * event key, or with a mandatory key, always goes out. The email switch on the profile page (notification_preferences.email)
 * only affects optional notices; mandatory ones keep their email copy.
 */
final class NotificationPreferences
{
    public static function isOptional(?string $event): bool
    {
        return NotificationMatrix::find($event)?->optional() === true;
    }

    /** Whether this notice may be sent to the recipient at all. */
    public static function allows(User $recipient, ?string $event): bool
    {
        if ($event === null || ! self::isOptional($event)) {
            return true;
        }

        return ! NotificationOptOut::query()->where('user_id', $recipient->getKey())->where('event_key', $event)->exists();
    }

    /** Whether the email copy may be sent (in-app delivery is decided by allows()). */
    public static function emailAllowed(object $notifiable, ?string $event): bool
    {
        if (! $notifiable instanceof User || ! self::isOptional($event)) {
            return true;
        }

        $preferences = json_decode((string) $notifiable->getAttribute('notification_preferences'), true);

        return ! is_array($preferences) || ($preferences['email'] ?? true) !== false;
    }
}
