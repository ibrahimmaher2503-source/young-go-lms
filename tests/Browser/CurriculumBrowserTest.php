<?php

declare(strict_types=1);

use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Curriculum\Queries\CurriculumOutline;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function browserCurriculum(string $locale): array
{
    $instructor = User::factory()->create(['locale' => $locale, 'name' => 'Instructor'])->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['ar' => 'أساسيات الرسم الرقمي', 'en' => 'Digital drawing basics'], 'slug_ar' => 'bc-'.uniqid(), 'slug_en' => 'bc-'.uniqid()]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $one = app(ManageSections::class)->add($instructor, $course, ['title' => ['ar' => 'البداية في الرسم الرقمي', 'en' => 'Getting started']]);
    $two = app(ManageSections::class)->add($instructor, $course, ['title' => ['ar' => 'تطبيقات عملية', 'en' => 'Practice']]);
    $items = [];
    foreach ([[$one, 'التعرف على الأدوات', 'Know your tools', 720], [$one, 'الخطوط والأشكال', 'Lines and shapes', 960], [$one, 'الضوء والظل', 'Light and shadow', 1080], [$two, 'رسم العناصر البسيطة', 'Simple elements', 1320]] as [$section, $ar, $en, $seconds]) {
        $items[] = app(ManageLessons::class)->add($instructor, $section, ['title' => ['ar' => $ar, 'en' => $en], 'duration_seconds' => $seconds]);
    }
    app(SetCurriculumStatus::class)($instructor, $course, [$items[0]->id, $items[1]->id, $items[2]->id], 'published');

    return [$course, $instructor, [$one, $two], $items];
}

it('renders the curriculum manager accessibly in both directions and sizes', function (string $locale, int $width, int $height): void {
    [$course, $instructor] = browserCurriculum($locale);
    $this->actingAs($instructor);

    $page = $this->visit("/instructor/courses/{$course->id}/curriculum")->resize($width, $height);
    $page->script('async () => { await document.fonts.ready; }');
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');
    expect($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();

    $directory = base_path('tests/Browser/screenshots/T-P3-04');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: true, filename: "T-P3-04/curriculum-{$locale}-{$width}");
})->with([['ar', 390, 844], ['ar', 1440, 900], ['en', 390, 844], ['en', 1440, 900]])->group('browser', 'T-P3-04', 'A1', 'PRD-09.5');

it('saves a drag and drop reorder across sections', function (): void {
    [$course, $instructor, $sections, $items] = browserCurriculum('en');
    $this->withSession(['locale' => 'en']);
    $this->actingAs($instructor);

    $page = $this->visit("/instructor/courses/{$course->id}/curriculum")->resize(1440, 1200);
    $page->drag("[data-item=\"{$items[3]->id}\"]", "[data-item=\"{$items[0]->id}\"]")->wait(1.5);

    $order = CurriculumOutline::forManager($course)->flatMap(fn ($s) => $s->items->pluck('id'))->map(fn ($id) => (int) $id)->all();
    expect($order)->not->toBe([$items[0]->id, $items[1]->id, $items[2]->id, $items[3]->id])
        ->and(count($order))->toBe(4);
})->group('browser', 'T-P3-04', 'A1');

it('reorders with the keyboard buttons', function (): void {
    [$course, $instructor, , $items] = browserCurriculum('en');
    $this->withSession(['locale' => 'en']);
    $this->actingAs($instructor);

    $page = $this->visit("/instructor/courses/{$course->id}/curriculum");
    $page->click("[data-item=\"{$items[1]->id}\"] button[aria-label^=\"Move up\"]")->wait(1.5);

    $first = CurriculumOutline::forManager($course)->first()->items->pluck('id')->map(fn ($id) => (int) $id)->all();
    expect($first)->toBe([$items[1]->id, $items[0]->id, $items[2]->id]);
})->group('browser', 'T-P3-04', 'A1');

it('renders the lesson editor with the upload field accessibly in both directions and sizes', function (string $locale, int $width, int $height): void {
    [$course, $instructor, , $items] = browserCurriculum($locale);
    $this->withSession(['locale' => $locale]);
    $this->actingAs($instructor);

    $page = $this->visit("/instructor/courses/{$course->id}/curriculum")->resize($width, $height);
    $page->click("[data-item=\"{$items[0]->id}\"] .yg-cur-actions button:nth-child(3)")->wait(1.5);
    $page->select('[id$="media_provider"]', 'signed_storage')->wait(1.5);
    $page->script('async () => { await document.fonts.ready; }');
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');

    $directory = base_path('tests/Browser/screenshots/T-P3-06');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: false, filename: "T-P3-06/lesson-editor-{$locale}-{$width}");
})->with([['ar', 390, 844], ['ar', 1440, 900], ['en', 390, 844], ['en', 1440, 900]])->group('browser', 'T-P3-06', 'A3', 'PRD-09.6');
