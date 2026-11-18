<?php

declare(strict_types=1);

use App\Domain\Assessment\Actions\ConfigureQuiz;
use App\Domain\Assessment\Actions\ManageQuestions;
use App\Domain\Assessment\Actions\StartQuizAttempt;
use App\Domain\Assessment\Actions\SubmitQuizAttempt;
use App\Domain\Assignments\Actions\ConfigureAssignment;
use App\Domain\Assignments\Actions\DecideSubmission;
use App\Domain\Assignments\Actions\SaveSubmissionDraft;
use App\Domain\Assignments\Actions\SubmitAssignment;
use App\Domain\Completion\Actions\ApproveCourseCompletion;
use App\Domain\Completion\Actions\CourseCompletionEvaluator;
use App\Domain\Completion\Actions\SaveCompletionRules;
use App\Domain\Completion\CompletionReport;
use App\Domain\Completion\CourseCompletion;
use App\Domain\Completion\CourseCompletionRule;
use App\Domain\Completion\Events\CourseCompleted;
use App\Domain\Curriculum\Actions\ManageAssessmentItems;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Live\Actions\CancelLiveSession;
use App\Domain\Live\Actions\CompleteLiveSession;
use App\Domain\Live\Actions\MarkAttendance;
use App\Domain\Live\Actions\OpenLiveSession;
use App\Domain\Live\Actions\ScheduleLiveSession;
use App\Domain\Progress\Actions\RecordProgress;
use App\Filament\Resources\Courses\Pages\ManageCourseCompletion;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\LiveAttendance;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\SubmissionDecision;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

/** @return array{course: Course, instructor: User, section: CourseSection} */
function cmpCourse(): array
{
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة الإكمال', 'en' => 'Completion Course'],
        'slug_ar' => 'cmp-ar-'.uniqid(),
        'slug_en' => 'cmp-en-'.uniqid(),
        'status' => 'published',
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['ar' => 'القسم 1', 'en' => 'Section 1']]);
    $section->update(['published' => true]);

    return compact('course', 'instructor', 'section');
}

