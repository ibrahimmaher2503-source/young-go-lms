<?php

declare(strict_types=1);

use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Media\Actions\AttachLessonMedia;
use App\Domain\Media\LessonPublishRule;
use App\Domain\Media\MediaProviders;
use App\Domain\Media\PlaybackDescriptor;
use App\Domain\Media\Queries\LessonPlayback;
use App\Models\Course;
use App\Models\MediaAsset;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');
    config(['media.iframe_allowlist' => ['slides.example.edu']]);
});

function mediaLesson(): array
{
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['en' => 'Media'], 'slug_ar' => 'm-'.uniqid(), 'slug_en' => 'm-'.uniqid()]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'S']]);
    $item = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'L']]);

    return [$instructor, $item->itemable, $course];
}

it('validates every source type and returns a playable descriptor', function (array $source, string $type, string $tier, string $provider): void {
    [$instructor, $lesson] = mediaLesson();
    Storage::disk('local')->put('media/clip.mp4', 'v');
    Storage::disk('local')->put('media/song.mp3', 'a');
    Storage::disk('local')->put('media/notes.pdf', 'd');
    Storage::disk('local')->put('media/pic.png', 'i');

    $asset = app(AttachLessonMedia::class)($instructor, $lesson, $source);
    $descriptor = LessonPlayback::for($lesson->refresh(), $instructor);

    expect($asset->provider)->toBe($provider)->and($asset->protection_tier)->toBe($tier)->and($asset->status)->toBe('ready')
        ->and($descriptor)->toBeInstanceOf(PlaybackDescriptor::class)->and($descriptor->type)->toBe($type)->and($descriptor->tier)->toBe($tier)
        ->and($descriptor->url ?? $descriptor->embedUrl ?? $descriptor->text)->not->toBeNull();
})->with([
    'private video' => [['provider' => 'signed_storage', 'path' => 'media/clip.mp4'], 'video', 'T1', 'signed_storage'],
    'audio' => [['provider' => 'audio', 'path' => 'media/song.mp3'], 'audio', 'T1', 'audio'],
    'document' => [['provider' => 'document', 'path' => 'media/notes.pdf'], 'document', 'T1', 'document'],
    'image' => [['provider' => 'image', 'path' => 'media/pic.png'], 'image', 'T1', 'image'],
    'unverified Vimeo' => [['provider' => 'vimeo', 'url' => 'https://vimeo.com/123456789/abcdef1234'], 'embed', 'T3', 'vimeo'],
    'youtube' => [['provider' => 'youtube', 'url' => 'https://youtu.be/dQw4w9WgXcQ'], 'embed', 'T3', 'youtube'],
    'external url' => [['provider' => 'external_url', 'url' => 'https://cdn.example.com/lesson.mp4'], 'video', 'T3', 'external_url'],
    'iframe' => [['provider' => 'iframe', 'url' => 'https://slides.example.edu/deck/1'], 'embed', 'T3', 'iframe'],
    'rich text' => [['provider' => 'rich_text', 'text' => "Warm up\nDraw a line"], 'text', 'T1', 'rich_text'],
])->group('T-P3-05', 'A1', 'YG-GAP-VID-001', 'ED-007');

