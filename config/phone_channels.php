<?php

declare(strict_types=1);

return [
    // Provider registry for WhatsApp/SMS: driver name => class implementing App\Domain\Notifications\NotificationChannel.
    // Empty until a provider is chosen (O-021). A channel selects its driver in config/live.php ('phone_reminders');
    // 'fake' is built in for local/testing, anything unknown or unregistered resolves to the refusing null driver.
    'providers' => [],

    // No reminders are sent to a phone inside this window, evaluated in the recipient's own timezone. Working default.
    'quiet_hours' => ['start' => '21:00', 'end' => '08:00'],
];
