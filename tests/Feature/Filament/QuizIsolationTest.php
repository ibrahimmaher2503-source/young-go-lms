<?php

declare(strict_types=1);

use App\Domain\Assessment\Actions\ConfigureQuiz;
use App\Domain\Assessment\Actions\ManageQuestions;
use App\Domain\Assessment\Actions\ReleaseQuizResult;
use App\Domain\Assessment\Actions\SaveQuizAnswers;
use App\Domain\Assessment\Queries\QuizAttemptView;
use App\Domain\Assessment\QuizRefusedException;
use App\Domain\Curriculum\Actions\ManageAssessmentItems;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Filament\Resources\Courses\Pages\ManageQuiz;
use App\Models\Course;
use App\Models\QuizAttempt;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    $this->quizzes = [];

    foreach (['Own', 'Foreign'] as $index => $label) {
        $instructor = User::factory()->create()->assignRole('instructor_academy');
        $course = Course::query()->create(['title' => ['en' => $label.' course'], 'slug_ar' => 'isolation-ar-'.$index, 'slug_en' => 'isolation-en-'.$index]);
        $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
        $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Section']]);
        $item = app(ManageAssessmentItems::class)->add($instructor, $section, 'quiz', ['title' => ['en' => $label.' quiz']]);
        $quiz = $item->itemable;
        app(ManageQuestions::class)->add($instructor, $quiz, [
            'type' => 'single', 'prompt' => ['en' => $label.' private prompt'], 'points' => 1,
            'options' => [
                ['text' => ['en' => $label.' correct key'], 'is_correct' => true],
                ['text' => ['en' => 'Wrong'], 'is_correct' => false],
            ],
        ]);
        $this->quizzes[] = [$instructor, $course, $quiz];
    }
    $this->actingAs($this->quizzes[0][0]);
});

it('renders assigned quiz questions and keeps normal authoring available', function (): void {
    [, $course, $quiz] = $this->quizzes[0];
    Livewire::test(ManageQuiz::class, ['record' => $course->id, 'quiz' => $quiz->id])
        ->assertSuccessful()->assertSee('Own private prompt')->assertSee('Own correct key')
        ->assertDontSee('Foreign private prompt')->assertDontSee('Foreign correct key')
        ->callAction('settings', ['time_limit_minutes' => 12, 'result_visibility' => 'score_only', 'progression' => 'after_submission'])
        ->assertHasNoActionErrors();

    expect($quiz->refresh()->time_limit_minutes)->toBe(12);
})->group('T-P6-01', 'A2', 'PRD-12', 'ED-005');

it('rejects a quiz from another course on initial mount', function (): void {
    [, $course] = $this->quizzes[0];
    [, , $foreignQuiz] = $this->quizzes[1];
    Livewire::test(ManageQuiz::class, ['record' => $course->id, 'quiz' => $foreignQuiz->id])->assertNotFound();
})->group('T-P6-01', 'A2', 'PRD-12', 'ED-005');

it('rejects client changes to the quiz identity', function (): void {
    [, $course, $quiz] = $this->quizzes[0];
    [, , $foreignQuiz] = $this->quizzes[1];
    $page = Livewire::test(ManageQuiz::class, ['record' => $course->id, 'quiz' => $quiz->id])
        ->assertSuccessful()->assertDontSee('Foreign private prompt');

    $this->withoutExceptionHandling();
    expect(fn () => $page->set('quizId', $foreignQuiz->id))->toThrow(CannotUpdateLockedPropertyException::class);
})->group('T-P6-01', 'A2', 'PRD-12', 'ED-005');

it('refuses another read when course management scope is revoked after mounting', function (): void {
    [$instructor, $course, $quiz] = $this->quizzes[0];
    $page = Livewire::test(ManageQuiz::class, ['record' => $course->id, 'quiz' => $quiz->id])
        ->assertSuccessful()->assertSee('Own correct key');

    $course->instructors()->updateExistingPivot($instructor->id, ['scope' => 'view']);
    $page->call('$refresh')->assertForbidden();
})->group('T-P6-01', 'A2', 'PRD-12', 'ED-005');

it('rolls back quiz settings if audit recording is unavailable', function (): void {
    [$instructor, , $quiz] = $this->quizzes[0];
    activity()->disableLogging();
    try {
        expect(fn () => app(ConfigureQuiz::class)($instructor, $quiz, [
            'time_limit_minutes' => 12, 'result_visibility' => 'score_only', 'progression' => 'after_submission',
        ]))->toThrow(RuntimeException::class);
    } finally {
        activity()->enableLogging();
    }

    expect($quiz->refresh()->time_limit_minutes)->toBeNull();
})->group('T-P6-01', 'A2', 'PRD-12', 'ED-005');

it('shows an expired quiz without submitting it on GET', function (): void {
    [, $course, $quiz] = $this->quizzes[0];
    $course->update(['status' => 'published', 'access_mode' => 'free']);
    $item = $quiz->item()->firstOrFail();
    $item->section->update(['published' => true]);
    $item->update(['status' => 'published']);
    $learner = User::factory()->create()->assignRole('student');
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);
    $attempt = QuizAttempt::query()->create([
        'quiz_id' => $quiz->id, 'learner_id' => $learner->id, 'attempt_no' => 1,
        'status' => QuizAttempt::IN_PROGRESS, 'started_at' => now()->subHour(),
        'deadline_at' => now()->subMinutes(2), 'snapshot' => ['questions' => []], 'settings' => [],
    ]);

    $this->actingAs($learner)->get(route('quiz.attempt', ['course' => $course, 'item' => $item, 'attempt' => $attempt]))
        ->assertOk()->assertViewHas('expired', true);

    expect($attempt->refresh()->isOpen())->toBeTrue()->and($attempt->submitted_at)->toBeNull();
    $this->artisan('quiz:expire-attempts')->assertSuccessful();
    expect($attempt->refresh()->submitted_by)->toBe('auto');
    expect(fn () => app(SaveQuizAnswers::class)($learner, $attempt, []))
        ->toThrow(QuizRefusedException::class);
})->group('T-P6-02', 'A1', 'PRD-12', 'ED-005');

it('publishes a finished hidden result only when a scoped reviewer releases it', function (): void {
    [$instructor, , $quiz] = $this->quizzes[0];
    [$other] = $this->quizzes[1];
    $attempt = QuizAttempt::query()->create([
        'quiz_id' => $quiz->id, 'learner_id' => User::factory()->create()->id, 'attempt_no' => 1,
        'status' => QuizAttempt::SUBMITTED, 'started_at' => now(), 'submitted_at' => now(),
        'snapshot' => ['questions' => []], 'settings' => ['result_visibility' => 'hidden'],
    ]);
    $release = app(ReleaseQuizResult::class);

    expect(QuizAttemptView::result($attempt)['visible'])->toBeFalse();
    expect(fn () => $release($other, $attempt))->toThrow(AuthorizationException::class);
    $release($instructor, $attempt);
    expect(QuizAttemptView::result($attempt->refresh())['visible'])->toBeTrue();
})->group('T-P6-04', 'PRD-12');
