<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Notifications\NotificationMatrix;
use App\Domain\Notifications\NotificationOptOut;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

/**
 * Saves a user's choices for optional notices. Mandatory (transactional) notices cannot be switched off: asking to do so
 * is refused and nothing is saved. Phone reminder consent still goes through SetPhoneReminderOptIn. Changes are audited.
 */
final class UpdateNotificationPreferences
{
    public function __construct(private readonly AuditLogger $audit, private readonly SetPhoneReminderOptIn $phone) {}

    /**
     * @param  array<string, bool>  $enabled  matrix event key => wants to receive it
     * @param  array<string, bool>  $phoneChannels  channel => consent
     */
    public function __invoke(User $user, array $enabled, array $phoneChannels = []): void
    {
        foreach (array_keys($enabled) as $key) {
            $row = NotificationMatrix::find((string) $key);
            if ($row === null || $row->mandatory) {
                throw ValidationException::withMessages(['enabled' => __('notification_events.cannot_opt_out')]);
            }
        }

        DB::transaction(function () use ($user, $enabled): void {
            foreach ($enabled as $key => $wants) {
                $existing = NotificationOptOut::query()->where('user_id', $user->getKey())->where('event_key', $key)->first();
                if ($wants && $existing !== null) {
                    $existing->delete();
                } elseif (! $wants && $existing === null) {
                    NotificationOptOut::query()->create(['user_id' => $user->getKey(), 'event_key' => $key]);
                } else {
                    continue;
                }

                if ($this->audit->record('notifications.preference_changed', $user, (int) $user->getKey(), ['enabled' => ! $wants], ['enabled' => $wants], ['event' => $key]) === null) {
                    throw new RuntimeException('Unable to record notification preference audit.');
                }
            }
        });

        foreach ($phoneChannels as $channel => $consent) {
            try {
                ($this->phone)($user, (string) $channel, $consent);
            } catch (InvalidArgumentException) {
                throw ValidationException::withMessages(['phone' => __('notification_events.phone_unavailable')]);
            }
        }
    }
}
