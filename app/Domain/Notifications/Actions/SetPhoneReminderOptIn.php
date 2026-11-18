<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Identity\GuardianAccess;
use App\Domain\Shared\Phone\PhoneNumbers;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/** Per-channel phone reminder consent, bound to the current canonical E.164 number. Minors cannot opt in (O-015 default). Audited without the number. */
final class SetPhoneReminderOptIn
{
    public const array CHANNELS = ['whatsapp', 'sms'];

    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $user, string $channel, bool $enabled): void
    {
        if (! in_array($channel, self::CHANNELS, true)) {
            throw new InvalidArgumentException('Unsupported phone reminder channel.');
        }

        DB::transaction(function () use ($user, $channel, $enabled): void {
            $account = User::query()->lockForUpdate()->findOrFail($user->getKey());
            $optIns = (array) $account->getAttribute('phone_reminder_opt_ins');
            $was = isset($optIns[$channel]);

            if ($enabled) {
                if (GuardianAccess::isMinor($account)) {
                    throw new InvalidArgumentException('Phone reminders are not available for minors.');
                }

                $phone = (string) $account->getAttribute('phone');
                $region = str_starts_with($phone, '+966') ? 'SA' : 'EG';
                if (PhoneNumbers::normalize($phone, $region) !== $phone) {
                    throw new InvalidArgumentException('Phone reminder consent requires a canonical phone number.');
                }

                $optIns[$channel] = $phone;
            } else {
                unset($optIns[$channel]);
            }

            $account->forceFill(['phone_reminder_opt_ins' => $optIns])->save();

            if ($was !== $enabled && $this->audit->record('notifications.phone_opt_in_changed', $account, (int) $account->getKey(), ['enabled' => $was], ['enabled' => $enabled], ['channel' => $channel]) === null) {
                throw new RuntimeException('Unable to record phone opt-in audit.');
            }
        });
    }
}
