<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base for every product notification. Text is built lazily from language files, so the sender renders it in the
 * recipient's own locale (the User is HasLocalePreference). Each notification names a dedupe key; NotificationDispatcher
 * sends one key to one recipient once. Channels: the app inbox always, email when the recipient has an address.
 */
abstract class LocalizedNotification extends Notification
{
    /** Same key and same recipient means the same message: a retry or a second scheduler run sends nothing more. */
    abstract public function dedupeKey(): string;

    abstract protected function title(User $recipient): string;

    abstract protected function body(User $recipient): string;

    protected function url(User $recipient): ?string
    {
        return null;
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User && filled($notifiable->getAttribute('email')) ? ['database', 'mail'] : ['database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        assert($notifiable instanceof User);
        $message = (new MailMessage)->subject($this->title($notifiable))->line($this->body($notifiable));
        $url = $this->url($notifiable);
        if ($url !== null) {
            $message->action(__('notifications.open'), $url);
        }

        return $message;
    }

    /** @return array<string, string|null> */
    public function toArray(object $notifiable): array
    {
        assert($notifiable instanceof User);

        return ['title' => $this->title($notifiable), 'message' => $this->body($notifiable), 'url' => $this->url($notifiable), 'type' => class_basename($this)];
    }
}
