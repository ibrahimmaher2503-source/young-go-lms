<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

/** Used wherever no real or fake driver is configured: it refuses to deliver and keeps nothing, so no phone or text is retained. */
final class NullNotificationChannel implements NotificationChannel
{
    public function send(string $channel, string $phone, string $locale, string $template, string $message, string $idempotencyKey): void {}
}
