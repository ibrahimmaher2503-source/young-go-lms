<?php

declare(strict_types=1);

use App\Domain\Commerce\CoursePrice;
use App\Models\CmsPage;
use App\Models\Course;
use App\Models\HomepageBlock;
use App\Models\InstructorProfile;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Database\Seeders\CmsPageSeeder;
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
    $second = User::factory()->create();
    $second->assignRole('instructor_academy');
    InstructorProfile::query()->create([
        'user_id' => $second->id,
        'slug_ar' => 'second-instructor-ar',
        'slug_en' => 'second-instructor-en',
        'display_name' => ['ar' => 'أ. نور', 'en' => 'Nour Artist'],
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
    $publicHero = $page->script(<<<'JS'
        () => {
            const hero = document.querySelector('.home-hero');
            const media = hero.querySelector('.home-hero-media');
            const image = media.querySelector('img');
            const title = hero.querySelector('h1');
            return {
                radius: getComputedStyle(image).borderRadius,
                backdrop: getComputedStyle(media, '::before').content !== 'none',
                accent: getComputedStyle(title, '::after').content !== 'none',
            };
        }
        JS);
    expect($publicHero)->toBe([
        'radius' => $locale === 'ar' ? '11% 46% 12% 17% / 18% 37% 30% 23%' : '46% 11% 17% 12% / 37% 18% 23% 30%',
        'backdrop' => true,
        'accent' => $width >= 800,
    ]);
    expect($page->script(<<<'JS'
        () => {
            const hero = document.querySelector('.home-hero').getBoundingClientRect();
            const stats = document.querySelector('.home-stats').getBoundingClientRect();
            return stats.top - hero.bottom >= 32;
        }
        JS))->toBeTrue();
    expect($page->script("() => document.documentElement.classList.contains('dark')"))->toBe($theme === 'dark');
    expect($page->script('() => document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();
    expect($page->script('async () => { const image = document.querySelector("[data-block-type=hero] img"); await image.decode(); return image.naturalWidth > 0; }'))->toBeTrue();
    expect($page->script('() => [...document.fonts].some(face => face.display === "swap")'))->toBeTrue();
    expect($page->script('() => [...document.querySelectorAll("main img")].every(image => image.hasAttribute("srcset") || image.loading === "lazy")'))->toBeTrue();
    expect($page->script('() => document.querySelectorAll("[data-block-type=featured_courses] article").length'))->toBe(3);
    if ($width >= 800) {
        expect($page->script(<<<'JS'
            async () => {
                const box = element => element.getBoundingClientRect();
                const sameRow = (selector, count) => {
                    const boxes = [...document.querySelectorAll(selector)].map(box);
                    return boxes.length === count && boxes.every(rect => Math.abs(rect.top - boxes[0].top) < 2);
                };
                const promo = document.querySelector('.home-featured-promo');
                const art = document.querySelector('.home-newsletter-art');
                const beside = (first, second) => {
                    const a = box(document.querySelector(first));
                    const b = box(document.querySelector(second));
                    return a.width > 0 && b.width > 0 && a.top < b.bottom && b.top < a.bottom
                        && (a.right <= b.left || b.right <= a.left);
                };
                art.loading = 'eager';
                await art.decode();
                return [
                    sameRow('.home-discipline', 6),
                    sameRow('.home-featured-promo, .home-course-card', 4),
                    promo?.querySelector('a')?.getAttribute('href').endsWith('/' + document.documentElement.lang + '/courses') ?? false,
                    beside('.home-steps-copy', '.home-steps-list'),
                    beside('.home-newsletter-copy', '.home-newsletter-art'),
                ];
            }
            JS))->toBe([true, true, true, true, true]);
    }
    if ($locale === 'ar' && $width === 828) {
        expect($page->script(<<<'JS'
            () => {
                const height = selector => document.querySelector(selector).getBoundingClientRect().height;
                return [height('.home-stats') <= 110, height('.home-free') <= 235, height('.home-newsletter') <= 222];
            }
            JS))->toBe([true, true, true]);
    }
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
            const aboveTitle = media.bottom <= title.top;
            window.scrollTo(0, 0);
            return [image.naturalWidth > 0 && aboveTitle, card.querySelector("[data-course-price]").textContent.includes("599")];
        }
        JS))->toBe([true, true]);

    if ($width === 1440) {
        $page->assertNotPresent('.home-featured-art')->assertNotPresent('.home-discipline-icon');
        expect($page->script(<<<'JS'
            () => {
                const box = element => element.getBoundingClientRect();
                const width = box(document.querySelector('[data-public-home]')).width;
                const covers = [...document.querySelectorAll('.home-course-card [data-card-cover]')].map(box);
                const profiles = [...document.querySelectorAll('.home-instructor')].map(box);
                const centered = Math.abs(Math.min(...profiles.map(card => card.left)) + Math.max(...profiles.map(card => card.right)) - innerWidth) < 2;
                const hero = box(document.querySelector('.home-hero'));
                return [width === 1160 && hero.height >= 460 && hero.height <= 520, covers.every(cover => Math.abs(cover.width / cover.height - 1.6) < .03), profiles.length === 2 && profiles.every(profile => profile.width > width / 3), centered];
            }
            JS))->toBe([true, true, true, true]);
    }

    expect($page->script(<<<'JS'
        () => {
            const header = document.querySelector('.site-header-inner').getBoundingClientRect();
            return [...document.querySelectorAll('[data-public-header] a, [data-public-header] button')]
                .filter(control => control.checkVisibility())
                .every(control => {
                    const bounds = control.getBoundingClientRect();
                    return bounds.left >= header.left - 1 && bounds.right <= header.right + 1;
                });
        }
        JS))->toBeTrue();

    if ($width < 1200) {
        $page->click('[commandfor="public-menu"]')->assertVisible('#public-menu[open] [data-public-nav]')
            ->assertNoAccessibilityIssues()->keys('#public-menu', 'Escape')->assertNotPresent('#public-menu[open]');
    }
    if ($width === 390) {
        expect($page->script('() => new Set([...document.querySelectorAll(".home-discipline")].map(card => Math.round(card.getBoundingClientRect().height))).size'))->toBe(1);
    }

    $path = base_path('tests/Browser/screenshots/T-P2-03/public-release');
    if (! is_dir($path)) {
        mkdir($path, 0755, true);
    }
    $page->screenshot(fullPage: true, filename: "T-P2-03/public-release/home-{$locale}-{$theme}-{$width}");
})->with([
    ['ar', 'light', 390, 844], ['en', 'light', 390, 844], ['ar', 'dark', 390, 844], ['en', 'dark', 390, 844],
    ['ar', 'light', 1440, 900], ['en', 'light', 1440, 900], ['ar', 'dark', 1440, 900], ['en', 'dark', 1440, 900],
    ['ar', 'light', 1568, 1003],
    ['ar', 'light', 1200, 900], ['en', 'light', 1200, 900],
    ['ar', 'light', 1024, 900], ['en', 'light', 1024, 900],
    ['ar', 'light', 800, 900],
    ['ar', 'light', 828, 900], ['en', 'dark', 828, 900],
    ['ar', 'light', 851, 1848],
])->group('browser', 'T-P2-03', 'A2', 'A3', 'YG-WEB-010', 'YG-GAP-PERF-001', 'YG-GAP-UX-001', 'ED-030');

