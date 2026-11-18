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

it('captures accessible teach page screenshots in AR and EN across mobile and desktop', function (string $locale, int $width, int $height): void {
    $this->withSession(['locale' => $locale]);
    $page = $this->visit(route('instructors.teach'))->resize($width, $height);
    $page->script('async () => { await document.fonts.ready; }');
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr')
        ->and($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
    $page->assertVisible('[data-teach-state="guest"]')->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();

    $dir = base_path('tests/Browser/screenshots/T-P9-01');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $page->screenshot(fullPage: false, filename: "../screenshots/T-P9-01/teach-{$locale}-{$width}");
})->with(['ar', 'en'])->with([[390, 844], [1440, 900]])
    ->group('T-P9-01', 'A3', 'YG-GAP-INS-001')
    ->group('browser');

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
