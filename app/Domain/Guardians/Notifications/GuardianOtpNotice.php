<?php

declare(strict_types=1);

namespace App\Domain\Guardians\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** The one-time code, by email only: it is never written to the in-app inbox. The code changes on each request, so no dedupe key. */
final class GuardianOtpNotice extends Notification
{
    public function __construct(private readonly string $code, private readonly int $minutes) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('guardian.otp.mail_subject'))
            ->line(__('guardian.otp.mail_body', ['code' => $this->code, 'minutes' => $this->minutes]))
            ->line(__('guardian.otp.mail_ignore'));
    }
}
