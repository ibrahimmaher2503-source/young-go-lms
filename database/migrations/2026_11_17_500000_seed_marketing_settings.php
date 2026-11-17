<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** T-P9-03: marketing tag IDs are configured by an administrator; nothing is enabled by default (R-021). */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('marketing.ga4_measurement_id', null);
        $this->migrator->add('marketing.meta_pixel_id', null);
    }

    public function down(): void
    {
        $this->migrator->delete('marketing.ga4_measurement_id');
        $this->migrator->delete('marketing.meta_pixel_id');
    }
};
