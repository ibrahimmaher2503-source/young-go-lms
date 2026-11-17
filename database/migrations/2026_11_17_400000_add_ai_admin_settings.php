<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

/**
 * T-P7-06: operator controls of the assistant. A null provider means "follow the environment" (AI_PROVIDER); the circuit breaker
 * and retention defaults are the AI spec 11 and 18 recommendations. Nothing here enables the assistant or picks a real provider (O-014).
 */
return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('ai.provider', null);
        $this->migrator->add('ai.model', null);
        $this->migrator->add('ai.fallback_provider', null);
        $this->migrator->add('ai.breaker_failures', 5);
        $this->migrator->add('ai.breaker_window_seconds', 120);
        $this->migrator->add('ai.breaker_cool_off_seconds', 60);
        $this->migrator->add('ai.message_retention_days', 180);
        $this->migrator->add('ai.usage_retention_months', 24);
    }

    public function down(): void
    {
        foreach (['provider', 'model', 'fallback_provider', 'breaker_failures', 'breaker_window_seconds', 'breaker_cool_off_seconds', 'message_retention_days', 'usage_retention_months'] as $name) {
            $this->migrator->delete('ai.'.$name);
        }
    }
};
