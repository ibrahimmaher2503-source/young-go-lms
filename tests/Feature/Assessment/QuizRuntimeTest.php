<?php

declare(strict_types=1);

use App\Domain\Assessment\Actions\ConfigureQuiz;
use App\Domain\Assessment\Actions\ExpireQuizAttempts;
use App\Domain\Assessment\Actions\GradeEssayAnswer;
use App\Domain\Assessment\Actions\ManageQuestions;
use App\Domain\Assessment\Actions\ReleaseQuizResult;
use App\Domain\Assessment\Actions\SaveQuizAnswers;
use App\Domain\Assessment\Actions\StartQuizAttempt;
use App\Domain\Assessment\Actions\SubmitQuizAttempt;
use App\Domain\Assessment\Queries\QuizAttemptView;
use App\Domain\Assessment\Queries\QuizOverview;
use App\Domain\Assessment\QuizGuard;
use App\Domain\Assessment\QuizRefusedException;
use App\Domain\Curriculum\Actions\ManageAssessmentItems;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Progress\LessonProgress;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAnswer;
use App\Models\QuizAttempt;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

/**
 * Builds a published course containing a published quiz item and a sequential follow-up lesson item.
 *
 * @param  array<string, mixed>  $settings
 * @return array{course: Course, instructor: User, quiz: Quiz, quizItem: CurriculumItem, nextItem: CurriculumItem, section: CourseSection}
 */
function qrunCourseSetup(array $settings = []): array
{
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة الاختبار', 'en' => 'Quiz Runtime Course'],
        'slug_ar' => 'qrun-ar-'.uniqid(),
        'slug_en' => 'qrun-en-'.uniqid(),
        'status' => 'published',
        'sequence_mode' => Course::SEQUENCE_SEQUENTIAL,
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['ar' => 'القسم 1', 'en' => 'Section 1']]);
    $section->update(['published' => true]);

    $quizItem = app(ManageAssessmentItems::class)->add($instructor, $section, 'quiz', ['title' => ['ar' => 'اختبار وحدة', 'en' => 'Module Quiz']]);
    /** @var Quiz $quiz */
    $quiz = $quizItem->itemable;
    app(ConfigureQuiz::class)($instructor, $quiz, [
        'time_limit_minutes' => 10,
        'pass_mark_percent' => 60,
        'max_attempts' => 3,
        'result_visibility' => 'full',
        'randomize' => false,
        'progression' => 'after_submission',
        ...$settings,
    ]);
    $nextItem = app(ManageLessons::class)->add($instructor, $section, ['type' => 'text', 'title' => ['ar' => 'الدرس التالي', 'en' => 'Next Lesson']]);
    app(SetCurriculumStatus::class)($instructor, $course, [$quizItem->id, $nextItem->id], CurriculumItem::STATUS_PUBLISHED);

    return [
        'course' => $course,
        'instructor' => $instructor,
        'quiz' => $quiz->fresh(),
        'quizItem' => $quizItem->fresh(),
        'nextItem' => $nextItem->fresh(),
        'section' => $section,
    ];
}

/**
 * Populates a quiz with one question of each supported type (total 15 points).
 *
 * @return array{single: Question, multiple: Question, tf: Question, short: Question, essay: Question}
 */
function qrunAddAllQuestionTypes(User $actor, Quiz $quiz): array
{
    $manage = app(ManageQuestions::class);
    $single = $manage->add($actor, $quiz, [
        'type' => Question::SINGLE,
        'prompt' => ['ar' => 'أولاً', 'en' => 'Single Choice Prompt'],
        'explanation' => ['en' => 'EXPLANATION_SHOWN_SINGLE'],
        'points' => 2,
        'options' => [
            ['text' => ['en' => 'Single Correct'], 'is_correct' => true],
            ['text' => ['en' => 'Single Wrong'], 'is_correct' => false],
        ],
    ]);
    $multiple = $manage->add($actor, $quiz, [
        'type' => Question::MULTIPLE,
        'prompt' => ['en' => 'Multiple Choice Prompt'],
        'points' => 4,
        'options' => [
            ['text' => ['en' => 'Multi A'], 'is_correct' => true],
            ['text' => ['en' => 'Multi B'], 'is_correct' => true],
            ['text' => ['en' => 'Multi C'], 'is_correct' => false],
        ],
    ]);
    $tf = $manage->add($actor, $quiz, [
        'type' => Question::TRUE_FALSE,
        'prompt' => ['en' => 'True False Prompt'],
        'points' => 2,
        'true_is_correct' => true,
    ]);
    $short = $manage->add($actor, $quiz, [
        'type' => Question::SHORT_ANSWER,
        'prompt' => ['en' => 'Short Answer Prompt'],
        'points' => 2,
        'options' => [
            ['text' => ['en' => '  WaterColor  ']],
            ['text' => ['en' => 'SECRET_ACCEPTED_GOUACHE']],
        ],
    ]);
    $essay = $manage->add($actor, $quiz, [
        'type' => Question::ESSAY,
        'prompt' => ['en' => 'Essay Prompt'],
        'points' => 5,
    ]);

    return compact('single', 'multiple', 'tf', 'short', 'essay');
}

