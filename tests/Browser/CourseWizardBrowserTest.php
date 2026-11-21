<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\SaveCourseCustomField;
use App\Domain\Commerce\CoursePrice;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

it('renders the pricing step with market amounts, localized labels and no overflow', function (string $locale, int $width, int $height, string $theme): void {
    $actor = User::factory()->create(['locale' => $locale, 'name' => 'Admin'])->assignRole('admin');
    $this->actingAs($actor);
    $course = Course::query()->create([
        'title' => ['ar' => 'أساسيات الرسم الرقمي', 'en' => 'Digital drawing basics'],
        'slug_ar' => 'digital-drawing-ar', 'slug_en' => 'digital-drawing',
        'access_mode' => Course::ACCESS_BOTH, 'wizard_step' => 3, 'wizard_completed' => ['1' => true, '2' => true],
    ]);
    CoursePrice::query()->create(['course_id' => $course->id, 'market' => 'EG', 'currency' => 'EGP', 'amount_minor' => 59900]);

    $page = $this->visit("/admin/courses/{$course->id}/edit")->resize($width, $height);
    $page->script("async () => { localStorage.setItem('theme', '{$theme}'); window.dispatchEvent(new CustomEvent('theme-changed', {detail: '{$theme}'})); await document.fonts.ready; }");
    $page->assertSee('EGP')->assertSee('SAR')
        ->assertSee(trans('courses.market_price_disabled', locale: $locale))
        ->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script(<<<'JS'
        () => document.querySelector('input[id$=".market_prices.EG"]')?.value ?? null
        JS))->toBe('599.00')
        ->and($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr')
        ->and($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue()
        ->and($page->script("() => document.documentElement.classList.contains('dark')"))->toBe($theme === 'dark');

    if ($width >= 1440) {
        expect($page->script(<<<'JS'
            () => {
                const rail = document.querySelector('.fi-sc-wizard-header');
                const step = document.querySelector('.fi-sc-wizard-step.fi-active');
                const progress = step.querySelector('progress').closest('.fi-section');
                return rail.offsetWidth > 1100 && Math.abs(
                    step.querySelector('.fi-section').getBoundingClientRect().top - progress.getBoundingClientRect().top
                ) < 2;
            }
            JS))->toBeTrue();
    }
    $directory = base_path('tests/Browser/screenshots/T-P3-03');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: true, filename: "T-P3-03/pricing-{$locale}-{$theme}-{$width}");
})->with([
    ['ar', 390, 844, 'light'], ['ar', 390, 844, 'dark'], ['en', 390, 844, 'light'], ['en', 390, 844, 'dark'],
    ['ar', 1440, 900, 'light'], ['ar', 1440, 900, 'dark'], ['en', 1440, 900, 'light'], ['en', 1440, 900, 'dark'],
    ['ar', 1568, 1003, 'light'],
])->group('browser', 'T-P3-03', 'PRD-09-2');

