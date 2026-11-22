<?php

declare(strict_types=1);

use App\Domain\Catalog\CourseStatus;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('captures the scoped directory and course details in both panel languages and themes', function (string $panel, string $locale, string $theme, int $width, int $height) {
    $this->seed(DatabaseSeeder::class);
    $viewer = User::factory()->create([
        'locale' => $locale, 'name' => $locale === 'ar' ? 'سارة محمود' : 'Sara Mahmoud',
    ])->assignRole($panel === 'admin' ? 'admin' : 'instructor_academy');
    $this->withSession(['locale' => $locale])->actingAs($viewer);

    $category = Category::query()->where('slug_en', 'acting')->sole();
    $course = Course::query()->create([
        'title' => ['ar' => 'ورشة الأداء المسرحي', 'en' => 'Stage Performance Workshop'],
        'description' => ['ar' => 'تمارين في الأداء المسرحي', 'en' => 'Practical stage acting exercises'],
        'slug_en' => 'stage-workshop', 'slug_ar' => 'ورشة-الأداء', 'category_id' => $category->id,
        'status' => CourseStatus::Published, 'access_mode' => Course::ACCESS_FREE,
    ]);
    $course->instructors()->attach($viewer, ['scope' => 'manage']);

    $directory = base_path('tests/Browser/screenshots/T-P3-01');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    foreach (['directory' => "/{$panel}/courses", 'details' => "/{$panel}/courses/{$course->id}"] as $screen => $path) {
        $page = $this->visit($path)->resize($width, $height);
        $page->script("() => localStorage.setItem('filament:theme:mode', '{$theme}')");
        $page->refresh();
        $page->script('async () => { await document.fonts.ready; }');
        $page->assertSee($course->getTranslation('title', $locale))->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
        expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');
        expect($page->script("() => document.documentElement.classList.contains('dark')"))->toBe($theme === 'dark');
        expect($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
        if ($screen === 'details') {
            expect($page->script("() => document.querySelector('h1.fi-header-heading')?.textContent.trim()"))
                ->toBe($course->getTranslation('title', $locale));
            $page->assertSeeIn('[data-course-overview]', $course->getTranslation('description', $locale))
                ->assertSeeIn('[data-course-facts]', $viewer->name);
            expect($page->script("() => document.querySelector('[data-course-facts] .fi-badge')?.textContent.trim()"))
                ->toBe($locale === 'ar' ? 'منشورة' : 'Published');
            expect($page->script("() => [...document.querySelectorAll('main .fi-breadcrumbs a')].some(link => new URL(link.href).pathname === '/{$panel}/courses' && link.getClientRects().length > 0)"))->toBeTrue();
            expect($page->script("() => [...document.querySelectorAll('main a')].some(link => new URL(link.href).pathname === '{$path}/curriculum')"))->toBeTrue();
        }
        $page->screenshot(fullPage: true, filename: "T-P3-01/{$panel}-{$screen}-{$locale}-{$theme}-{$width}");
    }
})->with(['admin', 'instructor'])->with(['ar', 'en'])->with(['light', 'dark'])->with([[390, 844], [1440, 900]])
    ->group('browser', 'T-P3-01', 'PRD-09.1');