it('enforces start, resume, access, draft, zero-question, and max-attempt guards on runtime routes', function (): void {
    ['course' => $course, 'instructor' => $instructor, 'quiz' => $quiz, 'quizItem' => $quizItem] = qrunCourseSetup(['max_attempts' => 2, 'time_limit_minutes' => 15]);
    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');
    $stranger = User::factory()->create(['status' => 'active'])->assignRole('student');
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);

    // 0 active questions -> refused on domain + HTTP card shows empty notice
    expect(fn () => app(StartQuizAttempt::class)($learner, $quizItem))->toThrow(QuizRefusedException::class, __('quiz.attempt.refused.no_questions'));
    $this->actingAs($learner)->get(route('learn.show', ['course' => $course->id, 'item' => $quizItem->id]))
        ->assertOk()
        ->assertSee('data-quiz-empty', false);
    $this->post(route('quiz.start', ['course' => $course->id, 'item' => $quizItem->id]))
        ->assertRedirect(route('learn.show', ['course' => $course->id, 'item' => $quizItem->id]))
        ->assertSessionHas('quiz_error');

    qrunAddAllQuestionTypes($instructor, $quiz);

    // Unentitled learner gets NO_ACCESS and 404
    expect(fn () => app(StartQuizAttempt::class)($stranger, $quizItem))->toThrow(QuizRefusedException::class);
    $this->actingAs($stranger)->post(route('quiz.start', ['course' => $course->id, 'item' => $quizItem->id]))->assertNotFound();

    // Start attempt 1 via HTTP -> redirects to timed quiz attempt page & QuizGuard activates
    $startRes = $this->actingAs($learner)->post(route('quiz.start', ['course' => $course->id, 'item' => $quizItem->id]));
    $attempt1 = QuizAttempt::query()->where('quiz_id', $quiz->id)->where('learner_id', $learner->id)->firstOrFail();
    $startRes->assertRedirect(route('quiz.attempt', ['course' => $course->id, 'item' => $quizItem->id, 'attempt' => $attempt1->id]));
    expect($attempt1->attempt_no)->toBe(1)
        ->and($attempt1->deadline_at)->not->toBeNull()
        ->and(QuizGuard::isActive($learner))->toBeTrue();

    // Starting again while open resumes attempt 1 without creating attempt 2
    $this->actingAs($learner)->post(route('quiz.start', ['course' => $course->id, 'item' => $quizItem->id]))
        ->assertRedirect(route('quiz.attempt', ['course' => $course->id, 'item' => $quizItem->id, 'attempt' => $attempt1->id]));
    expect(QuizAttempt::query()->where('quiz_id', $quiz->id)->count())->toBe(1);

    // Another learner cannot open, save, or submit this attempt
    $this->actingAs($stranger)->get(route('quiz.attempt', ['course' => $course->id, 'item' => $quizItem->id, 'attempt' => $attempt1->id]))->assertNotFound();
    $this->postJson(route('quiz.save', ['course' => $course->id, 'item' => $quizItem->id, 'attempt' => $attempt1->id]), ['answers' => []])->assertNotFound();
    $this->post(route('quiz.submit', ['course' => $course->id, 'item' => $quizItem->id, 'attempt' => $attempt1->id]))->assertNotFound();

    // Submit attempt 1 -> QuizGuard clears; start + submit attempt 2 -> max_attempts exhausted on 3rd try
    $this->actingAs($learner)->post(route('quiz.submit', ['course' => $course->id, 'item' => $quizItem->id, 'attempt' => $attempt1->id]))
        ->assertRedirect(route('quiz.attempt', ['course' => $course->id, 'item' => $quizItem->id, 'attempt' => $attempt1->id]));
    expect(QuizGuard::isActive($learner))->toBeFalse();

    $attempt2 = app(StartQuizAttempt::class)($learner, $quizItem);
    app(SubmitQuizAttempt::class)($learner, $attempt2);
    expect(QuizOverview::for($learner, $quiz)['left'])->toBe(0)
        ->and(QuizOverview::for($learner, $quiz)['can_start'])->toBeFalse();
    expect(fn () => app(StartQuizAttempt::class)($learner, $quizItem))->toThrow(QuizRefusedException::class, __('quiz.attempt.refused.attempts_exhausted'));
    $this->actingAs($learner)->get(route('learn.show', ['course' => $course->id, 'item' => $quizItem->id]))
        ->assertOk()
        ->assertSee('data-attempts-exhausted', false);

    // Draft item refuses start
    app(SetCurriculumStatus::class)($instructor, $course, [$quizItem->id], CurriculumItem::STATUS_DRAFT);
    expect(fn () => app(StartQuizAttempt::class)($learner, $quizItem->fresh()))->toThrow(QuizRefusedException::class);
})->group('T-P6-02', 'A1', 'ED-001');

