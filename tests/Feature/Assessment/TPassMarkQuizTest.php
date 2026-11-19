<?php

declare(strict_types=1);

// T-PASSMARK-RECIPIENTS | O-020 | A (course-level quiz pass mark)

use App\Domain\Assessment\Actions\ConfigureQuiz;
use App\Domain\Assessment\QuizPassMark;
use App\Domain\Assessment\QuizSnapshot;
use App\Domain\Completion\Actions\SaveCompletionRules;
use App\Domain\Curriculum\Actions\ManageAssessmentItems;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\Quiz;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    config(['assessment.default_pass_mark_percent' => 60]);
});

function tpmCourse(?int $courseMark = null): Course
{
    return Course::query()->create([
        'title' => ['ar' => 'دورة', 'en' => 'Pass mark course '.uniqid()],
        'slug_ar' => 'tpm-ar-'.uniqid(),
        'slug_en' => 'tpm-en-'.uniqid(),
        'status' => 'published',
        'quiz_pass_mark_percent' => $courseMark,
    ]);
}

/** A course with one section and a quiz item whose explicit mark is $quizMark. @return array{course: Course, quiz: Quiz, item: CurriculumItem} */
function tpmQuizIn(?int $courseMark, ?int $quizMark): array
{
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $course = tpmCourse($courseMark);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['ar' => 'القسم', 'en' => 'Section']]);
    $item = app(ManageAssessmentItems::class)->add($instructor, $section, 'quiz', ['title' => ['ar' => 'اختبار', 'en' => 'Quiz']]);
    /** @var Quiz $quiz */
    $quiz = $item->itemable;
    app(ConfigureQuiz::class)($instructor, $quiz, [
        'time_limit_minutes' => 10, 'pass_mark_percent' => $quizMark, 'max_attempts' => 3,
        'result_visibility' => 'full', 'randomize' => false, 'progression' => 'after_submission',
    ]);

    return ['course' => $course->refresh(), 'quiz' => $quiz->refresh(), 'item' => $item];
}

it('uses 60 with no course mark and the course mark when set, and the explicit mark wins', function (): void {
    expect(QuizPassMark::effective(null))->toBe(60)
        ->and(QuizPassMark::effective(null, 75))->toBe(75)
        ->and(QuizPassMark::effective(80, 75))->toBe(80);
})->group('T-PASSMARK-RECIPIENTS', 'A');

it('falls back to the platform default for out-of-range course marks', function (int $bad): void {
    expect(QuizPassMark::effective(null, $bad))->toBe(60);
})->with([0, 101, -5])->group('T-PASSMARK-RECIPIENTS', 'A');

it('falls through an out-of-range explicit mark to the course mark, then the default', function (): void {
    expect(QuizPassMark::effective(0, 70))->toBe(70)
        ->and(QuizPassMark::effective(101, null))->toBe(60)
        ->and(QuizPassMark::effective(-1, 0))->toBe(60);
})->group('T-PASSMARK-RECIPIENTS', 'A');

it('resolves the quiz mark: quiz value, else course value, else platform default', function (): void {
    $inherits = tpmQuizIn(70, null);
    $explicit = tpmQuizIn(70, 90);
    $noCourse = tpmQuizIn(null, null);

    expect(QuizPassMark::forQuiz($inherits['quiz']))->toBe(70)
        ->and(QuizPassMark::forQuiz($explicit['quiz']))->toBe(90)
        ->and(QuizPassMark::forQuiz($noCourse['quiz']))->toBe(60);

    config(['assessment.default_pass_mark_percent' => 55]);
    expect(QuizPassMark::forQuiz($noCourse['quiz']))->toBe(55);
})->group('T-PASSMARK-RECIPIENTS', 'A');

it('gives the platform default for a quiz with no curriculum item', function (): void {
    $quiz = new Quiz(['pass_mark_percent' => null]);

    expect(QuizPassMark::forQuiz($quiz))->toBe(60);
})->group('T-PASSMARK-RECIPIENTS', 'A');

it('freezes the course mark into the attempt settings at the time it is read', function (): void {
    $setup = tpmQuizIn(70, null);

    expect(QuizSnapshot::settings($setup['quiz'])['pass_mark_percent'])->toBe(70);

    $setup['course']->forceFill(['quiz_pass_mark_percent' => 50])->save();

    expect(QuizSnapshot::settings($setup['quiz']->refresh())['pass_mark_percent'])->toBe(50);
})->group('T-PASSMARK-RECIPIENTS', 'A');

it('saves the course pass mark through completion rules, clears on blank, and rejects bad values', function (): void {
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $course = tpmCourse();
    $action = app(SaveCompletionRules::class);

    $action($admin, $course, ['quiz_pass_mark_percent' => 70]);
    expect($course->refresh()->quiz_pass_mark_percent)->toBe(70);

    $action($admin, $course, ['quiz_pass_mark_percent' => '']);
    expect($course->refresh()->quiz_pass_mark_percent)->toBeNull();

    foreach ([0, 101, 'abc'] as $bad) {
        expect(fn () => $action($admin, $course, ['quiz_pass_mark_percent' => $bad]))->toThrow(ValidationException::class);
    }
})->group('T-PASSMARK-RECIPIENTS', 'A');

it('leaves the course mark untouched when the key is absent', function (): void {
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $course = tpmCourse(65);

    app(SaveCompletionRules::class)($admin, $course, ['lesson_percent' => 50]);

    expect($course->refresh()->quiz_pass_mark_percent)->toBe(65);
})->group('T-PASSMARK-RECIPIENTS', 'A');

it('refuses a learner with AuthorizationException', function (): void {
    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');
    $course = tpmCourse();

    expect(fn () => app(SaveCompletionRules::class)($learner, $course, ['quiz_pass_mark_percent' => 70]))
        ->toThrow(AuthorizationException::class);
    expect($course->refresh()->quiz_pass_mark_percent)->toBeNull();
})->group('T-PASSMARK-RECIPIENTS', 'A');

it('audits the pass mark change with before and after only when it changes, and keeps the completion-rules payload unchanged', function (): void {
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $course = tpmCourse(60);
    $action = app(SaveCompletionRules::class);

    $action($admin, $course, ['quiz_pass_mark_percent' => 60]);
    expect(Activity::query()->where('event', 'course.quiz_pass_mark_saved')->count())->toBe(0);

    $action($admin, $course, ['quiz_pass_mark_percent' => 75]);
    $audit = Activity::query()->where('event', 'course.quiz_pass_mark_saved')->latest('id')->firstOrFail();
    expect($audit->causer_id)->toBe($admin->id)
        ->and($audit->properties['after']['quiz_pass_mark_percent'] ?? null)->toBe(75)
        ->and($audit->properties['before']['quiz_pass_mark_percent'] ?? null)->toBe(60);

    $rules = Activity::query()->where('event', 'course.completion_rules_saved')->latest('id')->first();
    expect($rules)->not->toBeNull()
        ->and(array_key_exists('quiz_pass_mark_percent', [...($rules->properties['before'] ?? []), ...($rules->properties['after'] ?? [])]))->toBeFalse();
})->group('T-PASSMARK-RECIPIENTS', 'A');
