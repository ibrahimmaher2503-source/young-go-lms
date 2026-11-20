<?php

declare(strict_types=1);

namespace App\Domain\AI\Quota;

use App\Domain\AI\Assistant\AssistantRefused;
use App\Domain\Audit\AuditLogger;
use App\Domain\Settings\AiSettings;
use App\Models\AiBudgetLedger;
use App\Models\AiBudgetReservation;
use App\Models\AiUsage;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Monthly cost ceilings (AI spec 10.1): per course and for the platform, from ai_usage.cost_minor plus active in-flight
 * reservations. At the alert percent (default 80) one audit alert per scope and month is recorded; at 100 percent the
 * assistant pauses gracefully (a refusal before any provider call, lessons unaffected). A ceiling of null means no ceiling.
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
        $now = now();
        $month = $now->copy()->startOfMonth();
        $period = $now->format('Y-m');

        foreach ($this->targets($lesson) as [$scope, $ceiling]) {
            $spent = $this->spent($scope, $lesson, $month) + $this->activeReserved($scope, $period, $now);
            if ($spent >= $ceiling) {
                throw AssistantRefused::budgetExhausted(self::monthlyResetAt());
            }
        }
    }

    /**
     * Atomically verify configured monthly ceilings and place short-lived reservations so concurrent requests cannot
     * overshoot the remaining budget before usage rows are committed.
     *
     * @throws AssistantRefused
     */
    public function reserve(Lesson $lesson, ?int $estimatedCostMinor = null): BudgetReservation
    {
        $targets = $this->targets($lesson);
        if ($targets === []) {
            return new BudgetReservation($this, []);
        }

        $now = now();
        $month = $now->copy()->startOfMonth();
        $period = $now->format('Y-m');
        $ttlSeconds = max(10, (int) config('ai.budget.reservation_ttl_seconds', 60));

        /** @var list<int> $ids */
        $ids = DB::transaction(function () use ($targets, $lesson, $now, $month, $period, $ttlSeconds, $estimatedCostMinor): array {
            $reservedIds = [];

            foreach ($targets as [$scope, $ceiling]) {
                try {
                    AiBudgetLedger::query()->createOrFirst(['scope' => $scope, 'period' => $period]);
                } catch (UniqueConstraintViolationException) {
                    // A concurrent caller created the monthly sentinel row first.
                }

                AiBudgetLedger::query()->where('scope', $scope)->where('period', $period)->lockForUpdate()->first();

                AiBudgetReservation::query()
                    ->where('scope', $scope)
                    ->where('period', $period)
                    ->where('expires_at', '<=', $now)
                    ->delete();

                $used = $this->spent($scope, $lesson, $month) + $this->activeReserved($scope, $period, $now);
                if ($used >= $ceiling) {
                    throw AssistantRefused::budgetExhausted(self::monthlyResetAt());
                }

                $reservation = AiBudgetReservation::query()->create([
                    'scope' => $scope,
                    'period' => $period,
                    'amount_minor' => $this->reservationMinor($scope, $lesson, $month, $estimatedCostMinor),
                    'expires_at' => $now->copy()->addSeconds($ttlSeconds),
                ]);
                $reservedIds[] = (int) $reservation->getKey();
            }

            return $reservedIds;
        });

        return new BudgetReservation($this, $ids);
    }

    public function release(BudgetReservation $reservation): void
    {
        if ($reservation->ids !== []) {
            AiBudgetReservation::query()->whereIn('id', $reservation->ids)->delete();
        }
    }

    /** Called after a request was recorded: raises the 80 percent alert once per scope and month. */
    public function alertIfNeeded(Lesson $lesson): void
    {
        $percent = max(1, $this->settings->budget_alert_percent);
        $month = now()->startOfMonth();

        foreach ($this->targets($lesson) as [$scope, $ceiling]) {
            $spent = $this->spent($scope, $lesson, $month);
            if ($ceiling <= 0 || $spent * 100 < $ceiling * $percent) {
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

    /**
     * Configured scopes in deterministic order (`global` first, then `course:<id>`) to avoid lock-order deadlocks.
     *
     * @return list<array{0: string, 1: int}>
     */
    private function targets(Lesson $lesson): array
    {
        $targets = [];

        $global = $this->settings->monthly_budget_minor;
        if ($global !== null) {
            $targets[] = ['global', $global];
        }

        $courseCeiling = $this->settings->course_monthly_budget_minor;
        $courseId = $lesson->item?->course_id;
        if ($courseCeiling !== null && $courseId !== null) {
            $targets[] = ['course:'.$courseId, $courseCeiling];
        }

        return $targets;
    }

    /** @return Builder<AiUsage> */
    private function usageQuery(string $scope, Lesson $lesson, CarbonInterface $month): Builder
    {
        $query = AiUsage::query()->where('created_at', '>=', $month);
        if ($scope === 'global') {
            return $query;
        }

        $courseId = $lesson->item?->course_id;
        $lessons = CurriculumItem::query()
            ->where('course_id', $courseId)
            ->where('itemable_type', 'lesson')
            ->select('itemable_id');

        return $query->whereIn('lesson_id', $lessons);
    }

    private function spent(string $scope, Lesson $lesson, CarbonInterface $month): int
    {
        return (int) $this->usageQuery($scope, $lesson, $month)->sum('cost_minor');
    }

    private function activeReserved(string $scope, string $period, CarbonInterface $now): int
    {
        return (int) AiBudgetReservation::query()
            ->where('scope', $scope)
            ->where('period', $period)
            ->where('expires_at', '>', $now)
            ->sum('amount_minor');
    }

    private function reservationMinor(string $scope, Lesson $lesson, CarbonInterface $month, ?int $estimatedCostMinor): int
    {
        if ($estimatedCostMinor !== null && $estimatedCostMinor > 0) {
            return $estimatedCostMinor;
        }

        $configured = config('ai.budget.reservation_minor');
        if (is_numeric($configured) && (int) $configured > 0) {
            return (int) $configured;
        }

        $observedMax = (int) ($this->usageQuery($scope, $lesson, $month)->max('cost_minor') ?? 0);
        if ($observedMax > 0) {
            return $observedMax;
        }

        return max(1, (int) config('ai.budget.default_reservation_minor', 50));
    }
}