it('freezes attempt snapshot and never leaks answer keys or explanations during open attempt', function (): void {
    ['course' => $course, 'instructor' => $instructor, 'quiz' => $quiz, 'quizItem' => $quizItem] = qrunCourseSetup(['randomize' => false]);
    $questions = qrunAddAllQuestionTypes($instructor, $quiz);
    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);

    $attempt = app(StartQuizAttempt::class)($learner, $quizItem);

    // Model serialization hides snapshot and settings
    $serialized = $attempt->toArray();
    expect($serialized)->not->toHaveKeys(['snapshot', 'settings']);

    // QuizAttemptView::questions omits is_correct, explanation, and accepted short answer list
    $viewQuestions = QuizAttemptView::questions($attempt);
    $viewJson = json_encode($viewQuestions, JSON_THROW_ON_ERROR);
    expect($viewJson)->not->toContain('is_correct')
        ->not->toContain('EXPLANATION_SHOWN_SINGLE')
        ->not->toContain('SECRET_ACCEPTED_GOUACHE');

    // HTTP render of open attempt never leaks secrets
    $this->actingAs($learner)->get(route('quiz.attempt', ['course' => $course->id, 'item' => $quizItem->id, 'attempt' => $attempt->id]))
        ->assertOk()
        ->assertSee('data-quiz-form', false)
        ->assertSee('Single Correct')
        ->assertDontSee('EXPLANATION_SHOWN_SINGLE')
        ->assertDontSee('SECRET_ACCEPTED_GOUACHE');

    // Editing question bank and settings after start does NOT mutate attempt 1 snapshot
    app(ManageQuestions::class)->update($instructor, $questions['single'], [
        'type' => Question::SINGLE,
        'prompt' => ['en' => 'Edited Prompt V2'],
        'points' => 50,
        'options' => [
            ['text' => ['en' => 'New V2 Correct'], 'is_correct' => true],
            ['text' => ['en' => 'New V2 Wrong'], 'is_correct' => false],
        ],
    ]);
    app(ManageQuestions::class)->setActive($instructor, $questions['essay'], false);
    app(ConfigureQuiz::class)($instructor, $quiz, ['pass_mark_percent' => 95, 'result_visibility' => 'hidden', 'progression' => 'after_submission']);

    $freshAttempt = $attempt->fresh();
    expect(collect($freshAttempt->snapshot['questions'])->pluck('id')->all())->toContain($questions['essay']->id)
        ->and($freshAttempt->settings['pass_mark_percent'])->toBe(60)
        ->and($freshAttempt->settings['result_visibility'])->toBe('full');

    // Selecting Single Correct (from V1) still grades against V1 points (2) cleanly
    $snapSingle = collect($freshAttempt->snapshot['questions'])->firstWhere('id', $questions['single']->id);
    $correctKey = collect($snapSingle['options'])->firstWhere('is_correct', true)['key'];
    $done = app(SubmitQuizAttempt::class)($learner, $freshAttempt, [$questions['single']->id => ['selected' => [$correctKey]]]);
    expect((float) $done->score)->toBe(2.0)->and((float) $done->total)->toBe(15.0);
})->group('T-P6-02', 'A1', 'ED-001');

