<?php

declare(strict_types=1);

use App\Domain\Assessment\Actions\ConfigureQuiz;
use App\Domain\Assessment\Actions\ManageQuestions;
use App\Domain\Curriculum\Actions\ManageAssessmentItems;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders assessment and certificate staff controls in both locales and themes', function (string $surface, string $locale, string $theme, int $width, int $height): void {
    $this->seed(RolePermissionSeeder::class);
    $actor = User::factory()->create(['locale' => $locale])->assignRole($surface === 'certificate-templates' ? 'super_admin' : 'instructor_academy');
    $course = Course::query()->create([
        'title' => ['ar' => 'أساسيات الرسم الرقمي', 'en' => 'Digital Drawing Basics'],
        'slug_ar' => 'assessment-ar', 'slug_en' => 'assessment-en', 'status' => 'published',
    ]);
    $course->instructors()->attach($actor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($actor, $course, ['title' => ['ar' => 'الضوء والظل', 'en' => 'Light and shadow']]);
    app(ManageLessons::class)->add($actor, $section, ['title' => ['ar' => 'فهم مصادر الضوء', 'en' => 'Understanding light sources']]);

    if ($surface === 'release-quiz') {
        $quiz = app(ManageAssessmentItems::class)->add($actor, $section, 'quiz', ['title' => ['ar' => 'اختبار الألوان', 'en' => 'Colour theory quiz']])->itemable;
        $attempt = QuizAttempt::query()->create([
            'quiz_id' => $quiz->id, 'learner_id' => User::factory()->create(['name' => 'Nour'])->id, 'attempt_no' => 1,
            'status' => QuizAttempt::SUBMITTED, 'started_at' => now()->subMinutes(5), 'submitted_at' => now(),
            'snapshot' => ['questions' => []], 'settings' => ['result_visibility' => 'hidden'],
            'score' => 8, 'total' => 10, 'percent' => 80, 'passed' => true,
        ]);
    }

    [$url, $action, $task] = match ($surface) {
        'add-assessment' => ["/instructor/courses/{$course->id}/curriculum", 'curriculum.add_assessment', 'T-P6-01'],
        'release-quiz' => ["/instructor/essay-review/{$attempt->id}", 'review.release_result', 'T-P6-04'],
        'certificate-templates' => ["/admin/courses/{$course->id}/completion", 'certificates.edit_templates', 'T-P6-06'],
    };

    $this->withSession(['locale' => $locale]);
    $this->actingAs($actor);
    $page = $this->visit($url)->resize($width, $height);
    $page->script("() => document.documentElement.classList.toggle('dark', '{$theme}' === 'dark')");
    $page->click(__($action, [], $locale))->wait(0.8)->assertVisible('.fi-modal-window:visible');
    $page->script('async () => { await document.fonts.ready; }');
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');
    expect($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
    expect($page->script('() => document.documentElement.classList.contains("dark")'))->toBe($theme === 'dark');
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();

    $directory = base_path('tests/Browser/screenshots/'.$task);
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: false, filename: "../screenshots/{$task}/{$surface}-{$locale}-{$theme}-{$width}");
})->with(['add-assessment', 'release-quiz', 'certificate-templates'])
    ->with(['ar', 'en'])->with(['light', 'dark'])->with([[390, 844], [1440, 900]])
    ->group('T-P6-01', 'T-P6-04', 'T-P6-06')
    ->group('browser');

it('takes a timed quiz in the browser across RTL/LTR, themes, and viewports', function (string $locale, string $theme, int $width, int $height): void {
    $this->seed(RolePermissionSeeder::class);
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة متصفح', 'en' => 'Browser Course'],
        'slug_ar' => 'qb-ar-'.uniqid(),
        'slug_en' => 'qb-en-'.uniqid(),
        'status' => 'published',
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['ar' => 'قسم 1', 'en' => 'Section 1']]);
    $section->update(['published' => true]);
    $item = app(ManageAssessmentItems::class)->add($instructor, $section, 'quiz', ['title' => ['ar' => 'اختبار متصفح', 'en' => 'Browser Quiz']]);
    /** @var Quiz $quiz */
    $quiz = $item->itemable;
    app(ConfigureQuiz::class)($instructor, $quiz, [
        'time_limit_minutes' => 10,
        'pass_mark_percent' => 50,
        'max_attempts' => 2,
        'result_visibility' => 'full',
        'randomize' => false,
        'progression' => 'after_submission',
    ]);
    app(ManageQuestions::class)->add($instructor, $quiz, [
        'type' => Question::TRUE_FALSE,
        'prompt' => ['ar' => 'اللون الأزرق أساسي', 'en' => 'Blue is primary'],
        'points' => 2,
        'true_is_correct' => true,
    ]);
    app(SetCurriculumStatus::class)($instructor, $course, [$item->id], CurriculumItem::STATUS_PUBLISHED);

    $learner = User::factory()->create(['status' => 'active', 'locale' => $locale])->assignRole('student');
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);

    $this->actingAs($learner);
    $page = $this->visit(route('learn.show', ['course' => $course->id, 'item' => $item->id]))->resize($width, $height);
    $page->click('[data-quiz-start]')->assertVisible('[data-quiz-form]');
    $page->assertVisible('[data-quiz-timer]');
    $page->script("() => document.documentElement.classList.toggle('dark', '{$theme}' === 'dark')");
    $page->script('async () => { await document.fonts.ready; }');

    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr')
        ->and($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue()
        ->and($page->script('() => document.documentElement.classList.contains("dark")'))->toBe($theme === 'dark');

    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();

    $directory = base_path('tests/Browser/screenshots/T-P6-02');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: false, filename: "../screenshots/T-P6-02/quiz-attempt-{$locale}-{$theme}-{$width}");

    $page->click('[data-answer-input][value="1"]');
    $page->click('[data-finish]')->assertVisible('[data-finish-confirm]');
    $page->click('[data-submit]')->assertVisible('[data-quiz-result]');
    $page->assertNoJavaScriptErrors();
})->with(['ar', 'en'])->with(['light', 'dark'])->with([[390, 844], [1440, 900]])
    ->group('T-P6-02', 'A1', 'A4')
    ->group('browser');