it('builds the right embed links', function (): void {
    [$instructor, $lesson] = mediaLesson();

    app(AttachLessonMedia::class)($instructor, $lesson, ['provider' => 'vimeo', 'url' => 'https://vimeo.com/123456789/abcdef1234']);
    expect(LessonPlayback::for($lesson->refresh())->embedUrl)->toBe('https://player.vimeo.com/video/123456789?dnt=1&h=abcdef1234');

    app(AttachLessonMedia::class)($instructor, $lesson, ['provider' => 'youtube', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=5']);
    $descriptor = LessonPlayback::for($lesson->refresh());
    expect($descriptor->embedUrl)->toBe('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?rel=0')->and($descriptor->sandbox)->toContain('allow-scripts');
})->group('T-P3-05', 'A1');

it('treats legacy Vimeo restrictions as unverified and withholds paid playback', function (): void {
    [$instructor, $lesson, $course] = mediaLesson();
    $asset = app(AttachLessonMedia::class)($instructor, $lesson, ['provider' => 'vimeo', 'url' => 'https://vimeo.com/123456789']);
    $asset->update(['protection_tier' => MediaAsset::TIER_RESTRICTED]);
    $item = $lesson->item()->firstOrFail();

    expect(LessonPlayback::for($lesson->refresh())?->tier)->toBe(MediaAsset::TIER_OPEN);
    $course->update(['access_mode' => 'paid']);
    expect(LessonPlayback::for($lesson->refresh()))->toBeNull()
        ->and(LessonPublishRule::blocked([$item]))->toBe([$item->id])
        ->and($asset->refresh()->protection_tier)->toBe(MediaAsset::TIER_RESTRICTED);
})->group('T-P3-05', 'A2', 'YG-GAP-VID-001', 'YG-GAP-VID-002');

it('routes protected video and Vimeo fullscreen through the learner player stage', function (): void {
    $template = '<x-learning.lesson-media :descriptor="$descriptor" title="Lesson" managed-stage />';
    $video = new PlaybackDescriptor(PlaybackDescriptor::VIDEO, 'signed_storage', MediaAsset::TIER_PROTECTED, url: '/play');
    $vimeo = new PlaybackDescriptor(PlaybackDescriptor::EMBED, 'vimeo', MediaAsset::TIER_RESTRICTED, embedUrl: 'https://player.vimeo.com/video/123456789');

    $this->blade($template, ['descriptor' => $video])->assertSee('controlslist="nodownload nofullscreen"', false);
    $this->blade($template, ['descriptor' => $vimeo])->assertSee('data-player-vimeo', false)->assertDontSee('allowfullscreen', false);
})->group('T-P4-07', 'A3', 'YG-GAP-VID-003');

it('rejects bad sources for each provider', function (array $source, string $field): void {
    [$instructor, $lesson] = mediaLesson();
    Storage::disk('local')->put('media/clip.mp4', 'v');

    try {
        app(AttachLessonMedia::class)($instructor, $lesson, $source);
        $failed = [];
    } catch (ValidationException $exception) {
        $failed = array_keys($exception->errors());
    }

    expect($failed)->toContain($field)->and(MediaAsset::query()->count())->toBe(0);
})->with([
    'unknown provider' => [['provider' => 'dropbox', 'url' => 'https://x.example.com/a.mp4'], 'provider'],
    'path traversal' => [['provider' => 'signed_storage', 'path' => '../secret.mp4'], 'path'],
    'absolute path' => [['provider' => 'signed_storage', 'path' => '/etc/passwd.mp4'], 'path'],
    'wrong extension' => [['provider' => 'signed_storage', 'path' => 'media/clip.exe'], 'path'],
    'missing file' => [['provider' => 'signed_storage', 'path' => 'media/none.mp4'], 'path'],
    'document must be pdf' => [['provider' => 'document', 'path' => 'media/clip.mp4'], 'path'],
    'vimeo wrong host' => [['provider' => 'vimeo', 'url' => 'https://evil.example.com/123456789'], 'url'],
    'youtube bad id' => [['provider' => 'youtube', 'url' => 'https://youtube.com/watch?v=short'], 'url'],
    'http not https' => [['provider' => 'external_url', 'url' => 'http://cdn.example.com/a.mp4'], 'url'],
    'localhost' => [['provider' => 'external_url', 'url' => 'https://localhost/a.mp4'], 'url'],
    'private ip' => [['provider' => 'external_url', 'url' => 'https://192.168.1.5/a.mp4'], 'url'],
    'credentials in url' => [['provider' => 'external_url', 'url' => 'https://user:pw@cdn.example.com/a.mp4'], 'url'],
    'external wrong type' => [['provider' => 'external_url', 'url' => 'https://cdn.example.com/page.html'], 'url'],
    'empty text' => [['provider' => 'rich_text', 'text' => '   '], 'text'],
])->group('T-P3-05', 'A1', 'A3');

it('rejects an iframe host that is not on the allowlist, including look-alikes', function (string $url): void {
    [$instructor, $lesson] = mediaLesson();

    expect(fn () => app(AttachLessonMedia::class)($instructor, $lesson, ['provider' => 'iframe', 'url' => $url]))->toThrow(ValidationException::class);
})->with([
    'https://evil.example.com/embed', 'https://slides.example.edu.evil.com/x', 'https://notslides.example.edu/x', 'https://sub.slides.example.edu/x', 'javascript:alert(1)', 'https://slides.example.edu@evil.com/x',
])->group('T-P3-05', 'A3');

it('stops embedding a host that was removed from the allowlist', function (): void {
    [$instructor, $lesson] = mediaLesson();
    app(AttachLessonMedia::class)($instructor, $lesson, ['provider' => 'iframe', 'url' => 'https://slides.example.edu/deck/1']);
    expect(LessonPlayback::for($lesson->refresh())->sandbox)->toBe(config('media.iframe_sandbox'));

    config(['media.iframe_allowlist' => []]);
    expect(fn () => LessonPlayback::for($lesson->refresh()))->toThrow(ValidationException::class);
})->group('T-P3-05', 'A3');

it('gives protected files a signed link that expires', function (): void {
    [$instructor, $lesson] = mediaLesson();
    Storage::disk('local')->put('media/clip.mp4', 'video-bytes');
    $asset = app(AttachLessonMedia::class)($instructor, $lesson, ['provider' => 'signed_storage', 'path' => 'media/clip.mp4']);
    $url = LessonPlayback::for($lesson->refresh(), $instructor)->url;

    expect($url)->toContain('signature=')->and($url)->not->toContain('clip.mp4');
    $this->actingAs($instructor)->get($url)->assertOk();
    $this->get('/media/'.$asset->id)->assertForbidden();
    $this->get(preg_replace('/signature=[^&]+/', 'signature=tampered', $url))->assertForbidden();

    $this->travel(config('media.signed_ttl') + 5)->seconds();
    $this->get($url)->assertForbidden();
})->group('T-P3-05', 'A1', 'ED-007');

it('never serves open or unready media through the signed route', function (): void {
    [$instructor, $lesson] = mediaLesson();
    $asset = app(AttachLessonMedia::class)($instructor, $lesson, ['provider' => 'external_url', 'url' => 'https://cdn.example.com/a.mp4']);

    $this->actingAs($instructor)->get(URL::signedRoute('media.play', ['asset' => $asset->id, 'u' => $instructor->id]))->assertNotFound();
})->group('T-P3-05', 'A1');

it('lets only managing instructors attach media and audits the attachment', function (): void {
    [$instructor, $lesson] = mediaLesson();
    foreach ([User::factory()->create()->assignRole('instructor_academy'), User::factory()->create()->assignRole('student')] as $actor) {
        expect(fn () => app(AttachLessonMedia::class)($actor, $lesson, ['provider' => 'rich_text', 'text' => 'x']))->toThrow(AuthorizationException::class);
    }

    app(AttachLessonMedia::class)($instructor, $lesson, ['provider' => 'rich_text', 'text' => 'one']);
    app(AttachLessonMedia::class)($instructor, $lesson, ['provider' => 'rich_text', 'text' => 'two', 'duration_seconds' => 90]);

    expect(MediaAsset::query()->count())->toBe(1)->and($lesson->refresh()->media->duration_seconds)->toBe(90)
        ->and(Activity::query()->where('event', 'media.attached')->count())->toBe(2);
})->group('T-P3-05', 'A1', 'I02');

it('exposes nothing an AI source could use', function (): void {
    $properties = array_map(fn (ReflectionProperty $p): string => $p->getName(), (new ReflectionClass(PlaybackDescriptor::class))->getProperties());

    expect($properties)->not->toContain('transcript', 'aiSource', 'source', 'metadata');
    foreach (MediaProviders::all() as $provider) {
        expect(method_exists($provider, 'aiSource'))->toBeFalse();
    }
})->group('T-P3-05', 'ai');
