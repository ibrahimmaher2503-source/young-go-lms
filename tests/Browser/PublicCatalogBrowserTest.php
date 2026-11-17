<?php

declare(strict_types=1);

use App\Domain\Catalog\CourseStatus;
use App\Models\Category;
use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Vite;

uses(RefreshDatabase::class);

it('captures the catalog results and empty state in both languages and viewport sizes', function (string $locale, int $width, int $height) {
    if (($buildDirectory = getenv('YOUNG_GO_TEST_BUILD_DIRECTORY')) !== false) {
        Vite::useBuildDirectory($buildDirectory);
    }
    $this->withSession(['locale' => $locale]);
    $category = Category::query()->create([
        'name' => ['en' => 'Arts', 'ar' => 'الفنون'], 'slug_en' => 'arts', 'slug_ar' => 'فنون',
    ]);
    foreach ([['Drawing basics', 'أساسيات الرسم'], ['Storytelling workshop', 'ورشة الحكي'], ['Voice acting', 'الأداء الصوتي']] as $position => [$en, $ar]) {
        Course::query()->create([
            'title' => ['en' => $en, 'ar' => $ar], 'slug_en' => 'workshop-'.$position, 'slug_ar' => 'ورشة-'.$position,
            'status' => CourseStatus::Published, 'category_id' => $category->id, 'access_mode' => Course::ACCESS_FREE,
        ]);
    }

    $directory = base_path('tests/Browser/screenshots/T-P2-04');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $page = $this->visit('/courses')->resize($width, $height);
    $page->assertSee($locale === 'ar' ? 'أساسيات الرسم' : 'Drawing basics')
        ->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');
    expect($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
    $page->screenshot(fullPage: true, filename: "T-P2-04/results-{$locale}-{$width}");

    if ($width === 390) {
        $page->click('[commandfor="catalog-filters"]')->assertVisible('#catalog-filters')->assertNoAccessibilityIssues();
        $page->screenshot(fullPage: true, filename: "T-P2-04/filters-{$locale}-{$width}");
    }

    $this->visit('/courses?q=no-match')->resize($width, $height)
        ->assertSee(__('catalog.empty', [], $locale))->assertNoJavaScriptErrors()->assertNoAccessibilityIssues()
        ->screenshot(fullPage: true, filename: "T-P2-04/empty-{$locale}-{$width}");
})->with(['ar', 'en'])->with([[390, 844], [1440, 900]])->group('T-P2-04', 'A3')->group('browser');
