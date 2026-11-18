<?php

declare(strict_types=1);

use App\Domain\Assessment\Actions\ConfigureQuiz;
use App\Domain\Assessment\Actions\GradeEssayAnswer;
use App\Domain\Assessment\Actions\ManageQuestions;
use App\Domain\Assessment\Actions\StartQuizAttempt;
use App\Domain\Assessment\Actions\SubmitQuizAttempt;
use App\Domain\Assessment\Notifications\EssayReviewedNotice;
use App\Domain\Assignments\Actions\AddSubmissionFeedback;
use App\Domain\Assignments\Actions\ConfigureAssignment;
use App\Domain\Assignments\Actions\DecideSubmission;
use App\Domain\Assignments\Actions\SaveSubmissionDraft;
use App\Domain\Assignments\Actions\SubmitAssignment;
use App\Domain\Assignments\Notifications\SubmissionDecisionNotice;
use App\Domain\Assignments\ReviewAccess;
use App\Domain\Assignments\ReviewRefusedException;
use App\Domain\Assignments\SubmissionReview;
use App\Domain\Curriculum\Actions\ManageAssessmentItems;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Progress\LessonProgress;
use App\Filament\Resources\EssayReview\EssayReviewResource;
use App\Filament\Resources\EssayReview\Pages\ListEssayReviews;
use App\Filament\Resources\EssayReview\Pages\ReviewEssayAttempt;
use App\Filament\Resources\SubmissionReview\Pages\ListSubmissionReviews;
use App\Filament\Resources\SubmissionReview\Pages\ReviewSubmission;
use App\Filament\Resources\SubmissionReview\SubmissionReviewResource;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\SubmissionDecision;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

/** @return array{course: Course, instructor: User, learner: User, assignment: Assignment, submission: AssignmentSubmission, section: CourseSection} */
function revSetup(bool $late = false): array
{
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة مراجعة', 'en' => 'Review Course'],
        'slug_ar' => 'rev-ar-'.uniqid(),
        'slug_en' => 'rev-en-'.uniqid(),
        'status' => 'published',
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['ar' => 'قسم', 'en' => 'Section']]);
    $section->update(['published' => true]);

    $item = app(ManageAssessmentItems::class)->add($instructor, $section, 'assignment', ['title' => ['ar' => 'واجب', 'en' => 'Portfolio Task']]);
    /** @var Assignment $assignment */
    $assignment = $item->itemable;
    app(ConfigureAssignment::class)($instructor, $assignment, [
        'allowed_types' => ['text'],
        'due_at' => $late ? now()->addDay()->format('Y-m-d H:i:s') : now()->addDays(5)->format('Y-m-d H:i:s'),
        'timezone' => 'UTC',
        'late_policy' => Assignment::LATE_PENALTY,
        'late_penalty_percent' => 25,
        'max_revisions' => 1,
    ]);
    app(SetCurriculumStatus::class)($instructor, $course, [$item->id], CurriculumItem::STATUS_PUBLISHED);
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);

    if ($late) {
        $assignment->forceFill(['due_at' => now()->subHours(2)])->save();
    }

    app(SaveSubmissionDraft::class)($learner, $item->fresh(), 'First submission body');
    $submission = app(SubmitAssignment::class)($learner, $item->fresh());

    return [
        'course' => $course,
        'instructor' => $instructor,
        'learner' => $learner,
        'assignment' => $assignment->fresh(),
        'submission' => $submission->fresh(),
        'section' => $section,
    ];
}

