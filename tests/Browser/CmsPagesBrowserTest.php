<?php

declare(strict_types=1);

use App\Models\Category;
use App\Models\CmsPage;
use App\Models\HomepageBlock;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    HomepageBlock::query()->create([
        'type' => 'hero', 'title' => ['en' => 'Creative workshops', 'ar' => 'ورش إبداعية'], 'visible' => true,
    ]);
    CmsPage::query()->create([
        'slug' => 'workshops',
        'title' => ['en' => 'Creative workshops', 'ar' => 'ورش إبداعية'],
        'body' => ['en' => 'Acting and drawing workshops.', 'ar' => 'ورش التمثيل والرسم.'],
        'status' => CmsPage::PUBLISHED,
    ]);

    $directory = base_path('tests/Browser/screenshots/T-P2-02');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
});

it('keeps the overflowing empty workspace table reachable by keyboard', function (string $resource) {
    HomepageBlock::query()->delete();
    Category::query()->delete();
    $this->actingAs(User::factory()->create(['locale' => 'en'])->assignRole('content_manager'));

    $page = $this->visit('/admin/'.$resource)->resize(390, 844);
    $page->script('async () => { await document.fonts.ready; }');
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => {
        const table = document.querySelector(".fi-ta-content-ctn");
        return table.scrollWidth <= table.clientWidth + 1 || table.tabIndex === 0;
    }'))->toBeTrue();
})->with(['homepage-blocks', 'categories'])->group('browser', 'T-P2-02');

it('captures the CMS editor lists in both languages and themes', function (string $locale, string $theme, int $width, int $height) {
    $this->withSession(['locale' => $locale])
        ->actingAs(User::factory()->create(['locale' => $locale])->assignRole('content_manager'));

    foreach (['cms-pages' => 'pages', 'homepage-blocks' => 'blocks'] as $path => $title) {
        $page = $this->visit('/admin/'.$path)->resize($width, $height);
        $page->script("() => { localStorage.setItem('theme', '{$theme}'); window.dispatchEvent(new CustomEvent('theme-changed', {detail: '{$theme}'})); }");
        $page->refresh();
        $page->script('async () => { await document.fonts.ready; }');
        $page->assertSee(__('cms.'.$title, [], $locale))->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
        expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');
        expect($page->script("() => document.documentElement.classList.contains('dark')"))->toBe($theme === 'dark');
        expect($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
        $page->screenshot(fullPage: true, filename: "T-P2-02/{$path}-{$locale}-{$theme}-{$width}");
    }
})->with(['ar', 'en'])->with(['light', 'dark'])->with([[390, 844], [1440, 900]])
    ->group('browser', 'T-P2-02');

it('captures the public CMS page and visible homepage blocks', function (string $locale, int $width, int $height) {
    $this->withSession(['locale' => $locale]);

    foreach (['home' => '/', 'public-page' => '/pages/workshops'] as $screen => $path) {
        $page = $this->visit($path)->resize($width, $height);
        $page->script('async () => { await document.fonts.ready; }');
        $page->assertSee($locale === 'ar' ? 'ورش إبداعية' : 'Creative workshops')
            ->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
        expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');
        expect($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
        $page->screenshot(fullPage: true, filename: "T-P2-02/{$screen}-{$locale}-{$width}");
    }
})->with(['ar', 'en'])->with([[390, 844], [1440, 900]])->group('browser', 'T-P2-02');
