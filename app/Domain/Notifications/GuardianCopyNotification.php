<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Email copy of a child's notification, sent to a verified guardian in the guardian's own locale. */
final class GuardianCopyNotification extends Notification
{
    public function __construct(public readonly Notification $original, public readonly User $child) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $childName = (string) ($this->child->getAttribute('display_name') ?: $this->child->getAttribute('name'));
        $inner = method_exists($this->original, 'toMail') ? $this->original->toMail($notifiable) : null;
        $subject = $inner instanceof MailMessage && $inner->subject !== '' ? (string) $inner->subject : (string) config('app.name');

        $message = new MailMessage;
        $message->subject(__('notifications.guardian_copy_subject', ['child' => $childName, 'subject' => $subject]))
            ->line(__('notifications.guardian_copy_intro', ['child' => $childName]));

        if ($inner instanceof MailMessage) {
            foreach ($inner->introLines as $line) {
                $message->line($line);
            }
            // Deliberately no action button: the original link may be a signed or tokenised URL meant for the child.
            $message->line(__('notifications.guardian_copy_open_app'));
        }

        return $message;
    }
}
