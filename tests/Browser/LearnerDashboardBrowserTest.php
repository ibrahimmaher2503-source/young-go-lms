<?php

declare(strict_types=1);

use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Progress\Actions\RecordProgress;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/** @return array{0: User, 1: list<Course>} a learner enrolled in three courses with different progress, plus one finished and one ended */
function browserLearner(string $locale): array
{
    $instructor = User::factory()->create(['name' => $locale === 'ar' ? 'نور عبد الرحمن' : 'Noor Abdelrahman'])->assignRole('instructor_academy');
    $learner = User::factory()->create(['locale' => $locale, 'name' => $locale === 'ar' ? 'سارة أحمد' : 'Sara Ahmed'])->assignRole('student');
    $titles = [['أساسيات الرسم الرقمي', 'Digital drawing basics'], ['فن الإلقاء والتعبير', 'The art of public speaking'], ['التمثيل أمام الكاميرا', 'Acting for the camera'], ['مقدمة في الرسوم المتحركة', 'Intro to animation']];
    $courses = [];
    foreach ($titles as $index => [$ar, $en]) {
        $course = Course::query()->create(['title' => ['ar' => $ar, 'en' => $en], 'slug_ar' => 'bd-'.uniqid(), 'slug_en' => 'bd-'.uniqid(), 'status' => 'published', 'access_mode' => 'free']);
        $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
        $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['ar' => 'البداية', 'en' => 'Getting started']]);
        $section->update(['published' => true]);
        $lessons = [];
        foreach ([['الضوء والظل', 'Light and shadow'], ['الخامات والملمس', 'Materials and texture'], ['مشروع تطبيقي', 'Practice project']] as [$lar, $len]) {
            $lessons[] = app(ManageLessons::class)->add($instructor, $section, ['title' => ['ar' => $lar, 'en' => $len], 'type' => 'text', 'duration_seconds' => 600]);
        }
        app(SetCurriculumStatus::class)($instructor, $course, array_map(fn ($l) => $l->id, $lessons), 'published');
        app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free, '', $index === 3 ? now()->subDays(10) : null, $index === 3 ? now()->subDay() : null);
        if ($index === 0) {
            app(RecordProgress::class)->markComplete($learner, $lessons[0]->refresh());
        }
        if ($index === 1) {
            foreach ($lessons as $lesson) {
                app(RecordProgress::class)->markComplete($learner, $lesson->refresh());
            }
        }
        $courses[] = $course;
    }

    return [$learner, $courses];
}

it('renders the learner dashboard and my courses accessibly in both directions and sizes', function (string $locale, int $width, int $height, string $theme): void {
    [$learner] = browserLearner($locale);
    $this->withSession(['locale' => $locale]);
    $this->actingAs($learner);

    $page = $this->visit('/my')->resize($width, $height);
    $page->script("() => { document.documentElement.dataset.theme = '{$theme}'; }");
    $page->assertSee($locale === 'ar' ? 'جاهز نكمل' : 'Ready to continue');
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');

    $directory = base_path('tests/Browser/screenshots/T-P4-05');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $suffix = $theme === 'light' ? '' : "-{$theme}";
    $page->screenshot(fullPage: true, filename: "T-P4-05/dashboard-{$locale}{$suffix}-{$width}");

    $courses = $this->visit('/my/courses')->resize($width, $height);
    $courses->script("() => { document.documentElement.dataset.theme = '{$theme}'; }");
    $courses->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    $courses->screenshot(fullPage: true, filename: "T-P4-05/courses-{$locale}{$suffix}-{$width}");
})->with([['ar', 390, 844], ['ar', 1440, 900], ['en', 390, 844], ['en', 1440, 900]])
    ->with(['light', 'dark'])->group('browser', 'T-P4-05', 'A2');

it('shows the empty states for a learner with no courses', function (string $locale, int $width, int $height): void {
    $learner = User::factory()->create(['locale' => $locale, 'name' => $locale === 'ar' ? 'سارة أحمد' : 'Sara Ahmed'])->assignRole('student');
    $this->withSession(['locale' => $locale]);
    $this->actingAs($learner);

    $page = $this->visit('/my')->resize($width, $height);
    $page->assertSee(__('learner.no_courses', [], $locale));
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    $directory = base_path('tests/Browser/screenshots/T-P4-05');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: true, filename: "T-P4-05/dashboard-empty-{$locale}-{$width}");
})->with([['ar', 390, 844], ['ar', 1440, 900], ['en', 1440, 900]])->group('browser', 'T-P4-05', 'A2');
