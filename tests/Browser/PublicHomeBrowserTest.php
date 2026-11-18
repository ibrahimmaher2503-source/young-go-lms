<?php

declare(strict_types=1);

use App\Models\CmsPage;
use App\Models\Course;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the public home and exposes accessible mobile navigation', function (string $locale, string $theme, int $width, int $height): void {
    $this->seed(DatabaseSeeder::class);
    CmsPage::query()->where('slug', 'about')->update(['status' => CmsPage::PUBLISHED]);
    foreach ([['مدخل إلى الرسم الرقمي', 'Introduction to digital drawing'], ['التعليق الصوتي', 'Voiceover essentials'], ['مبادئ التحريك', 'Animation fundamentals']] as $index => [$arabic, $english]) {
        Course::query()->create([
            'title' => ['ar' => $arabic, 'en' => $english],
            'slug_ar' => 'course-'.$index,
            'slug_en' => 'creative-course-'.$index,
            'status' => 'published',
            'featured' => true,
        ]);
    }

    $page = $this->visit('/'.$locale);
    if ($theme === 'dark') {
        $page = $page->inDarkMode();
    }
    $page->resize($width, $height);
    $page->script('async () => { await document.fonts.ready; }');
    $page->assertSee(__('public-site.hero', [], $locale))->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');
    expect($page->script("() => document.documentElement.classList.contains('dark')"))->toBe($theme === 'dark');
    expect($page->script('() => document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
    expect($page->script('async () => { const image = document.querySelector("[data-block-type=hero] img"); await image.decode(); return image.naturalWidth > 0; }'))->toBeTrue();
    expect($page->script('() => [...document.fonts].some(face => face.display === "swap")'))->toBeTrue();
    expect($page->script('() => [...document.querySelectorAll("main img")].every(image => image.hasAttribute("srcset") || image.loading === "lazy")'))->toBeTrue();
    expect($page->script('() => document.querySelectorAll("[data-block-type=featured_courses] article").length'))->toBe(3);

    if ($width === 390) {
        $page->click('[commandfor="public-menu"]')->assertVisible('#public-menu[open] [data-public-nav]')
            ->assertNoAccessibilityIssues()->keys('#public-menu', 'Escape')->assertNotPresent('#public-menu[open]');
    }

    $path = base_path('tests/Browser/screenshots/T-P2-03');
    if (! is_dir($path)) {
        mkdir($path, 0755, true);
    }
    $page->screenshot(fullPage: true, filename: "T-P2-03/home-{$locale}-{$theme}-{$width}");
})->with([
    ['ar', 'light', 390, 844], ['en', 'light', 390, 844], ['ar', 'dark', 390, 844], ['en', 'dark', 390, 844],
    ['ar', 'light', 1440, 900], ['en', 'light', 1440, 900], ['ar', 'dark', 1440, 900], ['en', 'dark', 1440, 900],
    ['ar', 'light', 1568, 1003],
])->group('browser', 'T-P2-03', 'A2', 'A3', 'YG-GAP-PERF-001', 'YG-GAP-UX-001', 'ED-030');
