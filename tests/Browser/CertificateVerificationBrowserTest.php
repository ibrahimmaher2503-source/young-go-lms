<?php

declare(strict_types=1);

use App\Domain\Certificates\Actions\IssueCertificate;
use App\Domain\Certificates\CertificatePdf;
use App\Domain\Certificates\CertificateTemplate;
use App\Domain\Completion\CourseCompletion;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

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
    expect($page->script(<<<'JS'
        () => {
            const status = document.querySelector('[data-verify-status]').getBoundingClientRect();
            const preview = document.querySelector('[data-certificate-preview]').getBoundingClientRect();
            return innerWidth < 1024 ? status.bottom <= preview.top : status.top < innerHeight && preview.top < innerHeight;
        }
        JS))->toBeTrue();
    expect($page->script('() => document.querySelector("[data-copy-text]").getBoundingClientRect().height >= 44'))->toBeTrue();
    // Pest writes below Screenshots; retain the repository's lowercase artifact directory.
    $directory = base_path('tests/Browser/screenshots/T-P6-06/public-polish');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: true, filename: "../screenshots/T-P6-06/public-polish/verify-{$locale}-{$theme}-{$width}");
    if ($locale === 'ar' && $theme === 'light' && $width === 1440) {
        $page->resize(1376, 768)->screenshot(fullPage: false, filename: '../screenshots/T-P6-06/public-polish/verify-ar-light-1376');
    }
})->with(['ar', 'en'])->with(['light', 'dark'])->with([[390, 844], [1440, 900]])->group('T-P6-06', 'A3', 'YG-GAP-ID-001')->group('browser');

it('renders the certificate PDF layout at A4 landscape with connected Arabic shaping and direction', function (string $locale): void {
    $learner = User::factory()->create([
        'certificate_name_ar' => 'نور محمد سعيد',
        'certificate_name_en' => 'Nour Mohamed Said',
    ]);
    $course = Course::query()->create([
        'title' => ['ar' => 'أساسيات الرسم الرقمي', 'en' => 'Digital Drawing Basics'],
        'slug_ar' => 'certificate-pdf-course', 'slug_en' => 'certificate-pdf-course', 'status' => 'published',
    ]);
    $completion = CourseCompletion::query()->create([
        'course_id' => $course->id, 'learner_id' => $learner->id, 'completed_at' => now(), 'source' => CourseCompletion::AUTO,
    ]);
    $certificate = app(IssueCertificate::class)($completion);

    // The exact standalone HTML handed to headless Chromium by CertificatePdf::build(), served on a test-only route.
    $template = CertificateTemplate::query()->firstWhere('locale', $locale)
        ?? new CertificateTemplate(['locale' => $locale, 'layout' => CertificateTemplate::DEFAULT_LAYOUT]);
    $html = view($template->view(), app(CertificatePdf::class)->data($certificate, $locale, $template))->render();
    Route::middleware('web')->get("/_certificate-pdf-test/{$locale}", static fn () => response($html)->header('Content-Type', 'text/html; charset=UTF-8'));

    $page = $this->visit("/_certificate-pdf-test/{$locale}")->resize(1123, 794);
    $page->script('async () => { await document.fonts.ready; }');
    $page->assertVisible('.frame')
        ->assertSee($certificate->nameIn($locale))
        ->assertSee($certificate->number)
        ->assertVisible('img.qr')
        ->assertNoJavaScriptErrors();

    expect($page->script('() => [document.documentElement.lang, document.documentElement.dir]'))
        ->toBe([$locale, $locale === 'ar' ? 'rtl' : 'ltr']);
    // Noto Sans Arabic is embedded and loaded; the name element uses it.
    expect($page->script('() => [...document.fonts].some((f) => f.family.includes("Noto Sans Arabic") && f.status === "loaded")'))->toBeTrue();
    expect($page->script('() => document.querySelector(".name").getBoundingClientRect().width > 0'))->toBeTrue();
    // The QR is embedded as an SVG data URI image (not an inline <svg>) and has decoded.
    expect($page->script('() => { const img = document.querySelector("img.qr"); return img.complete && img.naturalWidth > 0 && img.src.startsWith("data:image/svg+xml"); }'))->toBeTrue();
    // A4 landscape is 297mm x 210mm (about 1123 x 794 CSS px): nothing may overflow the sheet.
    expect($page->script('() => document.documentElement.scrollWidth <= window.innerWidth + 1'))->toBeTrue();
    expect($page->script('() => document.documentElement.scrollHeight <= window.innerHeight + 1'))->toBeTrue();
    expect($page->script('() => document.querySelector(".frame").getBoundingClientRect().bottom <= window.innerHeight'))->toBeTrue();

    if ($locale === 'ar') {
        // Connected shaping: a joined Arabic run measures narrower than its letters measured one by one.
        // Isolated presentation forms in the DOM would mean the text was pre-split, so they must be absent.
        $shaping = $page->script(<<<'JS'
() => {
  const el = document.querySelector('.name');
  const text = el.textContent.trim();
  const style = getComputedStyle(el);
  const ctx = document.createElement('canvas').getContext('2d');
  ctx.font = `${style.fontWeight} ${style.fontSize} ${style.fontFamily}`;
  const joined = ctx.measureText(text).width;
  const isolated = [...text].reduce((sum, ch) => sum + ctx.measureText(ch).width, 0);
  return { joined, isolated, presentationForms: /[ﭐ-﷿ﹰ-﻿]/.test(text), arabic: /\p{Script=Arabic}/u.test(text) };
}
JS);
        expect($shaping['arabic'])->toBeTrue();
        expect($shaping['presentationForms'])->toBeFalse();
        expect($shaping['joined'])->toBeLessThan($shaping['isolated']);
    }

    $page->screenshot(fullPage: false, filename: "T-P6-06/pdf-{$locale}-landscape");
})->with(['ar', 'en'])->group('T-P6-06', 'A2', 'REQ-CP-003', 'REQ-CP-004', 'YG-GAP-CRT-001', 'browser');

