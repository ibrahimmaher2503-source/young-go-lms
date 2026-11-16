<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use Spatie\LaravelSettings\Settings;

final class GeneralSettings extends Settings
{
    public string $default_locale;

    public static function group(): string
    {
        return 'general';
    }
}
