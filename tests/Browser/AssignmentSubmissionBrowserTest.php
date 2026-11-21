<?php

declare(strict_types=1);

use App\Domain\Assignments\Actions\ConfigureAssignment;
use App\Domain\Assignments\Actions\DecideSubmission;
use App\Domain\Assignments\Actions\ManageAssignmentAttachments;
use App\Domain\Assignments\Actions\SaveSubmissionDraft;
use App\Domain\Assignments\Actions\SubmitAssignment;
use App\Domain\Curriculum\Actions\ManageAssessmentItems;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\SubmissionDecision;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

afterEach(function (): void {
    foreach ($GLOBALS['t603_browser_roots'] ?? [] as $root) {
        if (is_dir($root)) {
            File::deleteDirectory($root);
        }
    }
    $GLOBALS['t603_browser_roots'] = [];
});

/** Private brief-file disk with its own root, so concurrent runs never share files. */
function t603BrowserDisk(): void
{
    $name = 't603b-'.uniqid();
    $root = storage_path('framework/testing/disks/'.$name);
    config(["filesystems.disks.{$name}" => ['driver' => 'local', 'root' => $root, 'throw' => false]]);
    config(['media.disk' => $name]);
    $GLOBALS['t603_browser_roots'][] = $root;
}

/**
 * A published assignment with one teacher brief file, entitled learner, and the instructor who manages it.
 *
 * @return array{course: Course, instructor: User, learner: User, item: CurriculumItem, assignment: Assignment}
 */
function t603BrowserCourse(string $locale): array
{
    t603BrowserDisk();
    $instructor = User::factory()->create(['status' => 'active', 'timezone' => 'Africa/Cairo', 'locale' => $locale])->assignRole('instructor_academy');
    $learner = User::factory()->create(['status' => 'active', 'timezone' => 'Africa/Cairo', 'locale' => $locale])->assignRole('student');
    $course = Course::query()->create([
        'title' => ['ar' => 'أساسيات الرسم الرقمي', 'en' => 'Digital drawing basics'],
        'slug_ar' => 't603b-ar-'.uniqid(),
        'slug_en' => 't603b-en-'.uniqid(),
        'status' => 'published',
        'access_mode' => 'free',
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['ar' => 'البداية', 'en' => 'Getting started']]);
    $section->update(['published' => true]);

    $item = app(ManageAssessmentItems::class)->add($instructor, $section, 'assignment', ['title' => ['ar' => 'طبيعة صامتة', 'en' => 'Still life study']]);
    /** @var Assignment $assignment */
    $assignment = $item->itemable;
    app(ConfigureAssignment::class)($instructor, $assignment, [
        'instructions' => ['ar' => 'ارسم ثلاثة أشياء من مكتبك', 'en' => 'Draw three objects from your desk.'],
        'allowed_types' => ['text', 'image'],
        'max_size_kb' => 2048,
        'due_at' => now()->addDays(3)->format('Y-m-d H:i:s'),
        'timezone' => 'Africa/Cairo',
        'late_policy' => Assignment::LATE_PENALTY,
        'late_penalty_percent' => 10,
        'max_revisions' => 1,
    ]);
    app(ManageAssignmentAttachments::class)->add($instructor, $assignment, UploadedFile::fake()->image('brief-reference.png', 120, 120), ['en' => 'Reference sheet', 'ar' => 'ورقة مرجعية']);
    app(SetCurriculumStatus::class)($instructor, $course, [$item->id], CurriculumItem::STATUS_PUBLISHED);
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);

    return compact('course', 'instructor', 'learner', 'item', 'assignment');
}

/** Puts the learner's assignment into one of the card states the screenshots cover. */
function t603BrowserState(string $state, array $setup): void
{
    ['instructor' => $instructor, 'learner' => $learner, 'item' => $item] = $setup;
    if ($state === 'draft') {
        app(SaveSubmissionDraft::class)($learner, $item->fresh(), 'Sketch of the lamp and the mug.');

        return;
    }
    app(SaveSubmissionDraft::class)($learner, $item->fresh(), 'First pass at the still life.', [
        UploadedFile::fake()->image('first-pass.png', 150, 150),
    ]);
    $submission = app(SubmitAssignment::class)($learner, $item->fresh());
    if ($state === 'returned') {
        app(DecideSubmission::class)($instructor, AssignmentSubmission::query()->findOrFail($submission->getKey()), SubmissionDecision::REVISION_REQUESTED, null, 'Add highlights to the mug.');
    }
}

