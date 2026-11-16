<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use Spatie\LaravelSettings\Settings;

final class NotificationsSettings extends Settings
{
    public ?string $whatsapp_provider;

    public static function group(): string
    {
        return 'notifications';
    }
}
