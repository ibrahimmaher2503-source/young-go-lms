<?php

// T-P4-10 | YG-GAP-INS-004, YG-GAP-LRN-005, ED-024 | A3

declare(strict_types=1);

use App\Domain\Curriculum\Actions\ManageAssessmentItems;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Media\Actions\AttachLessonMedia;
use App\Domain\Progress\Actions\RecordProgress;
use App\Domain\Progress\LessonProgress;
use App\Domain\Settings\PlatformFlag;
use App\Models\AiUsage;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\LessonCurriculumDocument;
use App\Models\QuizAttempt;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Laravel\Pennant\Feature;
use Tests\Support\PdfFixture;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    File::ensureDirectoryExists(base_path('tests/Browser/screenshots/T-P4-10'));
});

function tp410BrowserCourse(User $instructor, string $slug): Course
{
    $course = Course::query()->create([
        'title' => ['ar' => 'أساسيات الرسم الرقمي', 'en' => 'Digital drawing basics'],
        'slug_ar' => $slug, 'slug_en' => $slug, 'status' => 'published', 'access_mode' => 'paid',
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);

    return $course;
}

$tp410Viewports = [
    ['ar', 'light', 390, 844], ['ar', 'dark', 390, 844],
    ['en', 'light', 390, 844], ['en', 'dark', 390, 844],
    ['ar', 'light', 1440, 900], ['ar', 'dark', 1440, 900],
    ['en', 'light', 1440, 900], ['en', 'dark', 1440, 900],
    ['ar', 'light', 1568, 1003],
];

it('renders the native instructor learner roster with real progress and assessment results', function (string $locale, string $theme, int $width, int $height): void {
    $instructor = User::factory()->create(['locale' => $locale, 'name' => $locale === 'ar' ? 'سارة أحمد' : 'Sara Ahmed'])->assignRole('instructor_academy');
    $otherInstructor = User::factory()->create()->assignRole('instructor_academy');
    $course = tp410BrowserCourse($instructor, 'digital-roster');
    $outside = tp410BrowserCourse($otherInstructor, 'outside-roster');
    $names = $locale === 'ar' ? ['نور علي', 'عمر حسن', 'ياسمين خالد'] : ['Nour Ali', 'Omar Hassan', 'Yasmin Khaled'];
    $learners = collect($names)->map(fn (string $name): User => User::factory()->create(['name' => $name])->assignRole('student'));
    $learner = $learners->first();
    $otherLearner = User::factory()->create(['name' => 'Outside course learner'])->assignRole('student');
    foreach ($learners as $enrolled) {
        app(GrantEntitlement::class)($enrolled, $course, EntitlementSource::Free);
    }
    app(GrantEntitlement::class)($otherLearner, $outside, EntitlementSource::Free);

    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['ar' => 'البداية', 'en' => 'Getting started']]);
    $section->update(['published' => true]);
    $lesson = app(ManageLessons::class)->add($instructor, $section, ['title' => ['ar' => 'الضوء والظل', 'en' => 'Light and shadow'], 'type' => 'text']);
    $quiz = app(ManageAssessmentItems::class)->add($instructor, $section, 'quiz', ['title' => ['ar' => 'اختبار الرسم', 'en' => 'Drawing quiz']]);
    $assignment = app(ManageAssessmentItems::class)->add($instructor, $section, 'assignment', ['title' => ['ar' => 'تمرين التظليل', 'en' => 'Shading exercise']]);
    app(SetCurriculumStatus::class)($instructor, $course, [$lesson->id, $quiz->id, $assignment->id], 'published');
    app(RecordProgress::class)->markComplete($learner, $lesson->refresh());
    QuizAttempt::query()->create([
        'quiz_id' => $quiz->itemable_id, 'learner_id' => $learner->id, 'attempt_no' => 1, 'status' => 'submitted',
        'started_at' => now(), 'submitted_at' => now(), 'snapshot' => [], 'settings' => [], 'passed' => true, 'total' => 10,
    ]);
    AssignmentSubmission::query()->create([
        'assignment_id' => $assignment->itemable_id, 'learner_id' => $learner->id, 'status' => 'accepted',
    ]);

    $this->actingAs($instructor)->withSession(['locale' => $locale]);
    $page = $this->visit("/instructor/courses/{$course->id}/learners")->resize($width, $height);
    $page->script("() => localStorage.setItem('filament:theme:mode', '{$theme}')");
    $page->refresh();
    $page->script('async () => { await document.fonts.ready; }');

    $page->assertVisible('.fi-ta-table')
        ->assertSee($names[0])->assertSee($names[1])->assertSee($names[2])->assertDontSee('Outside course learner')
        ->assertSee(__('courses.last_activity', [], $locale))->assertSee(__('courses.completed_items', [], $locale))
        ->assertSee(__('courses.quizzes', [], $locale))->assertSee(__('courses.assignments', [], $locale))
        ->assertSee($locale === 'ar' ? 'اختبار الرسم' : 'Drawing quiz')
        ->assertSee($locale === 'ar' ? 'تمرين التظليل' : 'Shading exercise');
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr')
        ->and($page->script("() => document.documentElement.classList.contains('dark')"))->toBe($theme === 'dark');

    $page->screenshot(fullPage: $width === 390, filename: "T-P4-10/roster-{$locale}-{$theme}-{$width}");
    expect($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
})->with($tp410Viewports)->group('browser', 'T-P4-10', 'A3', 'YG-GAP-INS-004');