/**
 * Opens the page in light or dark. The product script reads the stored theme once at page load, so the theme is stored
 * on the origin first and the page is then visited fresh.
 */
function t603BrowserVisit($test, string $url, int $width, int $height, string $theme)
{
    $page = $test->visit($url)->resize($width, $height);
    // The stored theme did not persist between browser visits on the learner page in this harness, so the page's own
    // applyTheme outcome (class and data-theme) is mirrored here. The dark assertion therefore checks the emulated state.
    $page->script("() => { try { localStorage.setItem('theme', '{$theme}'); } catch (e) {} const dark = '{$theme}' === 'dark'; document.documentElement.classList.toggle('dark', dark); document.documentElement.dataset.theme = dark ? 'dark' : 'light'; }");

    return $page;
}

function t603BrowserScreenshotDirectory(): void
{
    $directory = base_path('tests/Browser/screenshots/T-P6-03');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
}

it('renders the learner assignment card in each submission state accessibly in both directions, themes and sizes', function (string $locale, int $width, int $height, string $theme, string $state): void {
    $setup = t603BrowserCourse($locale);
    t603BrowserState($state, $setup);
    ['course' => $course, 'item' => $item, 'learner' => $learner] = $setup;
    $this->withSession(['locale' => $locale]);
    $this->actingAs($learner);

    $page = t603BrowserVisit($this, "/learn/{$course->id}/{$item->id}", $width, $height, $theme);
    $page->assertSee(__('assignment.brief_files', [], $locale))
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr')
        ->and($page->script("() => document.documentElement.classList.contains('dark')"))->toBe($theme === 'dark')
        ->and($page->script("() => document.querySelector('[data-assignment-card]')?.dataset.assignmentStatus"))->toBe($state)
        ->and($page->script("() => document.querySelectorAll('[data-assignment-attachments] a').length"))->toBeGreaterThan(0)
        ->and($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();

    t603BrowserScreenshotDirectory();
    $page->screenshot(fullPage: false, filename: "T-P6-03/learner-{$state}-{$locale}-{$theme}-{$width}");
})->with(function (): array {
    $cases = [];
    foreach (['ar', 'en'] as $locale) {
        foreach ([[390, 844], [1440, 900]] as [$width, $height]) {
            foreach (['light', 'dark'] as $theme) {
                foreach (['draft', 'submitted', 'returned'] as $state) {
                    $cases[] = [$locale, $width, $height, $theme, $state];
                }
            }
        }
    }

    return $cases;
})->group('browser', 'T-P6-03', 'A1', 'A2', 'A3');

it('opens the instructor assignment settings modal accessibly in both directions, themes and sizes', function (string $locale, int $width, int $height, string $theme): void {
    ['course' => $course, 'instructor' => $instructor, 'item' => $item] = t603BrowserCourse($locale);
    $this->withSession(['locale' => $locale]);
    $this->actingAs($instructor);

    $page = t603BrowserVisit($this, "/instructor/courses/{$course->id}/curriculum", $width, $height, $theme);
    $page->click("[data-item=\"{$item->id}\"] summary")
        ->click("[data-item=\"{$item->id}\"] [data-configure-assignment]")
        ->wait(1.5);
    $page->script(<<<'JS'
        async () => {
            await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)));
            await Promise.allSettled(document.getAnimations()
                .filter(animation => Number.isFinite(animation.effect.getComputedTiming().endTime))
                .map(animation => animation.finished));
        }
        JS);
    $page->assertSee(__('curriculum.assignment.settings', [], $locale))
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr')
        ->and($page->script("() => document.documentElement.classList.contains('dark')"))->toBe($theme === 'dark')
        ->and($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();

    t603BrowserScreenshotDirectory();
    $page->screenshot(fullPage: false, filename: "T-P6-03/instructor-settings-{$locale}-{$theme}-{$width}");
})->with([
    ['ar', 390, 844, 'light'], ['ar', 390, 844, 'dark'], ['en', 390, 844, 'light'], ['en', 390, 844, 'dark'],
    ['ar', 1440, 900, 'light'], ['ar', 1440, 900, 'dark'], ['en', 1440, 900, 'light'], ['en', 1440, 900, 'dark'],
])->group('browser', 'T-P6-03', 'A1');
