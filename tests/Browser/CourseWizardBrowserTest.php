<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('renders both wizard steps accessibly in both directions and sizes', function (string $step, string $locale, int $width, int $height): void {
    $this->actingAs(User::factory()->create(['locale' => $locale, 'name' => 'Admin'])->assignRole('admin'));
    $category = Category::query()->create(['name' => ['ar' => 'فنون', 'en' => 'Arts'], 'slug_ar' => 'فنون', 'slug_en' => 'arts', 'sort' => 1, 'status' => 'active']);
    $course = Course::query()->create([
        'title' => ['ar' => 'الرسم للمبتدئين', 'en' => 'Drawing for beginners'], 'short_description' => ['ar' => 'مقدمة في الرسم', 'en' => 'An introduction to drawing'],
        'slug_ar' => 'الرسم', 'slug_en' => 'drawing', 'category_id' => $category->id, 'level' => 'beginner', 'content_language' => 'both', 'format' => 'recorded',
        'min_age' => 7, 'max_age' => 12, 'wizard_step' => $step === 'information' ? 2 : 1,
        'outcomes' => [['text' => ['ar' => 'رسم وجه', 'en' => 'Draw a face']]], 'requirements' => [['text' => ['ar' => 'قلم رصاص', 'en' => 'A pencil']]],
    ]);

    $page = $this->visit("/admin/courses/{$course->id}/edit")->resize($width, $height);
    $page->script('async () => { await document.fonts.ready; }');
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');
    expect($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();

    $directory = base_path('tests/Browser/screenshots/T-P3-02');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: true, filename: "T-P3-02/wizard-{$step}-{$locale}-{$width}");
})->with(['basic', 'information'])->with([['ar', 390, 844], ['ar', 1440, 900], ['en', 390, 844], ['en', 1440, 900]])
    ->group('browser', 'T-P3-02', 'A3', 'PRD-09.2');
