<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Domain\Identity\GuardianAccess;
use App\Domain\Notifications\LocalizedNotification;
use App\Domain\Notifications\PhoneChannelResolver;
use App\Domain\Notifications\PhoneTemplates;
use App\Domain\Notifications\QuietHours;
use App\Domain\Shared\Phone\PhoneNumbers;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Traits\Localizable;
use InvalidArgumentException;

/**
 * Optional WhatsApp/SMS copy of a reminder (YG-GAP-NTF-001). Sends only when the notice names a phone template, the
 * recipient is an adult who consented per channel to their current E.164 number, the channel has a driver, and it is not
 * quiet hours there. A skipped reminder is not queued for later (it would be stale). Logs carry no phone or text.
 */
final class SendPhoneReminder
{
    use Localizable;

    public function __construct(private readonly PhoneChannelResolver $channels) {}

    public function __invoke(User $recipient, LocalizedNotification $notice): void
    {
        $template = $notice->phoneTemplate();
        $phone = $recipient->getAttribute('phone');
        if ($template === null || ! is_string($phone) || blank($phone)) {
            return;
        }

        $optIns = (array) $recipient->getAttribute('phone_reminder_opt_ins');
        $drivers = Config::array('live.phone_reminders');
        $channels = array_values(array_filter(
            SetPhoneReminderOptIn::CHANNELS,
            static fn (string $channel): bool => ($optIns[$channel] ?? null) === $phone && filled($drivers[$channel] ?? null),
        ));
        if ($channels === []) {
            return;
        }

        // Minors never get phone messages (O-015 default); a guardian phone copy needs its own decision.
        if (GuardianAccess::isMinor($recipient)) {
            $this->skip('minor', $template);

            return;
        }

        if (! $this->canonical($phone)) {
            $this->skip('phone_not_canonical', $template);

            return;
        }

        $timezone = $recipient->getAttribute('timezone');
        if (QuietHours::contains(now(), is_string($timezone) ? $timezone : null)) {
            $this->skip('quiet_hours', $template);

            return;
        }

        $preferred = $recipient->preferredLocale();
        $locale = in_array($preferred, ['ar', 'en'], true) ? (string) $preferred : app()->getLocale();
        $body = $this->withLocale($locale, fn () => $notice->toArray($recipient)['message'] ?? null);
        $message = is_string($body) ? PhoneTemplates::render($template, $locale, $body) : null;
        if ($message === null) {
            $this->skip('no_template', $template);

            return;
        }

        foreach ($channels as $channel) {
            $this->channels->for($channel)->send($channel, $phone, $locale, $template, $message, "{$notice->id}:{$channel}");
        }
    }

    private function canonical(string $phone): bool
    {
        try {
            return PhoneNumbers::normalize($phone, str_starts_with($phone, '+966') ? 'SA' : 'EG') === $phone;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    private function skip(string $reason, string $template): void
    {
        Log::info('phone_reminder.skipped', ['reason' => $reason, 'template' => $template]);
    }
}
