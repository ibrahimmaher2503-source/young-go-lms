<?php

declare(strict_types=1);

use App\Domain\Certificates\Actions\IssueCertificate;
use App\Domain\Completion\CourseCompletion;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the public certificate preview and verification details accessibly', function (string $locale, string $theme, int $width, int $height): void {
    $learner = User::factory()->create([
        'certificate_name_ar' => 'نور محمد سعيد',
        'certificate_name_en' => 'Nour Mohamed Said',
        'phone' => '+201000001234',
    ]);
    $course = Course::query()->create([
        'title' => ['ar' => 'أساسيات الرسم الرقمي', 'en' => 'Digital Drawing Basics'],
        'slug_ar' => 'certificate-course', 'slug_en' => 'certificate-course', 'status' => 'published',
    ]);
    $completion = CourseCompletion::query()->create([
        'course_id' => $course->id, 'learner_id' => $learner->id, 'completed_at' => now(), 'source' => CourseCompletion::AUTO,
    ]);
    $certificate = app(IssueCertificate::class)($completion);
    $this->withSession(['locale' => $locale]);
    $page = $this->visit('/verify/'.$certificate->token)->resize($width, $height);
    $page->script("() => document.documentElement.classList.toggle('dark', '{$theme}' === 'dark')");
    $page->assertVisible('[data-certificate-preview]')
        ->assertVisible('[data-verify-status="valid"]')
        ->assertSee($certificate->nameIn($locale))
        ->assertDontSee($learner->email)
        ->assertDontSee($learner->phone)
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
    expect($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');
    // Pest writes below Screenshots; retain the repository's lowercase artifact directory.
    $directory = base_path('tests/Browser/screenshots/T-P6-06');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: true, filename: "../screenshots/T-P6-06/verify-{$locale}-{$theme}-{$width}");
    if ($locale === 'ar' && $theme === 'light' && $width === 1440) {
        $page->resize(1376, 768)->screenshot(fullPage: false, filename: '../screenshots/T-P6-06/verify-ar-light-1376');
    }
})->with(['ar', 'en'])->with(['light', 'dark'])->with([[390, 844], [1440, 900]])->group('T-P6-06', 'A3', 'YG-GAP-ID-001')->group('browser');