it('validates review decisions, applies late penalties, avoids duplicate notices, and rolls back on audit failure', function (): void {
    Notification::fake();
    ['instructor' => $instructor, 'learner' => $learner, 'assignment' => $assignment, 'submission' => $submission] = revSetup(late: true);
    $decide = app(DecideSubmission::class);
    $comment = app(AddSubmissionFeedback::class);

    // Draft submission cannot be decided or commented on
    $otherLearner = User::factory()->create(['status' => 'active'])->assignRole('student');
    app(GrantEntitlement::class)($otherLearner, $assignment->item->course, EntitlementSource::Free);
    $draft = app(SaveSubmissionDraft::class)($otherLearner, $assignment->item, 'Draft text');
    expect(fn () => $decide($instructor, $draft, SubmissionDecision::ACCEPTED))->toThrow(ReviewRefusedException::class)
        ->and(fn () => $comment($instructor, $draft, 'Note'))->toThrow(ReviewRefusedException::class);

    // Validation bounds & required fields
    expect(fn () => $decide($instructor, $submission, SubmissionDecision::GRADED, null, 'Ok'))->toThrow(ReviewRefusedException::class)
        ->and(fn () => $decide($instructor, $submission, SubmissionDecision::GRADED, 105, 'Too high'))->toThrow(ReviewRefusedException::class)
        ->and(fn () => $decide($instructor, $submission, SubmissionDecision::GRADED, -1, 'Negative'))->toThrow(ReviewRefusedException::class)
        ->and(fn () => $decide($instructor, $submission, SubmissionDecision::REJECTED, null, '   '))->toThrow(ReviewRefusedException::class)
        ->and(fn () => $decide($instructor, $submission, SubmissionDecision::REVISION_REQUESTED, null, ''))->toThrow(ReviewRefusedException::class);

    // Valid late GRADED decision applies 25% penalty: 80 -> 60.0 and sends 1 notice
    $decision1 = $decide($instructor, $submission, SubmissionDecision::GRADED, 80, 'Solid composition.');
    expect((float) $decision1->score)->toBe(80.0)
        ->and($decision1->late_penalty_percent)->toBe(25)
        ->and((float) $decision1->final_score)->toBe(60.0)
        ->and($submission->fresh()->status)->toBe(AssignmentSubmission::GRADED)
        ->and(Activity::query()->where('event', 'assignment.graded')->count())->toBe(1);
    Notification::assertSentToTimes($learner, SubmissionDecisionNotice::class, 1);

    // Identical repeat decision records history row without duplicate notification
    $decide($instructor, $submission->fresh(), SubmissionDecision::GRADED, 80, 'Solid composition.');
    Notification::assertSentToTimes($learner, SubmissionDecisionNotice::class, 1);

    // AddSubmissionFeedback appends comment & audits without notifying
    expect(fn () => $comment($instructor, $submission->fresh(), '  '))->toThrow(ReviewRefusedException::class);
    $fb = $comment($instructor, $submission->fresh(), 'Please inspect edge alignment.');
    expect($fb->body)->toBe('Please inspect edge alignment.')
        ->and(Activity::query()->where('event', 'assignment.feedback_added')->count())->toBe(1);
    Notification::assertSentToTimes($learner, SubmissionDecisionNotice::class, 1);

    // Request revision (revision 1 of max_revisions=1) -> learner resubmits -> 2nd revision request refused -> ACCEPTED completes item
    $decide($instructor, $submission->fresh(), SubmissionDecision::REVISION_REQUESTED, 99, 'Tighten shading.');
    expect($submission->fresh()->status)->toBe(AssignmentSubmission::RETURNED)
        ->and(SubmissionReview::canDecide($submission->fresh()->status))->toBeFalse();
    Notification::assertSentToTimes($learner, SubmissionDecisionNotice::class, 2);

    app(SaveSubmissionDraft::class)($learner, $assignment->item, 'Improved second revision');
    $resubmitted = app(SubmitAssignment::class)($learner, $assignment->item);
    expect($resubmitted->status)->toBe(AssignmentSubmission::RESUBMITTED);

    expect(fn () => $decide($instructor, $resubmitted, SubmissionDecision::REVISION_REQUESTED, null, 'Again'))
        ->toThrow(ReviewRefusedException::class);

    $decide($instructor, $resubmitted, SubmissionDecision::ACCEPTED, null, 'Approved now.');
    expect($resubmitted->fresh()->status)->toBe(AssignmentSubmission::ACCEPTED)
        ->and(Activity::query()->where('event', 'assignment.accepted')->count())->toBe(1);

    // AuditLogger failure rolls back DB decision cleanly
    activity()->disableLogging();
    try {
        expect(fn () => $decide($instructor, $resubmitted->fresh(), SubmissionDecision::GRADED, 90, 'Audit fail'))
            ->toThrow(RuntimeException::class);
    } finally {
        activity()->enableLogging();
    }
    expect($resubmitted->fresh()->status)->toBe(AssignmentSubmission::ACCEPTED);
})->group('T-P6-04', 'A1');