it('measures lessons, quizzes, accepted assignments, and live attendance criteria accurately', function (): void {
    ['course' => $course, 'instructor' => $instructor, 'section' => $section] = cmpCourse();
    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);

    // Default empty course does not accidentally complete
    expect(CompletionReport::measure($learner, $course)->eligible())->toBeFalse();

    $lessonItem = app(ManageLessons::class)->add($instructor, $section, ['type' => 'text', 'title' => ['ar' => 'درس', 'en' => 'Lesson 1']]);
    $optionalItem = app(ManageLessons::class)->add($instructor, $section, ['type' => 'text', 'title' => ['ar' => 'إضافي', 'en' => 'Optional']]);
    $optionalItem->update(['required' => false]);

    $quizItem = app(ManageAssessmentItems::class)->add($instructor, $section, 'quiz', ['title' => ['ar' => 'اختبار', 'en' => 'Quiz']]);
    /** @var Quiz $quiz */
    $quiz = $quizItem->itemable;
    app(ConfigureQuiz::class)($instructor, $quiz, ['pass_mark_percent' => 80, 'max_attempts' => 3, 'result_visibility' => 'full', 'progression' => 'after_submission']);
    $tf = app(ManageQuestions::class)->add($instructor, $quiz, ['type' => Question::TRUE_FALSE, 'prompt' => ['en' => 'Q'], 'points' => 10, 'true_is_correct' => true]);

    $assignItem = app(ManageAssessmentItems::class)->add($instructor, $section, 'assignment', ['title' => ['ar' => 'واجب', 'en' => 'Task']]);
    /** @var Assignment $assignment */
    $assignment = $assignItem->itemable;
    app(ConfigureAssignment::class)($instructor, $assignment, ['allowed_types' => ['text'], 'late_policy' => Assignment::LATE_NOT_ALLOWED]);

    app(SetCurriculumStatus::class)($instructor, $course, [$lessonItem->id, $optionalItem->id, $quizItem->id, $assignItem->id], CurriculumItem::STATUS_PUBLISHED);

    // Live sessions: 2 finished (1 present, 1 excused) + 1 cancelled -> 1/1 counted = 100%
    $s1Item = app(ScheduleLiveSession::class)($instructor, $section, [
        'title' => ['en' => 'S1'], 'timezone' => 'UTC',
        'starts_at' => now()->addHour()->format('Y-m-d H:i:s'), 'ends_at' => now()->addHours(2)->format('Y-m-d H:i:s'),
        'provider' => 'manual_zoom', 'meeting_url' => 'https://zoom.us/j/111',
    ]);
    $s2Item = app(ScheduleLiveSession::class)($instructor, $section, [
        'title' => ['en' => 'S2'], 'timezone' => 'UTC',
        'starts_at' => now()->addHours(3)->format('Y-m-d H:i:s'), 'ends_at' => now()->addHours(4)->format('Y-m-d H:i:s'),
        'provider' => 'manual_zoom', 'meeting_url' => 'https://zoom.us/j/222',
    ]);
    $s3Item = app(ScheduleLiveSession::class)($instructor, $section, [
        'title' => ['en' => 'S3'], 'timezone' => 'UTC',
        'starts_at' => now()->addHours(5)->format('Y-m-d H:i:s'), 'ends_at' => now()->addHours(6)->format('Y-m-d H:i:s'),
        'provider' => 'manual_zoom', 'meeting_url' => 'https://zoom.us/j/333',
    ]);
    foreach ([$s1Item->itemable, $s2Item->itemable] as $ls) {
        $ls->forceFill(['starts_at' => now()->subHours(2), 'ends_at' => now()->subMinute()])->save();
        app(OpenLiveSession::class)($instructor, $ls);
        app(CompleteLiveSession::class)($instructor, $ls->fresh());
    }
    app(CancelLiveSession::class)($instructor, $s3Item->itemable);
    app(MarkAttendance::class)($instructor, $s1Item->itemable->fresh(), $learner, LiveAttendance::PRESENT);
    app(MarkAttendance::class)($instructor, $s2Item->itemable->fresh(), $learner, LiveAttendance::EXCUSED);

    app(SaveCompletionRules::class)($instructor, $course, [
        'lesson_percent' => 100,
        'require_quizzes' => true,
        'require_assignments' => true,
        'attendance_percent' => 80,
        'manual_approval' => false,
    ]);

    // Before completing required work -> not eligible
    $reportBefore = CompletionReport::measure($learner, $course);
    expect($reportBefore->eligible())->toBeFalse()
        ->and($reportBefore->criteria[CompletionReport::ATTENDANCE]['met'])->toBeTrue();

    // Complete required lesson + pass quiz + submit assignment & decide GRADED first (must NOT satisfy ACCEPTED per O-020)
    app(RecordProgress::class)->markComplete($learner, $lessonItem->fresh());
    $attempt = app(StartQuizAttempt::class)($learner, $quizItem->fresh());
    $trueKey = collect($attempt->snapshot['questions'][0]['options'])->firstWhere('is_correct', true)['key'];
    app(SubmitQuizAttempt::class)($learner, $attempt, [$tf->id => ['selected' => [$trueKey]]]);

    app(SaveSubmissionDraft::class)($learner, $assignItem->fresh(), 'Draft answer');
    $submission = app(SubmitAssignment::class)($learner, $assignItem->fresh());
    app(DecideSubmission::class)($instructor, $submission, SubmissionDecision::GRADED, 95, 'Needs explicit accept.');

    $reportGraded = CompletionReport::measure($learner, $course);
    expect($reportGraded->criteria[CompletionReport::LESSONS]['met'])->toBeTrue()
        ->and($reportGraded->criteria[CompletionReport::QUIZZES]['met'])->toBeTrue()
        ->and($reportGraded->criteria[CompletionReport::ASSIGNMENTS]['met'])->toBeFalse()
        ->and($reportGraded->eligible())->toBeFalse();

    // Explicit ACCEPTED triggers evaluation cleanly
    Event::fake([CourseCompleted::class]);
    app(DecideSubmission::class)($instructor, $submission->fresh(), SubmissionDecision::ACCEPTED, null, 'Approved.');

    $completion = CourseCompletion::query()->where('course_id', $course->id)->where('learner_id', $learner->id)->first();
    expect($completion)->not->toBeNull()
        ->and($completion->source)->toBe(CourseCompletion::AUTO);
    Event::assertDispatchedTimes(CourseCompleted::class, 1);

    // Adding a new required lesson afterwards does NOT undo existing completion or dispatch event again
    $extra = app(ManageLessons::class)->add($instructor, $section, ['type' => 'text', 'title' => ['ar' => 'جديد', 'en' => 'New Lesson']]);
    app(SetCurriculumStatus::class)($instructor, $course, [$extra->id], CurriculumItem::STATUS_PUBLISHED);
    $rechecked = app(CourseCompletionEvaluator::class)($learner, $course);
    expect($rechecked?->id)->toBe($completion->id);
    Event::assertDispatchedTimes(CourseCompleted::class, 1);
})->group('T-P6-05', 'A1', 'O-020');

