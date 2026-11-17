<?php

declare(strict_types=1);

namespace App\Domain\AI\Quota;

use App\Domain\AI\Assistant\AssistantRefused;
use App\Domain\AI\UnavailableReason;
use App\Domain\Settings\AiSettings;
use App\Models\AiQuotaCounter;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Per-student daily quotas (AI spec 10.1, ED-010): reserve one unit atomically before the provider call, commit it on a delivered
 * answer, refund it on provider error, timeout or guard rejection. These are the counters AssistantEligibility reads.
 * Cache hits and blocked inputs never reserve. The day is the application day; it resets at the next local midnight.
 */
final class QuotaLedger
{
    public const string STUDENT_DAY = 'student_day';

    public function __construct(private readonly AiSettings $settings) {}

    public static function lessonScope(Lesson $lesson): string
    {
        return 'student_lesson_day:'.$lesson->getKey();
    }

    public static function dailyResetAt(): Carbon
    {
        return now()->addDay()->startOfDay();
    }

    /** @throws AssistantRefused when either daily limit is already used up (nothing stays reserved) */
    public function reserve(User $learner, Lesson $lesson): QuotaReservation
    {
        $period = now()->toDateString();
        $limits = [
            self::STUDENT_DAY => $this->settings->questions_per_student_day,
            self::lessonScope($lesson) => $this->settings->questions_per_student_lesson_day,
        ];

        DB::transaction(function () use ($learner, $limits, $period): void {
            foreach ($limits as $scope => $limit) {
                $keys = ['student_id' => $learner->getKey(), 'scope' => $scope, 'period' => $period];
                AiQuotaCounter::query()->createOrFirst($keys, ['count' => 0]);

                // One conditional UPDATE: two parallel requests can never both take the last unit.
                $taken = AiQuotaCounter::query()->where($keys)->where('count', '<', $limit)->increment('count');
                if ($taken === 0) {
                    throw AssistantRefused::unavailable(UnavailableReason::QuotaExhausted);
                }
            }
        });

        return new QuotaReservation($this, (int) $learner->getKey(), array_keys($limits), $period);
    }

    public function release(QuotaReservation $reservation): void
    {
        AiQuotaCounter::query()->where('student_id', $reservation->studentId)->where('period', $reservation->period)
            ->whereIn('scope', $reservation->scopes)->where('count', '>', 0)->decrement('count');
    }
}
