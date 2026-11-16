<?php

declare(strict_types=1);

use Spatie\LaravelSettings\Migrations\SettingsMigration;

return new class extends SettingsMigration
{
    public function up(): void
    {
        $this->migrator->add('general.default_locale', 'ar');
        $this->migrator->add('markets.default_market', 'EG');
        $this->migrator->add('learning.required_lesson_percent', null);
        $this->migrator->add('notifications.whatsapp_provider', null);
        $this->migrator->add('ai.questions_per_student_day', 20);
        $this->migrator->add('ai.questions_per_student_lesson_day', 10);
        $this->migrator->add('ai.course_monthly_budget_minor', null);
        $this->migrator->add('ai.monthly_budget_minor', null);
        $this->migrator->add('ai.budget_currency', null);
        $this->migrator->add('ai.budget_alert_percent', 80);
        $this->migrator->add('ai.grounded_answer_min_percent', 95);
        $this->migrator->add('ai.scope_classification_min_percent', 95);
        $this->migrator->add('ai.length_compliance_min_percent', 100);
        $this->migrator->add('ai.red_team_pass_min_percent', 100);
        $this->migrator->add('ai.arabic_quality_min', 4);
        $this->migrator->add('ai.first_token_p95_max_ms', 2500);
    }

    public function down(): void
    {
        $this->migrator->delete('general.default_locale');
        $this->migrator->delete('markets.default_market');
        $this->migrator->delete('learning.required_lesson_percent');
        $this->migrator->delete('notifications.whatsapp_provider');
        $this->migrator->delete('ai.questions_per_student_day');
        $this->migrator->delete('ai.questions_per_student_lesson_day');
        $this->migrator->delete('ai.course_monthly_budget_minor');
        $this->migrator->delete('ai.monthly_budget_minor');
        $this->migrator->delete('ai.budget_currency');
        $this->migrator->delete('ai.budget_alert_percent');
        $this->migrator->delete('ai.grounded_answer_min_percent');
        $this->migrator->delete('ai.scope_classification_min_percent');
        $this->migrator->delete('ai.length_compliance_min_percent');
        $this->migrator->delete('ai.red_team_pass_min_percent');
        $this->migrator->delete('ai.arabic_quality_min');
        $this->migrator->delete('ai.first_token_p95_max_ms');
    }
};
