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

it('shows the masked watermark inside the player stage on desktop and phone', function (string $locale, int $width, int $height): void {
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['ar' => 'أساسيات الرسم الرقمي', 'en' => 'Digital drawing basics'], 'slug_ar' => 'wm-'.uniqid(), 'slug_en' => 'wm-'.uniqid(), 'status' => 'published', 'access_mode' => 'free']);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['ar' => 'البداية', 'en' => 'Start']]);
    $section->update(['published' => true]);
    Storage::disk('local')->put('media/clip.mp4', 'v');
    $lesson = app(ManageLessons::class)->add($instructor, $section, ['title' => ['ar' => 'التعرف على الأدوات', 'en' => 'Know your tools'], 'type' => 'video']);
    app(AttachLessonMedia::class)($instructor, $lesson->itemable, ['provider' => 'signed_storage', 'path' => 'media/clip.mp4']);
    app(SetCurriculumStatus::class)($instructor, $course, [$lesson->id], 'published');
    $learner = User::factory()->create(['locale' => $locale, 'email' => 'sara.ahmed@example.com'])->assignRole('student');
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);
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
