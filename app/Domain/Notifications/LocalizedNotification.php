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

    public function phoneTemplate(): ?string
    {
        return null;
    }

    /** T-P9-05: the NotificationMatrix event key. Null (the default) means "not opt-outable": always sent. */
    public function event(): ?string
    {
        return null;
    }

    abstract protected function title(User $recipient): string;

    abstract protected function body(User $recipient): string;

    protected function url(User $recipient): ?string
    {
        return null;
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User && filled($notifiable->getAttribute('email')) && NotificationPreferences::emailAllowed($notifiable, $this->event()) ? ['database', 'mail'] : ['database'];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        // A mail retry must keep the inbox copy already delivered under the dispatcher's stable ID.
        return $channel !== 'database' || ! $notifiable instanceof User || ! $notifiable->notifications()->whereKey($this->id)->exists();
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