it('fits an editorial headline when the tablet hero stacks', function (string $locale): void {
    $this->seed(CmsPageSeeder::class);
    $title = ['ar' => trim(str_repeat('عنوان إبداعي ', 12)), 'en' => trim(str_repeat('Creative skills ', 10))];
    HomepageBlock::query()->where('type', 'hero')->firstOrFail()->update(['title' => $title]);

    $page = $this->visit('/'.$locale)->resize(768, 900)->assertSee($title[$locale]);
    foreach ([768, 790] as $width) {
        $page->resize($width, 900);
        expect($page->script(<<<'JS'
            async () => {
                await document.fonts.ready;
                const title = document.getElementById('home-hero-title');
                return [document.documentElement.scrollWidth <= innerWidth, getComputedStyle(title, '::after').content];
            }
            JS))->toBe([true, 'none']);
    }
})->with(['ar', 'en'])->group('browser', 'T-P2-03', 'A3', 'YG-WEB-010');

it('follows the home learning guide and submits the real Arabic newsletter form', function (): void {
    $this->seed(CmsPageSeeder::class);
    $page = $this->visit('/ar')->resize(851, 900);
    $stepsId = $page->script('() => document.querySelector("[data-block-type=how_it_works]").id');
    $page->click('.home-hero-actions .home-button-secondary')->assertFragmentIs($stepsId)
        ->click('.home-newsletter-submit')->assertVisible('.home-newsletter-email [data-field-error]')
        ->fill('.home-newsletter-form input[name="email"]', 'homepage@example.com')->click('.home-newsletter-submit')
        ->assertSee(__('contact.errors.consent', [], 'ar'));

    expect($page->script(<<<'JS'
        () => {
            const form = document.querySelector('.home-newsletter-form');
            const email = form.querySelector('[name=email]');
            const consent = form.querySelector('[name=consent]');
            return [email.hasAttribute('aria-describedby'), consent.getAttribute('aria-describedby') === consent.id + '-error'];
        }
        JS))->toBe([false, true]);

    $page->check('.home-newsletter-form input[name="consent"]')->click('.home-newsletter-submit')
        ->assertSee(__('contact.newsletter.done', [], 'ar'));

    expect(NewsletterSubscriber::query()->where('email', 'homepage@example.com')->value('locale'))->toBe('ar');
})->group('browser', 'T-P2-03', 'A2', 'YG-WEB-008', 'YG-WEB-010');

it('opens the localized catalog filters from the mobile categories link', function (string $locale): void {
    $this->visit('/'.$locale)->resize(390, 844)
        ->click('[commandfor="public-menu"]')
        ->click('#public-menu a[href$="#catalog-filters"]')
        ->assertVisible('#catalog-filters')
        ->assertFragmentIs('catalog-filters');
})->with(['ar', 'en'])->group('browser', 'T-P2-03', 'A2', 'YG-GAP-UX-001');

it('dismisses the mobile menu when its catalog shortcut stays on the current page', function (string $locale): void {
    $this->visit('/'.$locale.'/courses')->resize(390, 844)
        ->click('[commandfor="public-menu"]')
        ->click('#public-menu a[href$="#catalog-filters"]')
        ->assertNotPresent('#public-menu[open]')
        ->assertFragmentIs('catalog-filters');
})->with(['ar', 'en'])->group('browser', 'T-P2-03', 'A2', 'YG-GAP-UX-001');
