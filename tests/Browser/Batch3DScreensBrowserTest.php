<?php

declare(strict_types=1);

use App\Domain\Engagement\Actions\ApproveCourseReview;
use App\Domain\Engagement\Actions\SubmitCourseReview;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Settings\Actions\SaveMarketingTags;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

function t3dPublicSettingsScreenshot($page, string $locale, string $theme, int $width, string $task, string $name): void
{
    $page->script("() => localStorage.setItem('theme', '{$theme}')");
    $page->refresh();
    $page->script('async () => { await document.fonts.ready; }');
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr')
        ->and($page->script('() => document.documentElement.classList.contains("dark")'))->toBe($theme === 'dark')
        ->and($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();

    $dir = base_path("tests/Browser/screenshots/{$task}/public-refined");
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $page->screenshot(fullPage: true, filename: "{$task}/public-refined/{$name}-{$locale}-{$theme}-{$width}");
}

it('captures accessible teach page screenshots in AR and EN across mobile and desktop', function (string $locale, string $theme, int $width, int $height): void {
    $this->withSession(['locale' => $locale]);
    $page = $this->visit(route('instructors.teach'))->resize($width, $height)
        ->assertVisible('[data-teach-state="guest"]')
        ->assertVisible('[data-teach-state="guest"] a[href$="/login"]')
        ->assertVisible('[data-teach-state="guest"] a[href$="/register"]');

    t3dPublicSettingsScreenshot($page, $locale, $theme, $width, 'T-P9-01', 'teach');
})->with(['ar', 'en'])->with(['light', 'dark'])->with([[390, 844], [1440, 900]])
    ->group('T-P9-01', 'A3', 'YG-GAP-INS-001', 'browser');

it('associates the application field hints and server errors with their native controls', function (string $locale, string $theme, int $width, int $height): void {
    $this->withSession(['locale' => $locale]);
    $this->actingAs(User::factory()->create(['status' => 'active', 'locale' => $locale, 'birth_year' => 1990]));

    $page = $this->visit(route('instructors.teach'))->resize($width, $height)
        ->assertVisible('[data-teach-state="form"]')
        ->assertAttribute('#phone', 'aria-describedby', 'phone-help')
        ->assertAttribute('#portfolio_links', 'aria-describedby', 'portfolio_links-help');

    t3dPublicSettingsScreenshot($page, $locale, $theme, $width, 'T-P9-01', 'teach-application');

    $page->script('() => { document.querySelector("#id_document").disabled = true; }');
    $page->click('[data-teach-state="form"] button[type="submit"]')
        ->assertAttribute('#bio', 'aria-describedby', 'bio-help bio-error')
        ->assertAttribute('#id_document', 'aria-errormessage', 'id_document-error')
        ->assertAttribute('#privacy', 'aria-describedby', 'privacy-error')
        ->assertVisible('#full_name-error');
})->with(['ar', 'en'])->with(['light', 'dark'])->with([[390, 844], [1440, 900]])
    ->group('T-P9-01', 'A3', 'YG-GAP-INS-001', 'browser');

it('shows the current cookie setting and offers two explicit choices', function (string $locale, string $theme, int $width, int $height): void {
    $this->withSession(['locale' => $locale]);
    $marketing = User::factory()->create(['status' => 'active'])->assignRole('marketing');
    app(SaveMarketingTags::class)($marketing, ['ga4_measurement_id' => 'G-SCREEN12', 'meta_pixel_id' => null]);

    $page = $this->visit(route('consent.show'))->resize($width, $height)
        ->assertVisible('[data-consent-state="none"]')
        ->assertVisible('[data-consent-preferences] button[value="decline"]')
        ->assertVisible('[data-consent-preferences] button[value="accept"]');

    t3dPublicSettingsScreenshot($page, $locale, $theme, $width, 'T-P9-03', 'cookie-settings');

    $page->click('[data-consent-preferences] button[value="decline"]')
        ->assertVisible('[data-consent-state="declined"]')
        ->assertNoJavaScriptErrors();
})->with(['ar', 'en'])->with(['light', 'dark'])->with([[390, 844], [1440, 900]])
    ->group('T-P9-03', 'A1', 'YG-GAP-MKT-001', 'browser');

it('captures accessible public course reviews screenshots in AR and EN across mobile and desktop', function (string $locale, int $width, int $height): void {
    $this->withSession(['locale' => $locale]);
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $learner = User::factory()->create(['status' => 'active', 'name' => 'Salma Radi', 'birth_year' => 1990])->assignRole('student');
    $course = Course::query()->create([
        'title' => ['en' => 'Creative Sketchbook', 'ar' => 'دفتر الرسم الإبداعي'],
        'slug_ar' => 'b3d-ar-'.uniqid(),
        'slug_en' => 'b3d-en-'.uniqid(),
        'status' => 'published',
        'access_mode' => Course::ACCESS_FREE,
    ]);
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);
    $review = app(SubmitCourseReview::class)($learner, $course, 5, 'Clear and joyful weekly sketching exercises.');
    app(ApproveCourseReview::class)($admin, $review);

    $slug = $locale === 'ar' ? $course->slug_ar : $course->slug_en;
    $page = $this->visit("/courses/{$slug}#reviews")->resize($width, $height);
    $page->script('async () => { await document.fonts.ready; }');
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr')
        ->and($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
    $page->assertVisible('[data-reviews-summary]')->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();

    $dir = base_path('tests/Browser/screenshots/T-P9-02');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $page->screenshot(fullPage: false, filename: "../screenshots/T-P9-02/course-reviews-{$locale}-{$width}");
})->with(['ar', 'en'])->with([[390, 844], [1440, 900]])
    ->group('T-P9-02', 'A1', 'A2', 'YG-GAP-ENG-001', 'YG-GAP-ENG-002')
    ->group('browser');

it('captures accessible cookie consent banner screenshots in AR and EN across mobile and desktop', function (string $locale, int $width, int $height): void {
    $this->withSession(['locale' => $locale]);
    $marketing = User::factory()->create(['status' => 'active'])->assignRole('marketing');
    app(SaveMarketingTags::class)($marketing, ['ga4_measurement_id' => 'G-SCREEN12', 'meta_pixel_id' => null]);

    $page = $this->visit(route('contact.show'))->resize($width, $height);
    $page->script('async () => { await document.fonts.ready; }');
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr')
        ->and($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
    $page->assertVisible('[data-consent-banner]')->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();

    $dir = base_path('tests/Browser/screenshots/T-P9-03');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $page->screenshot(fullPage: false, filename: "../screenshots/T-P9-03/consent-banner-{$locale}-{$width}");
})->with(['ar', 'en'])->with([[390, 844], [1440, 900]])
    ->group('T-P9-03', 'A1', 'YG-GAP-MKT-001')
    ->group('browser');
