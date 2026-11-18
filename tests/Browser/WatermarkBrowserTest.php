<?php

declare(strict_types=1);

use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Media\Actions\AttachLessonMedia;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    config(['media.disk' => 'watermark-browser']);
    Storage::fake('watermark-browser');
});

it('shows the masked watermark during normal and fullscreen playback', function (string $locale, int $width, int $height, string $theme): void {
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['ar' => 'أساسيات الرسم الرقمي', 'en' => 'Digital drawing basics'], 'slug_ar' => 'wm-'.uniqid(), 'slug_en' => 'wm-'.uniqid(), 'status' => 'published', 'access_mode' => 'free']);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['ar' => 'البداية', 'en' => 'Start']]);
    $section->update(['published' => true]);
    Storage::disk('watermark-browser')->put('media/clip.mp4', file_get_contents(base_path('tests/Fixtures/lesson-player.mp4')));
    $lesson = app(ManageLessons::class)->add($instructor, $section, ['title' => ['ar' => 'التعرف على الأدوات', 'en' => 'Know your tools'], 'type' => 'video']);
    app(AttachLessonMedia::class)($instructor, $lesson->itemable, ['provider' => 'signed_storage', 'path' => 'media/clip.mp4']);
    app(SetCurriculumStatus::class)($instructor, $course, [$lesson->id], 'published');
    $learner = User::factory()->create(['locale' => $locale, 'email' => 'sara.ahmed@example.com'])->assignRole('student');
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);
    $this->withSession(['locale' => $locale]);
    $this->actingAs($learner);

    $page = $this->visit("/learn/{$course->id}/{$lesson->id}")->resize($width, $height);
    $page->script("() => localStorage.setItem('theme', '{$theme}')");
    $page->refresh();
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script("async () => {
        const video = document.querySelector('[data-player-media]');
        video.muted = true;
        await video.play();
        video.pause();
        return video.readyState >= HTMLMediaElement.HAVE_CURRENT_DATA && video.videoWidth > 0;
    }"))->toBeTrue();

    expect($page->script('() => document.documentElement.dataset.theme'))->toBe($theme)
        ->and($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();
    expect($page->script("() => { const mark = document.querySelector('[data-watermark]'); const stage = document.querySelector('[data-player-stage]'); const r = mark.getBoundingClientRect(); const s = stage.getBoundingClientRect(); return stage.contains(mark) && r.width > 0 && r.left >= s.left - 1 && r.right <= s.right + 1 && mark.dataset.spot !== undefined; }"))->toBeTrue()
        ->and($page->script("() => document.querySelector('[data-watermark]').textContent.includes('sa') && !document.querySelector('[data-watermark]').textContent.includes('sara.ahmed@example.com')"))->toBeTrue();

    $directory = base_path('tests/Browser/screenshots/T-P4-07');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: false, filename: "T-P4-07/watermark-{$locale}-{$width}-{$theme}");

    $page->click('[data-player-fullscreen]')->assertVisible('[data-player-stage]:fullscreen');
    expect($page->script("() => {
        const mark = document.querySelector('[data-watermark]');
        const stage = document.querySelector('[data-player-stage]');
        const rect = mark.getBoundingClientRect();
        return document.fullscreenElement === stage && rect.width > 0
            && document.elementsFromPoint(rect.x + rect.width / 2, rect.y + rect.height / 2).includes(stage);
    }"))->toBeTrue();
    $page->screenshot(fullPage: false, filename: "T-P4-07/fullscreen-{$locale}-{$width}-{$theme}");
})->with(['ar', 'en'])->with([[390, 844], [1440, 900], [1568, 1003]])->with(['light', 'dark'])
    ->group('browser', 'T-P4-07', 'A3', 'YG-GAP-VID-003');
