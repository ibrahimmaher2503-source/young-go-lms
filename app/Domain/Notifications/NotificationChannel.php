<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

interface NotificationChannel
{
    /** A driver must deliver at most once per idempotency key, including after retries. */
    public function send(string $channel, string $phone, string $locale, string $template, string $message, string $idempotencyKey): void;
}