it('grades every question type accurately, handles blank vs written essays, and enforces result visibility', function (): void {
    ['course' => $course, 'instructor' => $instructor, 'quiz' => $quiz, 'quizItem' => $quizItem] = qrunCourseSetup([
        'pass_mark_percent' => 60,
        'result_visibility' => 'hidden',
        'max_attempts' => 5,
    ]);
    $q = qrunAddAllQuestionTypes($instructor, $quiz);
    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);

    // Attempt 1: answer all auto questions correctly (10/15 = 66.67%) + written essay -> needs_review = true, passed = null, hidden visibility
    $attempt1 = app(StartQuizAttempt::class)($learner, $quizItem);
    $snapById = collect($attempt1->snapshot['questions'])->keyBy('id');
    $singleKey = collect($snapById[$q['single']->id]['options'])->firstWhere('is_correct', true)['key'];
    $multiKeys = collect($snapById[$q['multiple']->id]['options'])->where('is_correct', true)->pluck('key')->values()->all();
    $tfTrueKey = collect($snapById[$q['tf']->id]['options'])->first()['key']; // TF keeps natural True=1, False=2

    $this->actingAs($learner)->postJson(route('quiz.save', ['course' => $course->id, 'item' => $quizItem->id, 'attempt' => $attempt1->id]), [
        'answers' => [
            $q['single']->id => ['selected' => [$singleKey]],
            $q['multiple']->id => ['selected' => $multiKeys],
            $q['tf']->id => ['selected' => [$tfTrueKey]],
            $q['short']->id => ['text' => '  watercolor  '],
            $q['essay']->id => ['text' => 'Written essay response for instructor.'],
        ],
    ])->assertOk()->assertJsonPath('saved', true);

    $submitted1 = app(SubmitQuizAttempt::class)($learner, $attempt1->fresh());
    expect((float) $submitted1->score)->toBe(10.0)
        ->and((float) $submitted1->total)->toBe(15.0)
        ->and((float) $submitted1->percent)->toBe(66.67)
        ->and($submitted1->needs_review)->toBeTrue()
        ->and($submitted1->passed)->toBeNull()
        ->and($submitted1->submitted_by)->toBe('learner');

    // Hidden result until ReleaseQuizResult & GradeEssayAnswer
    $this->actingAs($learner)->get(route('quiz.attempt', ['course' => $course->id, 'item' => $quizItem->id, 'attempt' => $attempt1->id]))
        ->assertOk()
        ->assertSee('data-result-hidden', false)
        ->assertDontSee('EXPLANATION_SHOWN_SINGLE');

    app(GradeEssayAnswer::class)($instructor, $submitted1->fresh(), $q['essay']->id, 3.0, 'Nice structure.');
    app(ReleaseQuizResult::class)($instructor, $submitted1->fresh());

    $this->actingAs($learner)->get(route('quiz.attempt', ['course' => $course->id, 'item' => $quizItem->id, 'attempt' => $attempt1->id]))
        ->assertOk()
        ->assertSee('data-score', false)
        ->assertSee('13 / 15')
        ->assertSee('EXPLANATION_SHOWN_SINGLE');

    // Partial multiple choice + wrong short answer + blank essay -> blank essay gets 0 immediately (needs_review = false, failed)
    app(ConfigureQuiz::class)($instructor, $quiz, ['pass_mark_percent' => 60, 'result_visibility' => 'score_only', 'max_attempts' => 5, 'progression' => 'after_submission']);
    $attempt2 = app(StartQuizAttempt::class)($learner, $quizItem);
    $snap2 = collect($attempt2->snapshot['questions'])->keyBy('id');
    $oneMultiKey = collect($snap2[$q['multiple']->id]['options'])->firstWhere('is_correct', true)['key'];

    $submitted2 = app(SubmitQuizAttempt::class)($learner, $attempt2, [
        $q['multiple']->id => ['selected' => [$oneMultiKey]], // partial credit -> 0
        $q['short']->id => ['text' => 'wrong text'],
        $q['essay']->id => ['text' => '   '], // whitespace essay treated as blank -> 0, not pending
    ]);
    $essayRow2 = QuizAnswer::query()->where('quiz_attempt_id', $submitted2->id)->where('question_id', $q['essay']->id)->first();
    expect((float) $submitted2->score)->toBe(0.0)
        ->and($submitted2->needs_review)->toBeFalse()
        ->and($submitted2->passed)->toBeFalse()
        ->and((float) $essayRow2?->points_awarded)->toBe(0.0);

    // Repeating SubmitQuizAttempt is idempotent (audit count unchanged)
    $auditCount = Activity::query()->where('event', 'quiz.attempt_submitted')->count();
    $again = app(SubmitQuizAttempt::class)($learner, $submitted2->fresh());
    expect(Activity::query()->where('event', 'quiz.attempt_submitted')->count())->toBe($auditCount)
        ->and($again->submitted_at?->equalTo($submitted2->submitted_at))->toBeTrue();

    // score_only hides explanations while displaying score and outcome
    $this->actingAs($learner)->get(route('quiz.attempt', ['course' => $course->id, 'item' => $quizItem->id, 'attempt' => $submitted2->id]))
        ->assertOk()
        ->assertSee('0 / 15')
        ->assertDontSee('EXPLANATION_SHOWN_SINGLE');
})->group('T-P6-02', 'A2');

