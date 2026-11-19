<?php

declare(strict_types=1);

use App\Domain\Catalog\CourseStatus;
use App\Domain\Commerce\CoursePrice;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Lesson;
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

it('captures the course detail media and access card in both languages', function (string $locale, string $theme, int $width, int $height): void {
    $this->withSession(['locale' => $locale]);
    $course = Course::query()->create([
        'title' => ['en' => 'Digital drawing basics', 'ar' => 'أساسيات الرسم الرقمي'], 'slug_en' => 'drawing', 'slug_ar' => 'الرسم',
        'short_description' => ['en' => 'Learn to draw with digital tools.', 'ar' => 'تعلّم الرسم بالأدوات الرقمية.'],
        'status' => CourseStatus::Published, 'min_age' => 10, 'max_age' => 13,
        'access_mode' => Course::ACCESS_PAID,
        'outcomes' => [['text' => ['en' => 'Use layers to compose an illustration', 'ar' => 'استخدم الطبقات في تكوين رسمة']]],
    ]);
    CoursePrice::query()->create(['course_id' => $course->id, 'market' => 'EG', 'currency' => 'EGP', 'amount_minor' => 59900]);
    $section = CourseSection::query()->create([
        'course_id' => $course->id, 'published' => true, 'sort' => 1,
        'title' => ['en' => 'Drawing tools', 'ar' => 'أدوات الرسم'],
    ]);
    $lesson = Lesson::query()->create([
        'type' => 'text', 'title' => ['en' => 'Introduction', 'ar' => 'المقدمة'], 'is_preview' => true,
    ]);
    $lesson->item()->create(['course_id' => $course->id, 'section_id' => $section->id, 'sort' => 1, 'status' => 'published']);

    $directory = base_path('tests/Browser/screenshots/T-P2-05');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $page = $this->visit('/courses/drawing');
    if ($theme === 'dark') {
        $page = $page->inDarkMode();
    }
    $page->resize($width, $height);
    $page->assertVisible('[data-hero-media] [data-preview-link]')->assertVisible('[data-access-age]')
        ->assertVisible(($width === 390 ? '[data-mobile-cta]' : '[data-access-card]').' [data-course-price]')
        ->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
    expect($page->script('() => document.documentElement.classList.contains("dark")'))->toBe($theme === 'dark');

    if ($width > 390) {
        $mediaAtStart = $page->script('() => {
            const media = document.querySelector("[data-hero-media]").getBoundingClientRect();
            const title = document.querySelector("[data-course-title]").getBoundingClientRect();
            return document.dir === "rtl" ? media.left >= title.right : media.right <= title.left;
        }');
        expect($mediaAtStart)->toBeTrue();
    }

    $page->screenshot(fullPage: true, filename: "T-P2-05/detail-{$locale}-{$theme}-{$width}");
})->with(['ar', 'en'])->with([
    ['light', 390, 844], ['dark', 390, 844], ['light', 1440, 900], ['dark', 1440, 900], ['light', 1568, 1003],
])->group('T-P2-05', 'T-P2-05:A3', 'YG-WEB-004')->group('browser');
