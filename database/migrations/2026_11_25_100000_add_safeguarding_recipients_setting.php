<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/** O-028: optional extra safeguarding recipients (comma-separated user ids or emails) on top of the configured roles. Empty by default. */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('ai.safeguarding_extra_recipients', null);
    }

    public function down(): void
    {
        $this->migrator->delete('ai.safeguarding_extra_recipients');
    }
};
