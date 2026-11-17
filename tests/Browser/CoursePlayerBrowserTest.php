<?php

declare(strict_types=1);

use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Media\Actions\AttachLessonMedia;
use App\Domain\Media\Actions\ManageSubtitleTracks;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\File\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');
});

/** @return array{0: Course, 1: CurriculumItem, 2: User} an enrolled learner on a video lesson with captions, plus a locked and a text lesson */
function browserPlayer(string $locale): array
{
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['ar' => 'أساسيات الرسم الرقمي', 'en' => 'Digital drawing basics'], 'slug_ar' => 'bp-'.uniqid(), 'slug_en' => 'bp-'.uniqid(), 'status' => 'published', 'access_mode' => 'paid']);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['ar' => 'البداية', 'en' => 'Getting started']]);
    $section->update(['published' => true]);
    Storage::disk('local')->put('media/clip.mp4', 'v');
    $video = app(ManageLessons::class)->add($instructor, $section, ['title' => ['ar' => 'التعرف على الأدوات', 'en' => 'Know your tools'], 'type' => 'video']);
    app(AttachLessonMedia::class)($instructor, $video->itemable, ['provider' => 'signed_storage', 'path' => 'media/clip.mp4']);
    $text = app(ManageLessons::class)->add($instructor, $section, ['title' => ['ar' => 'الخطوط والأشكال', 'en' => 'Lines and shapes'], 'type' => 'text']);
    $dated = app(ManageLessons::class)->add($instructor, $section, ['title' => ['ar' => 'الضوء والظل', 'en' => 'Light and shadow'], 'type' => 'text']);
    app(SetCurriculumStatus::class)($instructor, $course, [$video->id, $text->id, $dated->id], 'published');
    $dated->refresh()->update(['unlock_rule' => 'date', 'unlock_at' => now()->addWeek()]);
    $vtt = tempnam(sys_get_temp_dir(), 'vtt');
    file_put_contents($vtt, "WEBVTT\n\n00:00:01.000 --> 00:00:02.000\nHello\n");
    app(ManageSubtitleTracks::class)->add($instructor, $video->itemable, 'en', 'English', new File($vtt));

    $learner = User::factory()->create(['locale' => $locale])->assignRole('student');
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);

    return [$course, $video, $learner];
}

it('renders the course player and the curriculum drawer accessibly in both directions and sizes', function (string $locale, int $width, int $height): void {
    [$course, $video, $learner] = browserPlayer($locale);
    $this->withSession(['locale' => $locale]);
    $this->actingAs($learner);

    $page = $this->visit("/learn/{$course->id}/{$video->id}")->resize($width, $height);
    $page->assertSee(__('learn.speed', [], $locale))->assertSee($locale === 'ar' ? 'التعرف على الأدوات' : 'Know your tools');
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');

    $directory = base_path('tests/Browser/screenshots/T-P4-03');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $page->screenshot(fullPage: true, filename: "T-P4-03/player-{$locale}-{$width}");

    if ($width < 768) {
        $page->click(__('learn.open_curriculum', [], $locale))->wait(0.5);
        $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();
        $page->screenshot(fullPage: false, filename: "T-P4-03/curriculum-drawer-{$locale}-{$width}");
    }
})->with([['ar', 390, 844], ['ar', 1440, 900], ['en', 390, 844], ['en', 1440, 900]])->group('browser', 'T-P4-03', 'A3', 'YG-GAP-UX-001');

it('answers keyboard shortcuts and remembers the playback speed', function (): void {
    [$course, $video, $learner] = browserPlayer('en');
    $this->withSession(['locale' => 'en']);
    $this->actingAs($learner);

    $page = $this->visit("/learn/{$course->id}/{$video->id}")->resize(1440, 900);
    $press = fn (string $key) => $page->script("() => { document.body.focus(); document.dispatchEvent(new KeyboardEvent('keydown', {key: ".json_encode($key).', bubbles: true})); }');

    expect($page->script("() => document.querySelector('[data-player-media]').playbackRate"))->toBe(1);
    $press('>');
    $press('>');
    expect($page->script("() => document.querySelector('[data-player-media]').playbackRate"))->toBe(1.5);
    $press('<');
    expect($page->script("() => document.querySelector('[data-player-media]').playbackRate"))->toBe(1.25);

    expect($page->script("() => document.querySelector('[data-player]').dataset.captions"))->toBe('off');
    $press('c');
    expect($page->script("() => document.querySelector('[data-player]').dataset.captions"))->toBe('on')
        ->and($page->script("() => document.querySelector('[data-player-captions]').getAttribute('aria-pressed')"))->toBe('true');

    $page->script("() => { const select = document.querySelector('[data-player-speed]'); select.value = '2'; select.dispatchEvent(new Event('change', {bubbles: true})); }");
    $page->refresh();
    expect($page->script("() => document.querySelector('[data-player-media]').playbackRate"))->toBe(2)
        ->and($page->script("() => document.querySelector('[data-player-speed]').value"))->toBe('2')
        ->and($page->script('() => typeof window.ygPlayer.currentPosition()'))->toBe('number');
    $page->assertNoJavaScriptErrors();
})->group('browser', 'T-P4-03', 'A4', 'YG-GAP-LRN-004');