it('enforces timer grace window, GET expired non-mutation, late payload rejection, and ExpireQuizAttempts command', function (): void {
    ['course' => $course, 'instructor' => $instructor, 'quiz' => $quiz, 'quizItem' => $quizItem] = qrunCourseSetup(['time_limit_minutes' => 10, 'max_attempts' => 5]);
    $q = qrunAddAllQuestionTypes($instructor, $quiz);
    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);

    $grace = (int) config('assessment.grace_seconds');

    // Attempt 1: saved within deadline, then clock moves past grace -> GET does NOT mutate DB; late POST save returns 409 + auto-submits saved answers ONLY
    $attempt1 = app(StartQuizAttempt::class)($learner, $quizItem);
    $snap1 = collect($attempt1->snapshot['questions'])->keyBy('id');
    $singleKey1 = collect($snap1[$q['single']->id]['options'])->firstWhere('is_correct', true)['key'];

    // Within grace (deadline 1s ago) -> save is still accepted
    $attempt1->forceFill(['deadline_at' => now()->subSecond()])->save();
    $savedCount = app(SaveQuizAnswers::class)($learner, $attempt1->fresh(), [$q['single']->id => ['selected' => [$singleKey1]]]);
    expect($savedCount)->toBe(1);

    // Past deadline + grace -> GET renders expired notice without mutating DB
    $attempt1->forceFill(['deadline_at' => now()->subSeconds($grace + 30)])->save();
    $this->actingAs($learner)->get(route('quiz.attempt', ['course' => $course->id, 'item' => $quizItem->id, 'attempt' => $attempt1->id]))
        ->assertOk()
        ->assertSee(__('quiz.attempt.expired_pending'));
    expect($attempt1->fresh()->status)->toBe(QuizAttempt::IN_PROGRESS)
        ->and($attempt1->fresh()->submitted_at)->toBeNull();

    // HTTP POST save past grace -> 409 expired + auto-submits using previously saved single answer (2 pts), ignoring new TF answer!
    $tfKey1 = collect($snap1[$q['tf']->id]['options'])->first()['key'];
    $this->actingAs($learner)->postJson(route('quiz.save', ['course' => $course->id, 'item' => $quizItem->id, 'attempt' => $attempt1->id]), [
        'answers' => [$q['tf']->id => ['selected' => [$tfKey1]]],
    ])->assertStatus(409)
        ->assertJsonPath('reason', QuizRefusedException::EXPIRED)
        ->assertJsonPath('closed', true);

    $afterLate = $attempt1->fresh();
    expect($afterLate->status)->toBe(QuizAttempt::SUBMITTED)
        ->and($afterLate->submitted_by)->toBe('auto')
        ->and((float) $afterLate->score)->toBe(2.0);

    // Attempt 2: expired past grace; ExpireQuizAttempts leaves in-grace attempt open and closes overdue attempt
    $overdue = app(StartQuizAttempt::class)($learner, $quizItem);
    $snap2 = collect($overdue->snapshot['questions'])->keyBy('id');
    $tfKey2 = collect($snap2[$q['tf']->id]['options'])->first()['key'];
    app(SaveQuizAnswers::class)($learner, $overdue, [$q['tf']->id => ['selected' => [$tfKey2]]]);
    $overdue->forceFill(['deadline_at' => now()->subSeconds($grace + 15)])->save();

    $otherLearner = User::factory()->create(['status' => 'active'])->assignRole('student');
    app(GrantEntitlement::class)($otherLearner, $course, EntitlementSource::Free);
    $stillInGrace = app(StartQuizAttempt::class)($otherLearner, $quizItem);
    $stillInGrace->forceFill(['deadline_at' => now()->subSeconds(1)])->save();

    $expiredCount = app(ExpireQuizAttempts::class)();
    expect($expiredCount)->toBe(1)
        ->and($overdue->fresh()->status)->toBe(QuizAttempt::SUBMITTED)
        ->and($overdue->fresh()->submitted_by)->toBe('auto')
        ->and((float) $overdue->fresh()->score)->toBe(2.0)
        ->and($stillInGrace->fresh()->status)->toBe(QuizAttempt::IN_PROGRESS);
})->group('T-P6-02', 'A3', 'A4');

