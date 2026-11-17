<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use Spatie\LaravelSettings\Settings;

final class AiSettings extends Settings
{
    public int $questions_per_student_day;

    public int $questions_per_student_lesson_day;

    public ?int $course_monthly_budget_minor;

    public ?int $monthly_budget_minor;

    public ?string $budget_currency;

    public int $budget_alert_percent;

    public int $grounded_answer_min_percent;

    public int $scope_classification_min_percent;

    public int $length_compliance_min_percent;

    public int $red_team_pass_min_percent;

    public int $arabic_quality_min;

    public int $first_token_p95_max_ms;

    /** Provider key chosen by a super admin; null follows the environment (AI_PROVIDER). Provider choice stays OPEN (O-014). */
    public ?string $provider;

    public ?string $model;

    public ?string $fallback_provider;

    public int $breaker_failures;

    public int $breaker_window_seconds;

    public int $breaker_cool_off_seconds;

    public int $message_retention_days;

    public int $usage_retention_months;

    public static function group(): string
    {
        return 'ai';
    }
}
