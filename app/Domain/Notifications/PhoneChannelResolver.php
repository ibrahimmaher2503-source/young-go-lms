<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use Illuminate\Support\Facades\Config;

/** Picks the driver for a phone channel from config. Anything not explicitly and safely configured resolves to the null driver. */
final class PhoneChannelResolver
{
    public function for(string $channel): NotificationChannel
    {
        $name = Config::array('live.phone_reminders')[$channel] ?? null;
        if (! is_string($name) || $name === '') {
            return app(NullNotificationChannel::class);
        }

        if ($name === 'fake') {
            return app()->environment('local', 'testing') ? app(FakeNotificationChannel::class) : app(NullNotificationChannel::class);
        }

        $class = Config::array('phone_channels.providers')[$name] ?? null;
        if (is_string($class) && is_subclass_of($class, NotificationChannel::class)) {
            return app($class);
        }

        return app(NullNotificationChannel::class);
    }
}
