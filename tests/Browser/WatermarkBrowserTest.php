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
    Storage::fake('local');
});

function watermarkPlayer(string $locale): array
{
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['ar' => 'أساسيات الرسم الرقمي', 'en' => 'Digital drawing basics'], 'slug_ar' => 'wm-'.uniqid(), 'slug_en' => 'wm-'.uniqid(), 'status' => 'published', 'access_mode' => 'free']);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['ar' => 'البداية', 'en' => 'Start']]);
    $section->update(['published' => true]);
    Storage::disk('local')->put('media/clip.mp4', file_get_contents(base_path('tests/Fixtures/lesson-player.mp4')));
    $lesson = app(ManageLessons::class)->add($instructor, $section, ['title' => ['ar' => 'التعرف على الأدوات', 'en' => 'Know your tools'], 'type' => 'video']);
    app(AttachLessonMedia::class)($instructor, $lesson->itemable, ['provider' => 'signed_storage', 'path' => 'media/clip.mp4']);
    app(SetCurriculumStatus::class)($instructor, $course, [$lesson->id], 'published');
    $learner = User::factory()->create(['locale' => $locale, 'email' => 'sara.ahmed@example.com'])->assignRole('student');
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);

    return [$course, $lesson, $learner];
}

it('shows the masked watermark inside the player stage on desktop and phone', function (string $locale, int $width, int $height): void {
    [$course, $lesson, $learner] = watermarkPlayer($locale);
    $this->withSession(['locale' => $locale]);
    $this->actingAs($learner);

    $page = $this->visit("/learn/{$course->id}/{$lesson->id}")->resize($width, $height);
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();

    // It lives inside the element that goes full screen, so it stays visible there, and it is placed at a spot.
    expect($page->script("() => { const mark = document.querySelector('[data-watermark]'); const stage = document.querySelector('[data-player-stage]'); const r = mark.getBoundingClientRect(); const s = stage.getBoundingClientRect(); return stage.contains(mark) && r.width > 0 && r.left >= s.left - 1 && r.right <= s.right + 1 && mark.dataset.spot !== undefined; }"))->toBeTrue()
        ->and($page->script("() => document.querySelector('[data-watermark]').textContent.includes('sa') && !document.querySelector('[data-watermark]').textContent.includes('sara.ahmed@example.com')"))->toBeTrue();

    $directory = base_path('tests/Browser/screenshots/T-P4-07');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: false, filename: "T-P4-07/watermark-{$locale}-{$width}");
})->with([['ar', 390, 844], ['ar', 1440, 900], ['en', 390, 844], ['en', 1440, 900]])->group('browser', 'T-P4-07', 'A3');

it('keeps the protected watermark above the video when the whole stage enters fullscreen', function (string $locale, int $width, int $height, string $theme): void {
    [$course, $lesson, $learner] = watermarkPlayer($locale);
    $this->withSession(['locale' => $locale]);
    $this->actingAs($learner);

    $page = $this->visit("/learn/{$course->id}/{$lesson->id}")->resize($width, $height);
    $page->script("() => localStorage.setItem('theme', '{$theme}')");
    $path = base_path('storage/app/review-media');
    if (! is_dir($path)) {
        mkdir($path, 0755, true);
    }
    $page->refresh()->assertVisible('[data-watermark]')
        ->screenshot(fullPage: false, filename: "../../../storage/app/review-media/player-{$locale}-{$theme}-{$width}")
        ->click('[data-player-fullscreen]');
    expect($page->script('async () => {
        if (!document.fullscreenElement) {
            await new Promise((resolve) => {
                document.addEventListener("fullscreenchange", resolve, {once: true});
                setTimeout(resolve, 1500);
            });
        }
        const mark = document.querySelector("[data-watermark]");
        const stage = document.fullscreenElement;
        if (!stage?.matches("[data-player-stage]") || !stage.contains(mark)) return false;
        const bounds = mark.getBoundingClientRect();
        const player = stage.querySelector("video");
        const video = player.getBoundingClientRect();
        const imageHeight = Math.min(video.height, video.width * player.videoHeight / player.videoWidth);
        const imageTop = video.top + (video.height - imageHeight) / 2;
        return bounds.width > 0 && bounds.left >= video.left && bounds.right <= video.right
            && bounds.top >= imageTop && bounds.bottom <= imageTop + imageHeight && getComputedStyle(mark).zIndex === "10";
    }'))->toBeTrue();

    $page->screenshot(fullPage: false, filename: "../../../storage/app/review-media/fullscreen-{$locale}-{$theme}-{$width}");
})->with([
    ['ar', 390, 844, 'light'], ['ar', 1568, 1003, 'light'], ['en', 390, 844, 'light'], ['en', 1568, 1003, 'light'],
    ['ar', 390, 844, 'dark'], ['ar', 1568, 1003, 'dark'], ['en', 390, 844, 'dark'], ['en', 1568, 1003, 'dark'],
])->group('browser', 'T-P4-07', 'A3', 'YG-GAP-VID-003');