it('controls item completion and sequential gating for after_submission vs require_pass quizzes', function (): void {
    // 1) after_submission blocks subsequent items until submitted, then unlocks & completes even if failed
    ['course' => $course1, 'instructor' => $inst1, 'quiz' => $quiz1, 'quizItem' => $item1, 'nextItem' => $next1] = qrunCourseSetup([
        'pass_mark_percent' => 80,
        'progression' => 'after_submission',
    ]);
    qrunAddAllQuestionTypes($inst1, $quiz1);
    $learner1 = User::factory()->create(['status' => 'active'])->assignRole('student');
    app(GrantEntitlement::class)($learner1, $course1, EntitlementSource::Free);

    $entitlement = app(EntitlementService::class);
    expect($entitlement->canAccessItem($learner1, $next1)->reason)->toBe('sequence_locked');

    $a1 = app(StartQuizAttempt::class)($learner1, $item1);
    app(SubmitQuizAttempt::class)($learner1, $a1, []); // 0% fail
    $entitlement->flush();
    $prog1 = LessonProgress::query()->where('learner_id', $learner1->id)->where('curriculum_item_id', $item1->id)->first();
    expect($prog1?->status)->toBe(LessonProgress::COMPLETED)
        ->and($prog1?->completion_source)->toBe('quiz_submitted')
        ->and($entitlement->canAccessItem($learner1, $next1)->allowed)->toBeTrue();

    // 2) require_pass blocks next sequential item until learner passes
    ['course' => $course2, 'instructor' => $inst2, 'quiz' => $quiz2, 'quizItem' => $item2, 'nextItem' => $next2] = qrunCourseSetup([
        'pass_mark_percent' => 60,
        'progression' => 'require_pass',
    ]);
    $manage = app(ManageQuestions::class);
    $tf = $manage->add($inst2, $quiz2, ['type' => Question::TRUE_FALSE, 'prompt' => ['en' => 'Pass Gate Q'], 'points' => 10, 'true_is_correct' => true]);
    $learner2 = User::factory()->create(['status' => 'active'])->assignRole('student');
    app(GrantEntitlement::class)($learner2, $course2, EntitlementSource::Free);

    $entitlement->flush();
    expect($entitlement->canAccessItem($learner2, $next2)->reason)->toBe('sequence_locked');

    // Failed attempt -> next item stays sequence_locked
    $a2Fail = app(StartQuizAttempt::class)($learner2, $item2);
    app(SubmitQuizAttempt::class)($learner2, $a2Fail, []);
    $entitlement->flush();
    expect($entitlement->canAccessItem($learner2, $next2)->reason)->toBe('sequence_locked');

    // Retry and pass -> quiz completes with quiz_passed and unlocks next item
    $a2Pass = app(StartQuizAttempt::class)($learner2, $item2);
    $trueKey = collect($a2Pass->snapshot['questions'][0]['options'])->firstWhere('is_correct', true)['key'];
    app(SubmitQuizAttempt::class)($learner2, $a2Pass, [$tf->id => ['selected' => [$trueKey]]]);
    $prog2 = LessonProgress::query()->where('learner_id', $learner2->id)->where('curriculum_item_id', $item2->id)->first();
    expect($prog2?->status)->toBe(LessonProgress::COMPLETED)
        ->and($prog2?->completion_source)->toBe('quiz_passed')
        ->and($entitlement->canAccessItem($learner2, $next2)->allowed)->toBeTrue();
})->group('T-P6-02', 'A2', 'A4');
