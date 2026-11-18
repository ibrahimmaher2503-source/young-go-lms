<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Queries;

use App\Domain\Identity\GuardianAccess;
use App\Domain\Notifications\Actions\SetPhoneReminderOptIn;
use App\Domain\Notifications\NotificationEventDefinition;
use App\Domain\Notifications\NotificationMatrix;
use App\Domain\Notifications\NotificationOptOut;
use App\Models\User;
use Illuminate\Support\Facades\Config;

/** What the learner preferences page shows: optional notices with their current choice, the always-sent list, and phone reminder consent. */
final class NotificationPreferenceOverview
{
    /**
     * @return array{optional: list<array{event: NotificationEventDefinition, enabled: bool}>, mandatory: list<NotificationEventDefinition>, email: bool, phone: list<array{channel: string, enabled: bool}>}
     */
    public function for(User $user): array
    {
        $off = NotificationOptOut::query()->where('user_id', $user->getKey())->pluck('event_key')->all();
        $preferences = json_decode((string) $user->getAttribute('notification_preferences'), true);

        $drivers = Config::array('live.phone_reminders');
        $optIns = (array) $user->getAttribute('phone_reminder_opt_ins');
        $phone = [];
        if (filled($user->getAttribute('phone')) && ! GuardianAccess::isMinor($user)) {
            foreach (SetPhoneReminderOptIn::CHANNELS as $channel) {
                if (filled($drivers[$channel] ?? null)) {
                    $phone[] = ['channel' => $channel, 'enabled' => ($optIns[$channel] ?? null) === $user->getAttribute('phone')];
                }
            }
        }

        return [
            'optional' => array_map(static fn (NotificationEventDefinition $event): array => ['event' => $event, 'enabled' => ! in_array($event->key, $off, true)], NotificationMatrix::optional()),
            'mandatory' => NotificationMatrix::mandatory(),
            'email' => ! is_array($preferences) || ($preferences['email'] ?? true) !== false,
            'phone' => $phone,
        ];
    }
}