it('keeps the certificate footer inside the frame border at A4 landscape', function (string $locale): void {
    $learner = User::factory()->create([
        'certificate_name_ar' => 'نور محمد سعيد',
        'certificate_name_en' => 'Nour Mohamed Said',
    ]);
    $course = Course::query()->create([
        'title' => ['ar' => 'أساسيات الرسم الرقمي', 'en' => 'Digital Drawing Basics'],
        'slug_ar' => 'certificate-footer-course', 'slug_en' => 'certificate-footer-course', 'status' => 'published',
    ]);
    $completion = CourseCompletion::query()->create([
        'course_id' => $course->id, 'learner_id' => $learner->id, 'completed_at' => now(), 'source' => CourseCompletion::AUTO,
    ]);
    $certificate = app(IssueCertificate::class)($completion);
    $template = CertificateTemplate::query()->firstWhere('locale', $locale)
        ?? new CertificateTemplate(['locale' => $locale, 'layout' => CertificateTemplate::DEFAULT_LAYOUT]);
    $html = view($template->view(), app(CertificatePdf::class)->data($certificate, $locale, $template))->render();
    Route::middleware('web')->get("/_certificate-footer-test/{$locale}", static fn () => response($html)->header('Content-Type', 'text/html; charset=UTF-8'));

    $page = $this->visit("/_certificate-footer-test/{$locale}")->resize(1123, 794);
    $page->script('async () => { await document.fonts.ready; }');
    // The footer (QR, verify link, issuer) must sit inside the frame border, not spill past it.
    expect($page->script('() => document.querySelector(".foot").getBoundingClientRect().bottom <= document.querySelector(".frame").getBoundingClientRect().bottom'))->toBeTrue();
})->with(['ar', 'en'])->group('T-P6-06', 'A2', 'REQ-CP-003', 'REQ-CP-004', 'YG-GAP-CRT-001', 'browser');
