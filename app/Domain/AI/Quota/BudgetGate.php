<?php

declare(strict_types=1);

namespace App\Domain\AI\Quota;

use App\Domain\AI\Assistant\AssistantRefused;
use App\Domain\Audit\AuditLogger;
use App\Domain\Settings\AiSettings;
use App\Models\AiUsage;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Monthly cost ceilings (AI spec 10.1): per course and for the platform, from ai_usage.cost_minor. At the alert percent
 * (default 80) one audit alert per scope and month is recorded; at 100 percent the assistant pauses gracefully (a refusal
 * before any provider call, lessons unaffected). A ceiling of null means no ceiling. Budget values are OPEN.
 */
final class BudgetGate
{
    public function __construct(private readonly AiSettings $settings, private readonly AuditLogger $audit) {}

    public static function monthlyResetAt(): Carbon
    {
        return now()->startOfMonth()->addMonth();
    }

    /** @throws AssistantRefused */
    public function assertOpen(Lesson $lesson): void
    {
        foreach ($this->scopes($lesson) as [, $ceiling, $spent]) {
            if ($ceiling !== null && $spent >= $ceiling) {
                throw AssistantRefused::budgetExhausted(self::monthlyResetAt());
            }
        }
    }

    /** Called after a request was recorded: raises the 80 percent alert once per scope and month. */
    public function alertIfNeeded(Lesson $lesson): void
    {
        $percent = max(1, $this->settings->budget_alert_percent);
        foreach ($this->scopes($lesson) as [$scope, $ceiling, $spent]) {
            if ($ceiling === null || $ceiling <= 0 || $spent * 100 < $ceiling * $percent) {
                continue;
            }

            if (! Cache::add('ai:budget-alert:'.$scope.':'.now()->format('Y-m'), true, self::monthlyResetAt())) {
                continue;
            }

            $this->audit->record('ai.budget_alert', $scope === 'global' ? $lesson : $lesson->item?->course, null, null, [
                'scope' => $scope, 'spent_minor' => $spent, 'ceiling_minor' => $ceiling, 'alert_percent' => $percent,
                'currency' => $this->settings->budget_currency,
            ]);
        }
    }

    /** @return list<array{0: string, 1: int|null, 2: int}> scope, ceiling, spent this month */
    private function scopes(Lesson $lesson): array
    {
        $month = now()->startOfMonth();
        $scopes = [];

        $global = $this->settings->monthly_budget_minor;
        if ($global !== null) {
            $scopes[] = ['global', $global, (int) AiUsage::query()->where('created_at', '>=', $month)->sum('cost_minor')];
        }

        $courseCeiling = $this->settings->course_monthly_budget_minor;
        $courseId = $lesson->item?->course_id;
        if ($courseCeiling !== null && $courseId !== null) {
            $lessons = CurriculumItem::query()->where('course_id', $courseId)->where('itemable_type', 'lesson')->select('itemable_id');
            $scopes[] = ['course:'.$courseId, $courseCeiling, (int) AiUsage::query()->where('created_at', '>=', $month)->whereIn('lesson_id', $lessons)->sum('cost_minor')];
        }

        return $scopes;
    }
}