it('renders a recorded lesson preview without mounting the assistant or writing progress', function (string $locale, string $theme, int $width, int $height): void {
    config(['media.disk' => 'tp410-browser']);
    $disk = Storage::fake('tp410-browser');
    $instructor = User::factory()->create(['locale' => $locale, 'name' => $locale === 'ar' ? 'سارة أحمد' : 'Sara Ahmed'])->assignRole('instructor_academy');
    $course = tp410BrowserCourse($instructor, 'digital-preview');
    $course->update(['ai_enabled' => true]);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['ar' => 'أساسيات الرسم الرقمي', 'en' => 'Digital drawing basics']]);
    $section->update(['published' => true]);
    $lessons = app(ManageLessons::class);
    $previous = $lessons->add($instructor, $section, ['title' => ['ar' => 'التعرف على الأدوات', 'en' => 'Know your tools'], 'type' => 'text']);
    $video = $lessons->add($instructor, $section, ['title' => ['ar' => 'الضوء والظل', 'en' => 'Light and shadow'], 'type' => 'video']);
    $next = $lessons->add($instructor, $section, ['title' => ['ar' => 'الخامات والملمس', 'en' => 'Materials and texture'], 'type' => 'text']);
    $video->update(['ai_enabled' => true]);
    $disk->put('media/lesson-player.mp4', file_get_contents(base_path('tests/Fixtures/lesson-player.mp4')));
    app(AttachLessonMedia::class)($instructor, $video->itemable, ['provider' => 'signed_storage', 'path' => 'media/lesson-player.mp4']);
    $pdf = PdfFixture::bytes();
    $disk->put('documents/lesson.pdf', $pdf);
    $video->itemable->curriculumDocuments()->create([
        'version' => 1, 'path' => 'documents/lesson.pdf', 'sha256' => hash('sha256', $pdf),
        'size' => strlen($pdf), 'pages' => 1, 'status' => LessonCurriculumDocument::STATUS_READY, 'active' => true,
    ]);
    app(SetCurriculumStatus::class)($instructor, $course, [$previous->id, $video->id, $next->id], 'published');
    Feature::for(null)->activate(PlatformFlag::AiAssistant->value);

    $this->actingAs($instructor)->withSession(['locale' => $locale]);
    $page = $this->visit("/preview/{$course->id}/{$video->id}")->resize($width, $height);
    $page->script("() => localStorage.setItem('theme', '{$theme}')");
    $page->refresh();
    $page->script('async () => { await document.fonts.ready; }');

    $page->assertVisible('[data-preview-banner]')->assertVisible('[data-preview-assistant="available"]')
        ->assertVisible('[data-player-media]')->assertVisible('#lesson-materials-panel-pdf')
        ->assertNotPresent('[data-ai-panel], [data-ai-open], [data-progress-url], [data-complete-url]');
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr')
        ->and($page->script('() => document.documentElement.dataset.theme'))->toBe($theme);
    if ($width === 390) {
        $positions = $page->script(<<<'JS'
            () => [...document.querySelectorAll('[data-bottom-nav] li')]
                .map(item => item.getBoundingClientRect().top)
            JS);
        expect($positions)->toHaveCount(4)->and(array_unique($positions))->toHaveCount(1);
    }
    expect($page->script('async () => {
        const video = document.querySelector("[data-player-media]");
        if (video.readyState < 2 && !video.error) {
            await new Promise(resolve => {
                video.addEventListener("loadeddata", resolve, {once: true});
                video.addEventListener("error", resolve, {once: true});
                video.preload = "auto";
                setTimeout(resolve, 3000);
            });
        }
        return video.readyState >= 2 ? video.duration : null;
    }'))->toBe(2);

    $page->screenshot(fullPage: $width === 390, filename: "T-P4-10/preview-{$locale}-{$theme}-{$width}");
    expect($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue()
        ->and(LessonProgress::query()->count())->toBe(0)
        ->and(AiUsage::query()->count())->toBe(0);
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
})->with($tp410Viewports)->group('browser', 'T-P4-10', 'A3', 'YG-GAP-LRN-005', 'ED-024');
