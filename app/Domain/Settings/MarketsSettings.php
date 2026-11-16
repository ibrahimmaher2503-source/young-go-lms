<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use Spatie\LaravelSettings\Settings;

final class MarketsSettings extends Settings
{
    public string $default_market;

    public static function group(): string
    {
        return 'markets';
    }
}
