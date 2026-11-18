<?php

declare(strict_types=1);

namespace App\Domain\Completion;

use App\Domain\Curriculum\Queries\CurriculumOutline;
use App\Domain\Progress\Queries\CourseProgressSnapshot;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\LiveAttendance;
use App\Models\LiveSession;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;

/**
 * Measures one learner against the rules of one course, without writing completion. Each criterion reports what the
 * learner has against what is needed. A criterion with nothing to measure yet (no required quiz, no finished session)
 * is not met, so an empty course never completes by accident; staff can still approve by hand.
 */
final class CompletionReport
{
    public const string LESSONS = 'lessons';

    public const string QUIZZES = 'quizzes';

    public const string ASSIGNMENTS = 'assignments';

    public const string ATTENDANCE = 'attendance';

    /**
     * @param  array<string, array{met: bool, have: int, need: int}>  $criteria  only the rules that are on
     */
    public function __construct(public readonly CourseCompletionRule $rules, public readonly array $criteria) {}

    public static function measure(User $learner, Course $course, ?CourseCompletionRule $rules = null): self
    {
        $rules ??= CourseCompletionRule::forCourse($course);
        $criteria = [];

        if ($rules->lesson_percent !== null) {
            ['done' => $done, 'total' => $total] = CourseProgressSnapshot::counts($learner, [$course->id])
                ->get($course->id, ['done' => 0, 'total' => 0]);
            $criteria[self::LESSONS] = ['met' => $total > 0 && $done * 100 >= $rules->lesson_percent * $total, 'have' => $total === 0 ? 0 : intdiv($done * 100, $total), 'need' => $rules->lesson_percent];
        }
        if ($rules->require_quizzes) {
            $criteria[self::QUIZZES] = self::quizzes($learner, $course, $rules);
        }
        if ($rules->require_assignments) {
            $criteria[self::ASSIGNMENTS] = self::assignments($learner, $course);
        }
        if ($rules->attendance_percent !== null) {
            $criteria[self::ATTENDANCE] = self::attendance($learner, $course, $rules->attendance_percent);
        }

        return new self($rules, $criteria);
    }

    /** Every rule that is on is met, and at least one is on. */
    public function eligible(): bool
    {
        return $this->criteria !== [] && ! in_array(false, array_column($this->criteria, 'met'), true);
    }

    /** @return array<string, bool> */
    public function met(): array
    {
        return array_map(static fn (array $criterion): bool => $criterion['met'], $this->criteria);
    }

    /** @return array{met: bool, have: int, need: int} */
    private static function quizzes(User $learner, Course $course, CourseCompletionRule $rules): array
    {
        $required = self::requiredItems($course, Quiz::class);
        $passed = 0;
        foreach ($required as $quizId) {
            $attempts = QuizAttempt::query()->where('quiz_id', $quizId)->where('learner_id', $learner->getKey())->where('status', QuizAttempt::SUBMITTED)->get();
            // Passed, or (a quiz without a pass mark) fully reviewed.
            $ok = $attempts->contains(static fn (QuizAttempt $attempt): bool => $attempt->passed === true
                || ($attempt->passed === null && ! $attempt->needs_review && ($attempt->settings['pass_mark_percent'] ?? null) === null));
            $passed += $ok ? 1 : 0;
        }

        // O-020: the unsaved default rules ask for required quizzes only when the course has some; a saved rule stays strict.
        $nothingToPass = $required === [] && ! $rules->exists;

        return ['met' => $nothingToPass || ($required !== [] && $passed === count($required)), 'have' => $passed, 'need' => count($required)];
    }

    /** @return array{met: bool, have: int, need: int} */
    private static function assignments(User $learner, Course $course): array
    {
        $required = self::requiredItems($course, Assignment::class);
        $accepted = $required === [] ? 0 : AssignmentSubmission::query()->where('learner_id', $learner->getKey())->whereIn('assignment_id', $required)->where('status', AssignmentSubmission::ACCEPTED)->distinct()->count('assignment_id');

        return ['met' => $required !== [] && $accepted === count($required), 'have' => $accepted, 'need' => count($required)];
    }

    /**
     * Present and late count as attended; excused sessions leave the measure; an unmarked finished session counts as missed.
     *
     * @return array{met: bool, have: int, need: int}
     */
    private static function attendance(User $learner, Course $course, int $threshold): array
    {
        $sessions = LiveSession::query()->where('course_id', $course->getKey())->where('status', LiveSession::COMPLETED)->whereNull('archived_at')->with('cohort')->get()
            ->filter(static fn (LiveSession $session): bool => $session->cohort_id === null || ($session->cohort?->hasSeat($learner) ?? false));
        $marks = LiveAttendance::query()->where('learner_id', $learner->getKey())->whereIn('live_session_id', $sessions->modelKeys())->pluck('status', 'live_session_id');

        $excused = $sessions->filter(static fn (LiveSession $session): bool => $marks->get($session->getKey()) === LiveAttendance::EXCUSED)->count();
        $attended = $sessions->filter(static fn (LiveSession $session): bool => in_array($marks->get($session->getKey()), [LiveAttendance::PRESENT, LiveAttendance::LATE], true))->count();
        $counted = $sessions->count() - $excused;

        return ['met' => $counted > 0 && $attended * 100 >= $threshold * $counted, 'have' => $counted === 0 ? 0 : intdiv($attended * 100, $counted), 'need' => $threshold];
    }

    /**
     * Ids of the required, published, not archived items of one type in the course.
     *
     * @param  class-string  $type
     * @return list<int>
     */
    private static function requiredItems(Course $course, string $type): array
    {
        return CurriculumOutline::forStudents($course)->flatMap(fn ($section) => $section->items)
            ->filter(static fn (CurriculumItem $item): bool => $item->required && $item->itemable instanceof $type)
            ->map(static fn (CurriculumItem $item): int => (int) $item->itemable_id)->values()->all();
    }
}
