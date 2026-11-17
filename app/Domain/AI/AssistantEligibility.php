<?php

declare(strict_types=1);

namespace App\Domain\AI;

use App\Domain\Entitlements\Contracts\LessonAccessChecker;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Settings\AiSettings;
use App\Domain\Settings\PlatformFlag;
use App\Models\AiQuotaCounter;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\User;
use Laravel\Pennant\Feature;

/**
 * AI spec section 2: every condition must hold, checked cheapest-first, and anything unknown fails closed.
 * The order of the reasons is the order of the checks.
 */
final class AssistantEligibility
{
    public function __construct(private readonly LessonAccessChecker $access, private readonly AiSettings $settings) {}

    public function check(User $student, Lesson $lesson): AssistantAvailability
    {
        if (! Feature::for(null)->active(PlatformFlag::AiAssistant->value)) {
            return AssistantAvailability::unavailable(UnavailableReason::GlobalOff);
        }

        $item = $lesson->item;
        $course = $item?->course;
        if ($item === null || $course === null) {
            return AssistantAvailability::unavailable(UnavailableReason::NotRecordedLesson);
        }

        if (! $course->ai_enabled) {
            return AssistantAvailability::unavailable(UnavailableReason::CourseOff);
        }

        if (! $item->ai_enabled) {
            return AssistantAvailability::unavailable(UnavailableReason::ItemOff);
        }

        if (! $this->isRecorded($item, $lesson)) {
            return AssistantAvailability::unavailable(UnavailableReason::NotRecordedLesson);
        }

        if (! $this->access->canAccessLessonFiles($student, $lesson)) {
            return AssistantAvailability::unavailable(UnavailableReason::NoEntitlement);
        }

        if ($lesson->activeCurriculumDocument() === null) {
            return AssistantAvailability::unavailable(UnavailableReason::NoApprovedDocument);
        }

        if ($this->quotaExhausted($student, $lesson)) {
            return AssistantAvailability::unavailable(UnavailableReason::QuotaExhausted);
        }

        return $this->consent($student);
    }

    private function isRecorded(CurriculumItem $item, Lesson $lesson): bool
    {
        return $item->itemable_type === 'lesson' && in_array($lesson->type, ['video', 'audio'], true);
    }

    private function quotaExhausted(User $student, Lesson $lesson): bool
    {
        $lessonScope = 'student_lesson_day:'.$lesson->getKey();
        $counts = AiQuotaCounter::query()
            ->where('student_id', $student->getKey())
            ->where('period', now()->toDateString())
            ->whereIn('scope', ['student_day', $lessonScope])
            ->pluck('count', 'scope');

        return (int) ($counts['student_day'] ?? 0) >= $this->settings->questions_per_student_day
            || (int) ($counts[$lessonScope] ?? 0) >= $this->settings->questions_per_student_lesson_day;
    }

    /** Adults need nothing more. Everyone else needs a verified guardian who granted AI consent, and the minors flag. */
    private function consent(User $student): AssistantAvailability
    {
        if ($this->isAdult($student)) {
            return AssistantAvailability::available();
        }

        $consented = GuardianChildLink::query()
            ->where('child_id', $student->getKey())
            ->where('status', GuardianChildLink::STATUS_VERIFIED)
            ->whereNotNull('ai_consent_granted_at')
            ->whereNotNull('ai_consent_version')
            ->exists();

        if (! $consented) {
            return AssistantAvailability::unavailable(UnavailableReason::ConsentRequired);
        }

        if (! Feature::for(null)->active(PlatformFlag::AiMinors->value)) {
            return AssistantAvailability::unavailable(UnavailableReason::MinorsOff);
        }

        return AssistantAvailability::available();
    }

    /** O-015: with no configured adult age, or no known birth month, nobody counts as an adult. */
    private function isAdult(User $student): bool
    {
        $adultAge = config('ai.adult_age');
        $year = $student->getAttribute('birth_year');
        $month = $student->getAttribute('birth_month');
        if (! is_numeric($adultAge) || $year === null || $month === null) {
            return false;
        }

        $now = now();
        $age = $now->year - (int) $year - ($now->month < (int) $month ? 1 : 0);

        return $age >= (int) $adultAge;
    }
}