it('captures the media, SEO, custom field, finish and pending review screens in both directions and sizes', function (string $step, string $locale, int $width, int $height, string $theme): void {
    Storage::fake('public');
    $admin = User::factory()->create(['locale' => $locale, 'name' => 'Admin'])->assignRole('admin');
    $this->actingAs($admin);
    $category = Category::query()->create(['name' => ['ar' => 'فنون', 'en' => 'Arts'], 'slug_ar' => 'فنون-'.uniqid(), 'slug_en' => 'arts-'.uniqid(), 'sort' => 1, 'status' => 'active']);
    $attributes = [
        'title' => ['ar' => 'أساسيات الرسم الرقمي', 'en' => 'Digital drawing basics'],
        'short_description' => ['ar' => 'مقدمة في الرسم الرقمي', 'en' => 'An introduction to digital drawing'],
        'slug_ar' => 't303-ar-'.uniqid(), 'slug_en' => 't303-en-'.uniqid(),
        'category_id' => $category->id, 'level' => 'beginner', 'content_language' => 'both', 'format' => 'recorded',
        'min_age' => 7, 'max_age' => 12, 'access_mode' => Course::ACCESS_FREE,
        'wizard_completed' => ['1' => true, '2' => true, '3' => true],
    ];

    if ($step === 'pending-table') {
        $instructor = User::factory()->create(['locale' => $locale])->assignRole('instructor_independent');
        Course::query()->create([...$attributes, 'status' => 'pending_review', 'owner_type' => Course::OWNER_INDEPENDENT, 'owner_instructor_id' => $instructor->id, 'wizard_step' => 7]);
        Course::query()->create([...$attributes, 'slug_ar' => 't303-draft-ar-'.uniqid(), 'slug_en' => 't303-draft-en-'.uniqid(), 'title' => ['ar' => 'مسودة', 'en' => 'Draft course'], 'status' => 'draft', 'owner_type' => Course::OWNER_INDEPENDENT, 'owner_instructor_id' => $instructor->id, 'wizard_step' => 7]);
        $url = '/admin/courses';
    } else {
        $course = Course::query()->create([...$attributes, 'wizard_step' => match ($step) {
            'media' => 4, 'seo' => 5, 'custom' => 6, default => 7,
        }]);
        if ($step === 'media') {
            Storage::disk('public')->put('courses/t303-cover.jpg', UploadedFile::fake()->image('cover.jpg', 1600, 900)->get());
            $course->forceFill(['cover_image' => 'courses/t303-cover.jpg', 'promo_video' => ['provider' => 'youtube', 'source_url' => 'https://www.youtube.com/watch?v=t303demo']])->save();
        }
        if ($step === 'seo') {
            $course->forceFill([
                'seo_title' => ['ar' => 'الرسم الرقمي للمبتدئين', 'en' => 'Digital drawing for beginners'],
                'seo_description' => ['ar' => 'تعلم أساسيات الرسم الرقمي', 'en' => 'Learn the basics of digital drawing'],
                'seo_keywords' => ['ar' => 'رسم, رقمي', 'en' => 'drawing, digital'],
                'canonical_url' => 'https://example.test/courses/digital-drawing',
            ])->save();
        }
        if ($step === 'custom') {
            $definitions = [
                ['field_key' => 'session_count', 'type' => 'number', 'label' => ['en' => 'Session count', 'ar' => 'عدد الجلسات'], 'is_required' => true],
                ['field_key' => 'skill_tag', 'type' => 'select', 'label' => ['en' => 'Skill tag', 'ar' => 'وسم المهارة'], 'options' => ['Sketch', 'Colour']],
                ['field_key' => 'has_worksheets', 'type' => 'toggle', 'label' => ['en' => 'Has worksheets', 'ar' => 'يحتوي على أوراق عمل']],
                ['field_key' => 'start_on', 'type' => 'date', 'label' => ['en' => 'Start date', 'ar' => 'تاريخ البدء']],
                ['field_key' => 'tagline', 'type' => 'text', 'is_translatable' => true, 'label' => ['en' => 'Tagline', 'ar' => 'الشعار']],
            ];
            foreach ($definitions as $index => $definition) {
                app(SaveCourseCustomField::class)($admin, [...$definition, 'visibility' => 'public', 'is_active' => true, 'sort' => $index + 1, 'is_translatable' => $definition['is_translatable'] ?? false, 'is_required' => $definition['is_required'] ?? false]);
            }
        }
        $url = "/admin/courses/{$course->id}/edit";
    }

    $page = $this->visit($url)->resize($width, $height);
    $page->script("async () => { localStorage.setItem('theme', '{$theme}'); window.dispatchEvent(new CustomEvent('theme-changed', {detail: '{$theme}'})); await document.fonts.ready; }");
    $expected = match ($step) {
        'media' => trans('course_wizard.media_section', [], $locale),
        'seo' => trans('course_page.seo.group', [], $locale),
        'custom' => trans('course_wizard.step_custom', [], $locale),
        'finish' => trans('course_wizard.finish_summary', [], $locale),
        default => trans('courses.pending_review', [], $locale),
    };
    $page->assertSee($expected)->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr')
        ->and($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue()
        ->and($page->script("() => document.documentElement.classList.contains('dark')"))->toBe($theme === 'dark');

    $directory = base_path('tests/Browser/screenshots/T-P3-03');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: true, filename: "T-P3-03/{$step}-{$locale}-{$theme}-{$width}");
})->with(['media', 'seo', 'custom', 'finish', 'pending-table'])
    ->with([
        ['ar', 390, 844, 'light'], ['ar', 390, 844, 'dark'], ['en', 390, 844, 'light'], ['en', 390, 844, 'dark'],
        ['ar', 1440, 900, 'light'], ['ar', 1440, 900, 'dark'], ['en', 1440, 900, 'light'], ['en', 1440, 900, 'dark'],
    ])
    ->group('browser', 'T-P3-03', 'A4', 'D-007', 'PRD-09-2');
