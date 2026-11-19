<?php

declare(strict_types=1);

use App\Domain\Commerce\CoursePrice;
use App\Models\CmsPage;
use App\Models\Course;
use App\Models\InstructorProfile;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the public home and exposes accessible mobile navigation', function (string $locale, string $theme, int $width, int $height): void {
    $this->seed(DatabaseSeeder::class);
    CmsPage::query()->where('slug', 'about')->update(['status' => CmsPage::PUBLISHED]);
    config(['filesystems.disks.public.url' => '/images']);
    $instructor = User::factory()->create(['name' => 'Staff account name']);
    $instructor->assignRole('instructor_academy');
    InstructorProfile::query()->create([
        'user_id' => $instructor->id,
        'slug_ar' => 'test-instructor-ar',
        'slug_en' => 'test-instructor-en',
        'display_name' => ['ar' => 'أ. سارة', 'en' => 'Sara Artist'],
        'status' => InstructorProfile::APPROVED,
    ]);
    foreach ([['مدخل إلى الرسم الرقمي', 'Introduction to digital drawing'], ['التعليق الصوتي', 'Voiceover essentials'], ['مبادئ التحريك', 'Animation fundamentals']] as $index => [$arabic, $english]) {
        $course = Course::query()->create([
            'title' => ['ar' => $arabic, 'en' => $english],
            'slug_ar' => 'course-'.$index,
            'slug_en' => 'creative-course-'.$index,
            'status' => 'published',
            'featured' => true,
            'access_mode' => $index === 0 ? Course::ACCESS_PAID : Course::ACCESS_FREE,
            'cover_image' => $index < 2 ? 'public-home-hero-640.webp' : null,
        ]);
        if ($index === 0) {
            $course->instructors()->attach($instructor, ['scope' => Course::INSTRUCTOR_SCOPE_MANAGE]);
            CoursePrice::query()->create(['course_id' => $course->id, 'market' => 'EG', 'currency' => 'EGP', 'amount_minor' => 59900]);
        }
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
    $slug = $locale === 'ar' ? 'course-0' : 'creative-course-0';
    $featuredLink = '[data-block-type=featured_courses] a[href$="/'.$locale.'/courses/'.$slug.'"]';
    $page->assertVisible($featuredLink)->assertSee($locale === 'ar' ? 'أ. سارة' : 'Sara Artist')->assertDontSee('Staff account name');
    expect($page->script(<<<'JS'
        async () => {
            const card = document.querySelector("[data-block-type=featured_courses] article:has([data-course-price])");
            if (!card) return null;
            const image = card.querySelector("img");
            if (!image) return null;
            image.scrollIntoView();
            await image.decode();
            const media = image.getBoundingClientRect();
            const title = card.querySelector("h3").getBoundingClientRect();
            const atStart = document.dir === "rtl" ? media.left >= title.right : media.right <= title.left;
            window.scrollTo(0, 0);
            return [image.naturalWidth > 0 && atStart, card.querySelector("[data-course-price]").textContent.includes("599")];
        }
        JS))->toBe([true, true]);

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
])->group('browser', 'T-P2-03', 'A2', 'A3', 'YG-WEB-010', 'YG-GAP-PERF-001', 'YG-GAP-UX-001', 'ED-030');

it('opens the localized catalog filters from the mobile categories link', function (string $locale): void {
    $this->visit('/'.$locale)->resize(390, 844)
        ->click('[commandfor="public-menu"]')
        ->click('#public-menu a[href$="#catalog-filters"]')
        ->assertVisible('#catalog-filters')
        ->assertFragmentIs('catalog-filters');
})->with(['ar', 'en'])->group('browser', 'T-P2-03', 'A2', 'YG-GAP-UX-001');
