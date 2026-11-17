<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use Spatie\LaravelSettings\Settings;

/** Marketing tag IDs (YG-GAP-MKT-001). Null = tag off. Loaded only after cookie consent and never on learning/child/AI screens (R-021). */
final class MarketingSettings extends Settings
{
    public ?string $ga4_measurement_id;

    public ?string $meta_pixel_id;

    public static function group(): string
    {
        return 'marketing';
    }
}
