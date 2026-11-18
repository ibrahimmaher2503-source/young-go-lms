<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use Illuminate\Container\Attributes\Singleton;

#[Singleton]
final class FakeNotificationChannel implements NotificationChannel
{
    /** @var array<string, array{channel: string, phone: string, locale: string, template: string, message: string}> */
    public array $messages = [];

    public function send(string $channel, string $phone, string $locale, string $template, string $message, string $idempotencyKey): void
    {
        $this->messages[$idempotencyKey] ??= [
            'channel' => $channel, 'phone' => $phone, 'locale' => $locale, 'template' => $template, 'message' => $message,
        ];
    }
}
