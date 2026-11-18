<?php

declare(strict_types=1);

namespace App\Domain\Settings\Listeners;

use App\Domain\Settings\GeneralSettings;
use Illuminate\Mail\Events\MessageSending;
use Symfony\Component\Mime\Address;
use Throwable;

/** Applies the admin-set sender name/address and reply-to to outgoing mail; unset values leave the mail config untouched. */
final class ApplyMailSenderIdentity
{
    public function handle(MessageSending $event): void
    {
        try {
            $settings = app(GeneralSettings::class);
        } catch (Throwable) {
            return;
        }

        if (filled($settings->mail_from_address) || filled($settings->mail_from_name)) {
            $current = $event->message->getFrom()[0] ?? null;
            $address = filled($settings->mail_from_address) ? (string) $settings->mail_from_address : $current?->getAddress();
            $name = filled($settings->mail_from_name) ? (string) $settings->mail_from_name : ($current?->getName() ?? '');

            if ($address !== null) {
                $event->message->from(new Address($address, $name));
            }
        }

        if (filled($settings->mail_reply_to) && $event->message->getReplyTo() === []) {
            $event->message->replyTo((string) $settings->mail_reply_to);
        }
    }
}
