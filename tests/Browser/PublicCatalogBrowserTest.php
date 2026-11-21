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

it('captures the catalog results and empty state in both languages and viewport sizes', function (string $locale, string $theme, int $width, int $height) {
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

    $directory = base_path('tests/Browser/screenshots/T-P2-04/public-release');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $page = $this->visit('/'.$locale.'/courses');
    if ($theme === 'dark') {
        $page = $page->inDarkMode();
    }
    $page->resize($width, $height);
    $page->assertSee($locale === 'ar' ? 'أساسيات الرسم' : 'Drawing basics')
        ->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');
    expect($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
    $page->screenshot(fullPage: true, filename: "T-P2-04/public-release/results-{$locale}-{$theme}-{$width}");

    if ($width === 390) {
        $page->click('[commandfor="catalog-filter-drawer"]')->assertVisible('#catalog-filter-drawer')->assertNoAccessibilityIssues();
        $page->screenshot(fullPage: true, filename: "T-P2-04/public-release/filters-{$locale}-{$theme}-{$width}");
        $page->click('#catalog-filter-drawer [data-filter-group="category"] summary')
            ->select('#catalog-mobile-category', $category->id)
            ->click('#catalog-filter-drawer form[data-filter-groups] [type="submit"]')
            ->assertPathIs('/'.$locale.'/courses')
            ->assertQueryStringHas('category', (string) $category->id)
            ->assertNotPresent('#catalog-filter-drawer[open]');
    }

    $empty = $this->visit('/'.$locale.'/courses?q=no-match');
    if ($theme === 'dark') {
        $empty = $empty->inDarkMode();
    }
    $empty->resize($width, $height)
        ->assertSee(__('catalog.empty', [], $locale))->assertNoJavaScriptErrors()->assertNoAccessibilityIssues()
        ->screenshot(fullPage: true, filename: "T-P2-04/public-release/empty-{$locale}-{$theme}-{$width}");
})->with(['ar', 'en'])->with([
    ['light', 390, 844], ['dark', 390, 844], ['light', 1440, 900], ['dark', 1440, 900], ['light', 1568, 1003],
])->group('T-P2-04', 'A3', 'YG-WEB-003')->group('browser');

it('captures the course detail media and access card in both languages', function (string $locale, string $theme, int $width, int $height): void {
    $this->withSession(['locale' => $locale]);
    $course = Course::query()->create([
        'title' => ['en' => 'Digital drawing basics', 'ar' => 'أساسيات الرسم الرقمي'], 'slug_en' => 'drawing', 'slug_ar' => 'الرسم',
        'short_description' => ['en' => 'Learn to draw with digital tools.', 'ar' => 'تعلّم الرسم بالأدوات الرقمية.'],
        'status' => CourseStatus::Published, 'min_age' => 10, 'max_age' => 13,
        'access_mode' => Course::ACCESS_PAID,
        'outcomes' => [['text' => ['en' => 'Use layers to compose an illustration', 'ar' => 'استخدم الطبقات في تكوين رسمة']]],
        'requirements' => [['text' => ['en' => 'A sketchbook', 'ar' => 'دفتر رسم']]],
        'faqs' => [['question' => ['en' => 'How do I start?', 'ar' => 'كيف أبدأ؟'], 'answer' => ['en' => 'Open the course preview.', 'ar' => 'افتح معاينة الدورة.']]],
    ]);
    CoursePrice::query()->create(['course_id' => $course->id, 'market' => 'EG', 'currency' => 'EGP', 'amount_minor' => 59900]);
    $section = CourseSection::query()->create([
        'course_id' => $course->id, 'published' => true, 'sort' => 1,
        'title' => ['en' => 'Drawing tools', 'ar' => 'أدوات الرسم'],
    ]);
    $lesson = Lesson::query()->create([
        'type' => 'text', 'title' => ['en' => 'Introduction', 'ar' => 'المقدمة'], 'is_preview' => true,
    ]);
    $previewItem = $lesson->item()->create(['course_id' => $course->id, 'section_id' => $section->id, 'sort' => 1, 'status' => 'published']);
    $locked = Lesson::query()->create([
        'type' => 'text', 'title' => ['en' => 'Shading', 'ar' => 'التظليل'], 'is_preview' => false,
    ]);
    $locked->item()->create(['course_id' => $course->id, 'section_id' => $section->id, 'sort' => 2, 'status' => 'published']);

    $directory = base_path('tests/Browser/screenshots/T-P2-05/public-release');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }

    $slug = $locale === 'ar' ? rawurlencode($course->slug_ar) : $course->slug_en;
    $coursePath = '/'.$locale.'/courses/'.$slug;
    $page = $this->visit($coursePath);
    if ($theme === 'dark') {
        $page = $page->inDarkMode();
    }
    $page->resize($width, $height);
    $page->assertVisible('[data-hero-media] [data-preview-link]')->assertVisible('[data-access-age]')
        ->assertVisible('#curriculum [data-state="locked"] [data-lock]')
        ->assertVisible('#requirements')->assertVisible('#faq summary')
        ->assertVisible(($width === 390 ? '[data-mobile-cta]' : '[data-access-card]').' [data-course-price]')
        ->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
    expect($page->script('() => document.documentElement.classList.contains("dark")'))->toBe($theme === 'dark');

    if ($width > 390) {
        $mediaAtStart = $page->script('async () => {
            await document.fonts.ready;
            const media = document.querySelector("[data-hero-media]").getBoundingClientRect();
            const title = document.querySelector("[data-course-title]").getBoundingClientRect();
            return document.dir === "rtl" ? media.left + 1 >= title.right : media.right <= title.left + 1;
        }');
        expect($mediaAtStart)->toBeTrue();
    } else {
        expect($page->script(<<<'JS'
            () => {
                window.scrollTo(0, document.documentElement.scrollHeight);
                const link = document.querySelector('[data-public-footer] nav:last-of-type li:last-child a');
                const box = link.getBoundingClientRect();
                return link.contains(document.elementFromPoint(box.x + box.width / 2, box.y + box.height / 2));
            }
            JS))->toBeTrue();
        $page->script('() => window.scrollTo(0, 0)');
    }

    $page->screenshot(fullPage: true, filename: "T-P2-05/public-release/detail-{$locale}-{$theme}-{$width}")
        ->click('[data-course-sections] a[href="#curriculum"]')->assertFragmentIs('curriculum')
        ->click('#curriculum [data-preview-link]')->assertVisible('[data-lesson-title]')
        ->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => decodeURI(location.pathname)'))->toBe(rawurldecode($coursePath.'/preview/'.$previewItem->id));
    $page->screenshot(fullPage: true, filename: "T-P2-05/public-release/preview-{$locale}-{$theme}-{$width}");
})->with(['ar', 'en'])->with([
    ['light', 390, 844], ['dark', 390, 844], ['light', 1440, 900], ['dark', 1440, 900], ['light', 1568, 1003],
])->group('T-P2-05', 'T-P2-05:A3', 'YG-WEB-004')->group('browser');
