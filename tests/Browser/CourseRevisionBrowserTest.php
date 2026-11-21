<?php

// T-P3-07 | PRD 29.5 YG-GAP-CHG-001..003 | A1 A2 A3 | browser

declare(strict_types=1);

use App\Domain\Catalog\CourseStatus;
use App\Domain\Catalog\Revisions\Actions\OpenCourseRevision;
use App\Domain\Catalog\Revisions\Actions\SaveCourseRevision;
use App\Domain\Catalog\Revisions\Actions\SubmitCourseRevision;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    config(['markets.EG.active' => true]);
});

/** A published independent course with a pending revision that renames it and adds a lesson, for the screens under test. */
function crbPendingCourse(string $locale, bool $submit = true): array
{
    $instructor = User::factory()->create(['locale' => $locale, 'name' => 'Instructor', 'status' => 'active'])->assignRole('instructor_independent');
    $admin = User::factory()->create(['locale' => $locale, 'name' => 'Reviewer', 'status' => 'active'])->assignRole('admin');
    $category = Category::query()->create(['name' => ['en' => 'Arts', 'ar' => 'فنون'], 'slug_ar' => 'crb-ar-'.uniqid(), 'slug_en' => 'crb-en-'.uniqid(), 'sort' => 1, 'status' => 'active']);
    $course = Course::query()->create([
        'title' => ['ar' => 'أساسيات الرسم الرقمي', 'en' => 'Digital drawing basics'], 'slug_ar' => 'crb-c-ar-'.uniqid(), 'slug_en' => 'crb-c-en-'.uniqid(),
        'status' => CourseStatus::Published, 'category_id' => $category->id, 'level' => 'beginner', 'content_language' => 'en',
        'format' => 'recorded', 'access_mode' => Course::ACCESS_FREE, 'owner_type' => Course::OWNER_INDEPENDENT, 'owner_instructor_id' => $instructor->id,
    ]);
    $course->instructors()->attach($instructor, ['role' => 'owner', 'scope' => Course::INSTRUCTOR_SCOPE_MANAGE]);
    $section = CourseSection::query()->create(['course_id' => $course->id, 'published' => true, 'sort' => 1, 'title' => ['ar' => 'البداية', 'en' => 'Getting started']]);
    $lesson = Lesson::query()->create(['title' => ['ar' => 'التعرف على الأدوات', 'en' => 'Know your tools'], 'type' => 'text', 'is_preview' => false]);
    $lesson->item()->create(['course_id' => $course->id, 'section_id' => $section->id, 'sort' => 1, 'status' => 'published']);

    $revision = app(OpenCourseRevision::class)($instructor, $course);
    $input = ['course' => $revision->base['course'], 'sections' => $revision->base['sections']];
    $input['course']['title'] = ['ar' => 'الرسم الرقمي المتقدم', 'en' => 'Advanced digital drawing'];
    app(SaveCourseRevision::class)($instructor, $revision, $input);
    $pending = $submit ? app(SubmitCourseRevision::class)($instructor, $revision->fresh()) : $revision;

    return [$course, $instructor, $admin, $pending];
}

function crbScreenshotDirectory(): void
{
    $directory = base_path('tests/Browser/screenshots/T-P3-07');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
}

function crbApplyTheme(string $theme): string
{
    return "async () => {
        localStorage.setItem('theme', '{$theme}');
        window.dispatchEvent(new CustomEvent('theme-changed', {detail: '{$theme}'}));
        await document.fonts.ready;
    }";
}

function crbWorkspaceCase(object $test, string $locale, int $width, int $height, string $theme): void
{
    [$course, $instructor] = crbPendingCourse($locale);
    $test->actingAs($instructor);

    $page = $test->visit("/instructor/courses/{$course->id}/revision")->resize($width, $height);
    $page->script(crbApplyTheme($theme));
    $page->assertSee(__('course_revisions.state.pending', [], $locale))
        ->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');
    expect($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();

    crbScreenshotDirectory();
    $page->screenshot(fullPage: true, filename: "T-P3-07/revision-workspace-{$locale}-{$width}-{$theme}");
}

function crbEditorCase(object $test, string $locale, int $width, int $height, string $theme): void
{
    [$course, $instructor] = crbPendingCourse($locale, submit: false);
    $test->actingAs($instructor);

    $page = $test->visit("/instructor/courses/{$course->id}/revision")->resize($width, $height);
    $page->script(crbApplyTheme($theme));
    $page->click(__('course_revisions.edit', [], $locale))
        ->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');
    expect($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();

    crbScreenshotDirectory();
    $page->screenshot(fullPage: true, filename: "T-P3-07/revision-editor-{$locale}-{$width}-{$theme}");
}

function crbQueueCase(object $test, string $locale, int $width, int $height, string $theme): void
{
    [, , $admin] = crbPendingCourse($locale);
    $test->actingAs($admin);

    $more = __('components.more', [], $locale);
    $compare = __('course_revisions.compare', [], $locale);

    $page = $test->visit('/admin/course-revisions')->resize($width, $height);
    $page->script(crbApplyTheme($theme));
    $page->assertSee(__('course_revisions.queue_title', [], $locale))
        ->click("[aria-label=\"{$more}\"], [title=\"{$more}\"]")
        ->click($compare);
    $page->script('async () => { await new Promise((resolve) => setTimeout(resolve, 800)); }');
    $page->assertSee($compare)
        ->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');
    expect($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();

    crbScreenshotDirectory();
    $page->screenshot(fullPage: true, filename: "T-P3-07/review-queue-compare-{$locale}-{$width}-{$theme}");
}

// ---------- both viewports ----------

it('renders the revision workspace with the open revision, compare summary and history accessibly at desktop and phone width', function (string $locale, int $width, int $height, string $theme): void {
    crbWorkspaceCase($this, $locale, $width, $height, $theme);
})->with([
    ['ar', 1440, 900, 'light'], ['ar', 1440, 900, 'dark'], ['en', 1440, 900, 'light'], ['en', 1440, 900, 'dark'],
    ['ar', 390, 844, 'light'], ['ar', 390, 844, 'dark'], ['en', 390, 844, 'light'], ['en', 390, 844, 'dark'],
])->group('browser', 'T-P3-07', 'A1', 'A2', 'A3', 'YG-GAP-CHG-001');

it('opens the revision editor slide-over accessibly at desktop and phone width', function (string $locale, int $width, int $height, string $theme): void {
    crbEditorCase($this, $locale, $width, $height, $theme);
})->with([
    ['ar', 1440, 900, 'light'], ['ar', 1440, 900, 'dark'], ['en', 1440, 900, 'light'], ['en', 1440, 900, 'dark'],
    ['ar', 390, 844, 'light'], ['ar', 390, 844, 'dark'], ['en', 390, 844, 'light'], ['en', 390, 844, 'dark'],
])->group('browser', 'T-P3-07', 'A1', 'A2', 'A3', 'YG-GAP-CHG-001');

it('shows the review queue with the compare modal open to a reviewer in both directions', function (string $locale, int $width, int $height, string $theme): void {
    crbQueueCase($this, $locale, $width, $height, $theme);
})->with([
    ['ar', 1440, 900, 'light'], ['ar', 1440, 900, 'dark'], ['en', 1440, 900, 'light'], ['en', 1440, 900, 'dark'],
    ['ar', 390, 844, 'light'], ['ar', 390, 844, 'dark'], ['en', 390, 844, 'light'], ['en', 390, 844, 'dark'],
])->group('browser', 'T-P3-07', 'A2', 'A3', 'YG-GAP-CHG-003');
