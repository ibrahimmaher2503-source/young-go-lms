<?php

declare(strict_types=1);

use App\Domain\Content\ContactEnquiryStatus;
use App\Models\CmsPage;
use App\Models\ContactEnquiry;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);

    $directory = base_path('tests/Browser/screenshots/T-P2-08');
    foreach ([$directory, $directory.'/public-refined'] as $path) {
        if (! is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }
});

/** Seeds one enquiry and one subscriber so the admin lists have rows to render. */
function t208SeedPopulated(): NewsletterSubscriber
{
    ContactEnquiry::query()->create([
        'name' => 'Nour Hassan',
        'email' => 'nour@example.com',
        'phone' => '+201012345678',
        'message' => 'I would like to know about the drawing classes for my daughter.',
        'locale' => 'en',
        'status' => ContactEnquiryStatus::New,
    ]);

    return NewsletterSubscriber::query()->create([
        'email' => 'reader@example.com',
        'locale' => 'en',
        'source' => 'footer',
        'consent_version' => 'newsletter-v1-draft',
        'consented_at' => now(),
    ]);
}

/**
 * Shared per-capture checks: direction, no horizontal overflow, no JS errors, no accessibility issues.
 */
function t208AssertCapture($page, string $locale): void
{
    $page->script('async () => { await document.fonts.ready; }');
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr')
        ->and($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
}

/** Applies the theme the same way the admin and public themes read it, then reloads. */
function t208ApplyTheme($page, string $theme): void
{
    $page->script("() => { localStorage.setItem('theme', '{$theme}'); window.dispatchEvent(new CustomEvent('theme-changed', {detail: '{$theme}'})); }");
    $page->refresh();
    $page->script('async () => { await document.fonts.ready; }');
    expect($page->script("() => document.documentElement.classList.contains('dark')"))->toBe($theme === 'dark');
}

it('renders the public contact page with an accessible form and a honeypot that keyboard users cannot reach', function (string $locale, string $theme, int $width, int $height): void {
    $this->withSession(['locale' => $locale]);

    $page = $this->visit('/contact')->resize($width, $height);
    t208ApplyTheme($page, $theme);
    $page->assertPresent('[data-contact-form]')
        ->assertPresent('[data-contact-form] input[name="name"]')
        ->assertPresent('[data-contact-form] input[name="email"]')
        ->assertPresent('[data-contact-form] textarea[name="message"]');

    expect($page->script('() => { const h = document.querySelector("[data-contact-form] input[name=website]"); return h !== null && h.tabIndex === -1 && h.closest("[aria-hidden=true]") !== null; }'))->toBeTrue();

    expect($page->script(<<<'JS'
        () => {
            const form = document.querySelector('[data-contact-form]').getBoundingClientRect();
            const channels = document.querySelector('[data-contact-channels]').getBoundingClientRect();
            const newsletter = document.querySelector('#contact-newsletter').getBoundingClientRect();
            return newsletter.top >= Math.max(form.bottom, channels.bottom)
                && (innerWidth < 768 ? channels.top >= form.bottom : channels.top === form.top && channels.width < form.width);
        }
        JS))->toBeTrue();

    t208AssertCapture($page, $locale);
    $page->screenshot(fullPage: true, filename: "T-P2-08/public-refined/contact-reference-{$locale}-{$theme}-{$width}");
})->with(['ar', 'en'])->with(['light', 'dark'])->with([[390, 844], [1122, 1402], [1440, 900]])
    ->group('T-P2-08', 'A1', 'YG-WEB-007', 'browser');

it('links the contact privacy note to the localized policy only after publication', function (): void {
    $policy = CmsPage::query()->forceCreate([
        'seed_key' => 'privacy',
        'slug' => 'privacy-policy',
        'template' => 'policy',
        'status' => CmsPage::DRAFT,
        'title' => ['ar' => 'سياسة الخصوصية', 'en' => 'Privacy policy'],
        'body' => ['ar' => 'محتوى السياسة', 'en' => 'Policy content'],
    ]);

    $page = $this->visit('/ar/contact')->assertNotPresent('[data-contact-form] a[href*="/pages/"]');
    $policy->update(['status' => CmsPage::PUBLISHED]);

    $page->refresh()->assertVisible('[data-contact-form] a[href$="/ar/pages/privacy-policy"]');
})->group('T-P2-08', 'A1', 'YG-WEB-007', 'browser');

it('renders the public newsletter page with the consent checkbox and an accessible card', function (string $locale, string $theme, int $width, int $height): void {
    $this->withSession(['locale' => $locale]);

    $page = $this->visit('/newsletter')->resize($width, $height);
    t208ApplyTheme($page, $theme);
    $page->assertPresent('[data-newsletter-card]')
        ->assertPresent('[data-newsletter-card] input[name="email"]')
        ->assertPresent('[data-newsletter-card] input[name="consent"]');

    t208AssertCapture($page, $locale);
    $page->screenshot(fullPage: true, filename: "T-P2-08/public-refined/newsletter-{$locale}-{$theme}-{$width}");
})->with(['ar', 'en'])->with(['light', 'dark'])->with([[390, 844], [1440, 900]])
    ->group('T-P2-08', 'A2', 'YG-WEB-008', 'browser');

it('confirms the login-free signed unsubscribe page in both locales', function (string $locale, string $theme, int $width, int $height): void {
    $this->withSession(['locale' => $locale]);
    $subscriber = NewsletterSubscriber::query()->create([
        'email' => 'reader@example.com',
        'locale' => $locale,
        'source' => 'footer',
        'consent_version' => 'newsletter-v1-draft',
        'consented_at' => now(),
    ]);

    $url = $subscriber->unsubscribeUrl();
    $path = parse_url($url, PHP_URL_PATH).'?'.parse_url($url, PHP_URL_QUERY);

    $page = $this->visit($path)->resize($width, $height);
    t208ApplyTheme($page, $theme);
    $page->assertPresent('[data-unsubscribed]');

    expect($subscriber->fresh()->isSubscribed())->toBeFalse();

    t208AssertCapture($page, $locale);
    $page->screenshot(fullPage: true, filename: "T-P2-08/public-refined/unsubscribed-{$locale}-{$theme}-{$width}");
})->with(['ar', 'en'])->with(['light', 'dark'])->with([[390, 844], [1440, 900]])
    ->group('T-P2-08', 'A2', 'YG-WEB-008', 'browser');

it('renders the populated and empty enquiry inbox for marketing staff', function (string $locale, string $theme, int $width, int $height): void {
    $this->withSession(['locale' => $locale]);
    $marketing = User::factory()->create(['status' => 'active', 'locale' => $locale])->assignRole('marketing');
    $this->actingAs($marketing);

    foreach (['populated', 'empty'] as $state) {
        if ($state === 'empty') {
            ContactEnquiry::query()->delete();
        } else {
            t208SeedPopulated();
        }

        $page = $this->visit('/admin/contact-enquiries')->resize($width, $height);
        t208ApplyTheme($page, $theme);
        t208AssertCapture($page, $locale);
        $page->screenshot(fullPage: true, filename: "T-P2-08/admin-contact-enquiries-{$state}-{$locale}-{$theme}-{$width}");
    }
})->with(['ar', 'en'])->with(['light', 'dark'])->with([[390, 844], [1440, 900]])
    ->group('T-P2-08', 'A1', 'YG-WEB-007', 'browser');

it('renders the populated and empty newsletter subscriber list for marketing staff', function (string $locale, string $theme, int $width, int $height): void {
    $this->withSession(['locale' => $locale]);
    $marketing = User::factory()->create(['status' => 'active', 'locale' => $locale])->assignRole('marketing');
    $this->actingAs($marketing);

    foreach (['populated', 'empty'] as $state) {
        if ($state === 'empty') {
            NewsletterSubscriber::query()->delete();
        } else {
            t208SeedPopulated();
        }

        $page = $this->visit('/admin/newsletter-subscribers')->resize($width, $height);
        t208ApplyTheme($page, $theme);
        t208AssertCapture($page, $locale);
        $page->screenshot(fullPage: true, filename: "T-P2-08/admin-newsletter-subscribers-{$state}-{$locale}-{$theme}-{$width}");
    }
})->with(['ar', 'en'])->with(['light', 'dark'])->with([[390, 844], [1440, 900]])
    ->group('T-P2-08', 'A2', 'YG-WEB-008', 'browser');
