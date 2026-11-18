<?php

declare(strict_types=1);

use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Live\Actions\CompleteLiveSession;
use App\Domain\Live\Actions\OpenLiveSession;
use App\Domain\Live\Actions\PublishLiveReplay;
use App\Domain\Live\Actions\ScheduleLiveSession;
use App\Domain\Media\Actions\AttachLessonMedia;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

it('shows the live session card and the dashboard schedule accessibly in both directions and sizes', function (string $locale, int $width, int $height): void {
    $admin = User::factory()->create()->assignRole('admin');
    $course = Course::query()->create(['title' => ['ar' => 'ورشة الرسم المباشرة', 'en' => 'Live drawing workshop'], 'slug_ar' => 'lv-'.uniqid(), 'slug_en' => 'lv-'.uniqid(), 'status' => 'published', 'access_mode' => 'paid', 'format' => 'live']);
    $section = app(ManageSections::class)->add($admin, $course, ['title' => ['ar' => 'الأسبوع الأول', 'en' => 'Week one']]);
    $section->update(['published' => true]);
    $item = app(ScheduleLiveSession::class)($admin, $section, [
        'title' => ['ar' => 'جلسة الانطلاق', 'en' => 'Kickoff session'], 'description' => ['ar' => 'نتعرف على الأدوات ونبدأ أول تمرين.', 'en' => 'We meet the tools and start the first exercise.'],
        'starts_at' => now()->addDays(2)->format('Y-m-d H:i'), 'ends_at' => now()->addDays(2)->addHour()->format('Y-m-d H:i'), 'timezone' => 'UTC',
        'provider' => 'manual_zoom', 'meeting_url' => 'https://zoom.example.com/j/browser-room', 'join_window_minutes' => 15,
    ]);
    app(SetCurriculumStatus::class)($admin, $course, [$item->id], 'published');
    $learner = User::factory()->create(['locale' => $locale])->assignRole('student');
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);
    $this->withSession(['locale' => $locale]);
    $this->actingAs($learner);

    $page = $this->visit("/learn/{$course->id}/{$item->id}")->resize($width, $height);
    $page->assertSee(__('live.title', [], $locale));
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr')
        ->and($page->script("() => document.querySelectorAll('[data-live-card]').length"))->toBe(1)
        ->and($page->script('() => document.body.innerHTML.includes("browser-room")'))->toBeFalse();

    $directory = base_path('tests/Browser/screenshots/T-P5-03');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: true, filename: "T-P5-03/live-card-{$locale}-{$width}");

    $dashboard = $this->visit('/my')->resize($width, $height);
    $dashboard->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($dashboard->script("() => document.querySelectorAll('[data-live-row]').length"))->toBe(1);
    $dashboard->screenshot(fullPage: true, filename: "T-P5-03/dashboard-{$locale}-{$width}");
})->with([['ar', 390, 844], ['ar', 1440, 900], ['en', 390, 844], ['en', 1440, 900]])->group('T-P5-03', 'A1')->group('browser');

it('shows the published replay link on an ended live session card accessibly in AR/EN', function (string $locale, int $width, int $height): void {
    $admin = User::factory()->create()->assignRole('admin');
    $course = Course::query()->create(['title' => ['ar' => 'ورشة الإعادة', 'en' => 'Replay workshop'], 'slug_ar' => 'lvr-'.uniqid(), 'slug_en' => 'lvr-'.uniqid(), 'status' => 'published', 'access_mode' => 'paid', 'format' => 'live']);
    $section = app(ManageSections::class)->add($admin, $course, ['title' => ['ar' => 'الأسبوع الأول', 'en' => 'Week one']]);
    $section->update(['published' => true]);
    $liveItem = app(ScheduleLiveSession::class)($admin, $section, [
        'title' => ['ar' => 'جلسة منتهية', 'en' => 'Ended session'],
        'starts_at' => now()->subDays(2)->format('Y-m-d H:i'),
        'ends_at' => now()->subDays(2)->addHour()->format('Y-m-d H:i'),
        'timezone' => 'UTC',
        'provider' => 'manual_zoom',
        'meeting_url' => 'https://zoom.example.com/j/ended',
        'join_window_minutes' => 15,
    ]);
    $session = $liveItem->itemable;
    app(OpenLiveSession::class)($admin, $session);
    app(CompleteLiveSession::class)($admin, $session);
    $replayItem = app(PublishLiveReplay::class)($admin, $session->refresh());
    Storage::fake('local');
    Storage::disk('local')->put('media/replay-browser.mp4', 'video');
    app(AttachLessonMedia::class)($admin, $replayItem->itemable, ['provider' => 'signed_storage', 'path' => 'media/replay-browser.mp4']);
    app(SetCurriculumStatus::class)($admin, $course, [$liveItem->id, $replayItem->id], 'published');

    $learner = User::factory()->create(['locale' => $locale])->assignRole('student');
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);
    $this->withSession(['locale' => $locale]);
    $this->actingAs($learner);

    $page = $this->visit("/learn/{$course->id}/{$liveItem->id}")->resize($width, $height);
    $page->assertVisible('[data-replay-link]')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr')
        ->and($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();

    $directory = base_path('tests/Browser/screenshots/T-P5-05');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: true, filename: "T-P5-05/ended-replay-{$locale}-{$width}");
})->with([['ar', 390, 844], ['ar', 1440, 900], ['en', 390, 844], ['en', 1440, 900]])->group('T-P5-05', 'A2')->group('browser');
