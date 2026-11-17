<?php

declare(strict_types=1);

use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('shows the manual grants screen with its access summary in both directions and sizes', function (string $locale, int $width, int $height): void {
    $admin = User::factory()->create(['locale' => $locale])->assignRole('admin');
    $course = Course::query()->create(['title' => ['ar' => 'أساسيات الرسم', 'en' => 'Drawing basics'], 'slug_ar' => 'mg-'.uniqid(), 'slug_en' => 'mg-'.uniqid(), 'access_mode' => 'free', 'status' => 'published']);
    $grant = app(GrantEntitlement::class);
    $grant(User::factory()->create(['name' => 'Mona Ali', 'email' => 'mona@example.com']), $course, EntitlementSource::Free);
    $grant(User::factory()->create(['name' => 'Omar Hassan', 'email' => 'omar@example.com']), $course, EntitlementSource::ManualGrant, 'g1', null, now()->addMonth(), $admin, 'Scholarship');
    $grant(User::factory()->create(['name' => 'Salma Nabil', 'email' => 'salma@example.com']), $course, EntitlementSource::ManualGrant, 'g2', now()->subDays(5), now()->subDay(), $admin, 'Trial');
    $this->withSession(['locale' => $locale]);
    $this->actingAs($admin);

    $page = $this->visit('/admin/manual-grants')->resize($width, $height);
    $page->assertSee(__('entitlements.grant', [], $locale))->assertSee('omar@example.com');
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');

    $directory = base_path('tests/Browser/screenshots/T-P4-02');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: true, filename: "T-P4-02/manual-grants-{$locale}-{$width}");
})->with([['ar', 390, 844], ['ar', 1440, 900], ['en', 390, 844], ['en', 1440, 900]])->group('browser', 'T-P4-02', 'A1');
