<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use Spatie\LaravelSettings\Settings;

final class LearningSettings extends Settings
{
    public ?int $required_lesson_percent;

    public static function group(): string
    {
        return 'learning';
    }
}