it('supports manual approval, audit rollback safety, and ManageCourseCompletion Livewire actions', function (): void {
    ['course' => $course, 'instructor' => $instructor, 'section' => $section] = cmpCourse();
    ['course' => $otherCourse, 'instructor' => $otherInstructor] = cmpCourse();
    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');
    $stranger = User::factory()->create(['status' => 'active'])->assignRole('student');
    $viewInstructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $course->instructors()->attach($viewInstructor->id, ['role' => 'instructor', 'scope' => 'view']);
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);

    $lessonItem = app(ManageLessons::class)->add($instructor, $section, ['type' => 'text', 'title' => ['en' => 'L1']]);
    app(SetCurriculumStatus::class)($instructor, $course, [$lessonItem->id], CurriculumItem::STATUS_PUBLISHED);
    app(SaveCompletionRules::class)($instructor, $course, ['lesson_percent' => 100, 'manual_approval' => true]);
    app(RecordProgress::class)->markComplete($learner, $lessonItem->fresh());

    // manual_approval=true prevents auto completion even when 100% met
    expect(app(CourseCompletionEvaluator::class)($learner, $course))->toBeNull();

    // Authorization boundaries
    expect(fn () => app(ApproveCourseCompletion::class)($viewInstructor, $course, $learner, 'Valid reason'))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(SaveCompletionRules::class)($otherInstructor, $course, ['lesson_percent' => 50]))
        ->toThrow(AuthorizationException::class);

    $this->actingAs($viewInstructor);
    Filament::setCurrentPanel('instructor');
    $this->get("/instructor/courses/{$course->id}/completion")->assertForbidden();

    // Non-learner refused on domain ApproveCourseCompletion
    $this->actingAs($instructor);
    expect(fn () => app(ApproveCourseCompletion::class)($instructor, $course, $stranger, 'Not enrolled'))->toThrow(ValidationException::class);

    // Livewire ManageCourseCompletion: too-short reason halts + notifies; valid reason approves and issues certificate
    Filament::setCurrentPanel('instructor');
    $page = Livewire::test(ManageCourseCompletion::class, ['record' => $course->id])->assertOk();
    $page->callAction('approve', ['reason' => 'no'], arguments: ['learner' => $learner->id])
        ->assertHasActionErrors(['reason'])
        ->unmountAction();

    // Force ValidationException through action (non-enrolled) -> halts + notifies
    $page->callAction('approve', ['reason' => 'Manual sign-off'], arguments: ['learner' => $stranger->id])
        ->assertActionHalted('approve')->assertNotified()->unmountAction();

    $page->callAction('editRules', [
        'lesson_percent' => 80,
        'require_quizzes' => false,
        'require_assignments' => false,
        'attendance_percent' => null,
        'manual_approval' => true,
    ])->assertHasNoActionErrors();
    expect(CourseCompletionRule::forCourse($course)->lesson_percent)->toBe(80);

    $page->callAction('approve', ['reason' => 'Completed all portfolio critiques offline'], arguments: ['learner' => $learner->id])
        ->assertHasNoActionErrors();
    expect(CourseCompletion::query()->where('course_id', $course->id)->where('learner_id', $learner->id)->value('source'))
        ->toBe(CourseCompletion::MANUAL);

    // AuditLogger failure rolls back auto-completion inside DB transaction
    $learner2 = User::factory()->create(['status' => 'active'])->assignRole('student');
    app(GrantEntitlement::class)($learner2, $course, EntitlementSource::Free);
    app(SaveCompletionRules::class)($instructor, $course, ['lesson_percent' => 100, 'manual_approval' => false]);
    app(RecordProgress::class)->markComplete($learner2, $lessonItem->fresh());
    CourseCompletion::query()->where('learner_id', $learner2->id)->delete();

    activity()->disableLogging();
    try {
        expect(fn () => app(CourseCompletionEvaluator::class)($learner2, $course))->toThrow(RuntimeException::class);
    } finally {
        activity()->enableLogging();
    }
    expect(CourseCompletion::query()->where('course_id', $course->id)->where('learner_id', $learner2->id)->exists())->toBeFalse();
})->group('T-P6-05', 'A1', 'A2', 'O-020');
