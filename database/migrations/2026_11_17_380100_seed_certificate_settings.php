<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** T-P6-06: issuer wording is an OPEN owner decision (O-019); null prints a neutral placeholder line from the language files. */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('certificates.issuer_text_ar', null);
        $this->migrator->add('certificates.issuer_text_en', null);
    }

    public function down(): void
    {
        $this->migrator->delete('certificates.issuer_text_ar');
        $this->migrator->delete('certificates.issuer_text_en');
    }
};