it('grades essay answers, updates attempt pass state and notices, and enforces ReviewAccess across roles and Filament queues', function (): void {
    Notification::fake();
    ['course' => $course, 'instructor' => $instructor, 'learner' => $learner, 'submission' => $submission, 'section' => $section] = revSetup();
    ['course' => $foreignCourse, 'submission' => $foreignSub] = revSetup();

    $quizItem = app(ManageAssessmentItems::class)->add($instructor, $section, 'quiz', ['title' => ['en' => 'Critique Quiz']]);
    /** @var Quiz $quiz */
    $quiz = $quizItem->itemable;
    app(ConfigureQuiz::class)($instructor, $quiz, ['pass_mark_percent' => 60, 'result_visibility' => 'full', 'progression' => 'require_pass']);
    $tf = app(ManageQuestions::class)->add($instructor, $quiz, ['type' => Question::TRUE_FALSE, 'prompt' => ['en' => 'TF'], 'points' => 2, 'true_is_correct' => true]);
    $essay = app(ManageQuestions::class)->add($instructor, $quiz, ['type' => Question::ESSAY, 'prompt' => ['en' => 'Explain composition'], 'points' => 8]);
    app(SetCurriculumStatus::class)($instructor, $course, [$quizItem->id], CurriculumItem::STATUS_PUBLISHED);

    $attempt = app(StartQuizAttempt::class)($learner, $quizItem->fresh());
    $submitted = app(SubmitQuizAttempt::class)($learner, $attempt, [
        $tf->id => ['selected' => [collect($attempt->snapshot['questions'])->firstWhere('id', $tf->id)['options'][0]['key']]],
        $essay->id => ['text' => 'Foreground and depth ratio.'],
    ]);
    expect($submitted->needs_review)->toBeTrue()->and($submitted->passed)->toBeNull();

    // Essay grading validation guards
    $gradeEssay = app(GradeEssayAnswer::class);
    expect(fn () => $gradeEssay($instructor, $submitted, $tf->id, 2.0))->toThrow(ReviewRefusedException::class)
        ->and(fn () => $gradeEssay($instructor, $submitted, $essay->id, 9.0))->toThrow(ReviewRefusedException::class);

    // Grade essay 5/8 -> total (2+5)/10 = 70% >= 60% -> needs_review clears, passed=true, completes require_pass item, 1 notification
    $gradeEssay($instructor, $submitted->fresh(), $essay->id, 5.0, 'Good analysis.');
    expect($submitted->fresh()->needs_review)->toBeFalse()
        ->and($submitted->fresh()->passed)->toBeTrue()
        ->and((float) $submitted->fresh()->percent)->toBe(70.0)
        ->and(LessonProgress::query()->where('learner_id', $learner->id)->where('curriculum_item_id', $quizItem->id)->value('status'))
        ->toBe(LessonProgress::COMPLETED);
    Notification::assertSentToTimes($learner, EssayReviewedNotice::class, 1);

    // Subsequent re-grade audits without duplicate EssayReviewedNotice
    $gradeEssay($instructor, $submitted->fresh(), $essay->id, 6.0, 'Updated score.');
    expect((float) $submitted->fresh()->percent)->toBe(80.0);
    Notification::assertSentToTimes($learner, EssayReviewedNotice::class, 1);

    // Role & scope isolation
    $viewInstructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $course->instructors()->attach($viewInstructor->id, ['role' => 'instructor', 'scope' => 'view']);
    $otherInstructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $contentManager = User::factory()->create(['status' => 'active'])->assignRole('content_manager');
    $suspended = User::factory()->create(['status' => 'suspended'])->assignRole('admin');
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');

    expect(ReviewAccess::allows($instructor, $course))->toBeTrue()
        ->and(ReviewAccess::allows($admin, $course))->toBeTrue()
        ->and(ReviewAccess::allows($viewInstructor, $course))->toBeFalse()
        ->and(ReviewAccess::allows($otherInstructor, $course))->toBeFalse()
        ->and(ReviewAccess::allows($contentManager, $course))->toBeFalse()
        ->and(ReviewAccess::allows($learner, $course))->toBeFalse()
        ->and(ReviewAccess::allows($suspended, $course))->toBeFalse();

    expect(fn () => app(DecideSubmission::class)($viewInstructor, $submission, SubmissionDecision::ACCEPTED))
        ->toThrow(AuthorizationException::class);

    foreach ([$viewInstructor, $otherInstructor, $contentManager, $learner, $suspended] as $denied) {
        $this->actingAs($denied);
        Filament::setCurrentPanel('instructor');
        expect($this->get("/instructor/submission-review/{$submission->id}")->getStatusCode())->toBeIn([403, 404]);
        expect($this->get("/instructor/essay-review/{$submitted->id}")->getStatusCode())->toBeIn([403, 404]);
    }

    // Scoped queue lists only reviewer's courses & never leaks foreign course records
    $this->actingAs($instructor);
    Filament::setCurrentPanel('instructor');
    expect(SubmissionReviewResource::courseIds())->toContain($course->id)->not->toContain($foreignCourse->id)
        ->and(EssayReviewResource::courseIds())->toContain($course->id)->not->toContain($foreignCourse->id);
    $this->get("/instructor/submission-review/{$foreignSub->id}")->assertNotFound();

    Livewire::test(ListSubmissionReviews::class)->assertOk();
    Livewire::test(ListEssayReviews::class)->assertOk();

    // Filament ReviewSubmission halted refusal -> unmountAction() -> valid action
    $reviewPage = Livewire::test(ReviewSubmission::class, ['record' => $submission->id])->assertOk();
    $reviewPage->callAction('grade', ['score' => 150, 'feedback' => 'Out of bounds'])
        ->assertHasActionErrors(['score'])
        ->unmountAction();
    $reviewPage->callAction('accept', ['feedback' => 'Clear work.'])
        ->assertHasNoActionErrors();
    expect($submission->fresh()->status)->toBe(AssignmentSubmission::ACCEPTED);

    $essayPage = Livewire::test(ReviewEssayAttempt::class, ['record' => $submitted->id])->assertOk();
    $essayPage->callAction('grade', ['points' => 99, 'feedback' => 'Too high'], arguments: ['question' => $essay->id])
        ->assertActionHalted('grade')->assertNotified()->unmountAction();
    $essayPage->callAction('grade', ['points' => 7.0, 'feedback' => 'Well revised.'], arguments: ['question' => $essay->id])
        ->assertHasNoActionErrors();
    expect((float) $submitted->fresh()->percent)->toBe(90.0);
})->group('T-P6-04', 'A1', 'A2');
