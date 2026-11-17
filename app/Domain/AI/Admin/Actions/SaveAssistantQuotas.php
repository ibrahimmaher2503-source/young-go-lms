<?php

declare(strict_types=1);

namespace App\Domain\AI\Admin\Actions;

use App\Domain\AI\Admin\AssistantAdminAccess;
use App\Domain\Audit\AuditLogger;
use App\Domain\Settings\AiSettings;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use RuntimeException;

/**
 * Daily question quotas, monthly budget ceilings and the alert percent (AI spec 10.1, 14). Values are OPEN (O-AI-03), so
 * everything stays configurable here; a ceiling left empty means no ceiling. Amounts are in minor units of the chosen currency.
 */
final class SaveAssistantQuotas
{
    public function __construct(private readonly AiSettings $settings, private readonly AuditLogger $audit) {}

    /** @param  array<string, mixed>  $data */
    public function __invoke(User $actor, array $data): void
    {
        if (! AssistantAdminAccess::canConfigure($actor)) {
            throw new AuthorizationException;
        }

        $clean = Validator::make($data, [
            'questions_per_student_day' => ['required', 'integer', 'min:1', 'max:1000'],
            'questions_per_student_lesson_day' => ['required', 'integer', 'min:1', 'max:1000', 'lte:questions_per_student_day'],
            'monthly_budget_minor' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'course_monthly_budget_minor' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'budget_currency' => ['nullable', 'string', 'size:3', 'required_with:monthly_budget_minor,course_monthly_budget_minor'],
            'budget_alert_percent' => ['required', 'integer', 'min:1', 'max:100'],
        ], [], (array) __('ai_admin.fields'))->validate();

        $keys = array_keys($clean);
        $before = $this->snapshot($keys);

        DB::transaction(function () use ($actor, $clean, $keys, $before): void {
            $this->settings->questions_per_student_day = (int) $clean['questions_per_student_day'];
            $this->settings->questions_per_student_lesson_day = (int) $clean['questions_per_student_lesson_day'];
            $this->settings->monthly_budget_minor = filled($clean['monthly_budget_minor'] ?? null) ? (int) $clean['monthly_budget_minor'] : null;
            $this->settings->course_monthly_budget_minor = filled($clean['course_monthly_budget_minor'] ?? null) ? (int) $clean['course_monthly_budget_minor'] : null;
            $this->settings->budget_currency = filled($clean['budget_currency'] ?? null) ? mb_strtoupper((string) $clean['budget_currency']) : null;
            $this->settings->budget_alert_percent = (int) $clean['budget_alert_percent'];
            $this->settings->save();

            if ($this->audit->record('ai.quotas_saved', $actor, (int) $actor->getKey(), $before, $this->snapshot($keys)) === null) {
                throw new RuntimeException('Unable to record assistant audit.');
            }
        });
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function snapshot(array $keys): array
    {
        $values = [];
        foreach ($keys as $key) {
            $values[$key] = $this->settings->{$key};
        }

        return $values;
    }
}
