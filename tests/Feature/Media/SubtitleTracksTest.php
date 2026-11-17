<?php

declare(strict_types=1);

use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Entitlements\Contracts\LessonAccessChecker;
use App\Domain\Media\Actions\AttachLessonMedia;
use App\Domain\Media\Actions\ManageSubtitleTracks;
use App\Domain\Media\Queries\LessonPlayback;
use App\Domain\Media\VttValidator;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\SubtitleTrack;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\File\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');
});

function stGood(string $text = 'Hello'): string
{
    return "WEBVTT\n\n00:00:01.000 --> 00:00:03.500\n{$text}\n\n00:00:04.000 --> 00:00:06.000\nSecond line\n";
}

function stFile(string $contents): File
{
    $path = tempnam(sys_get_temp_dir(), 'vtt');
    file_put_contents($path, $contents);

    return new File($path);
}

/** @return array{0: User, 1: Lesson, 2: Course} a ready video lesson managed by the returned instructor */
function stLesson(string $type = 'video'): array
{
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['en' => 'Subs'], 'slug_ar' => 's-'.uniqid(), 'slug_en' => 's-'.uniqid()]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'S']]);
    $item = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'L'], 'type' => $type]);
    /** @var Lesson $lesson */
    $lesson = $item->itemable;
    Storage::disk('local')->put('media/clip.mp4', 'v');
    if ($type === 'video') {
        app(AttachLessonMedia::class)($instructor, $lesson, ['provider' => 'signed_storage', 'path' => 'media/clip.mp4']);
    }

    return [$instructor, $lesson->refresh(), $course];
}

function stEntitle(bool $allowed): void
{
    app()->bind(LessonAccessChecker::class, fn () => new class($allowed) implements LessonAccessChecker
    {
        public function __construct(private bool $allowed) {}

        public function canAccessLessonFiles(User $user, Lesson $lesson): bool
        {
            return $this->allowed;
        }
    });
}

it('rejects invalid VTT files and stores nothing', function (string $contents, string $reason): void {
    [$instructor, $lesson] = stLesson();

    expect(fn () => app(ManageSubtitleTracks::class)->add($instructor, $lesson, 'en', 'English', stFile($contents)))
        ->toThrow(ValidationException::class, __('media.vtt.'.$reason))
        ->and(SubtitleTrack::query()->count())->toBe(0)->and(Storage::disk('local')->allFiles('subtitles'))->toBe([]);
})->with([
    'empty' => ['', 'size'],
    'not utf-8' => ["WEBVTT\n\n00:00:01.000 --> 00:00:02.000\n\xFF\xFE\n", 'encoding'],
    'wrong signature' => ["1\n00:00:01,000 --> 00:00:02,000\nSRT style\n", 'signature'],
    'script markup' => ["WEBVTT\n\n00:00:01.000 --> 00:00:02.000\n<script>alert(1)</script>\n", 'markup'],
    'end before start' => ["WEBVTT\n\n00:00:05.000 --> 00:00:02.000\nBackwards\n", 'timing'],
    'cues out of order' => ["WEBVTT\n\n00:00:05.000 --> 00:00:06.000\nB\n\n00:00:01.000 --> 00:00:02.000\nA\n", 'timing'],
    'no cues' => ["WEBVTT\n\nNOTE only a note\n", 'no_cues'],
    'bad timestamp' => ["WEBVTT\n\n0:1.0 --> 0:2.0\nBad\n", 'no_cues'],
])->group('T-P3-14', 'A1', 'YG-GAP-LRN-003');

it('accepts valid VTT with BOM, short timestamps and cue settings', function (): void {
    expect(VttValidator::problem(stGood()))->toBeNull()
        ->and(VttValidator::problem("\xEF\xBB\xBFWEBVTT - title\n\n01:02.000 --> 01:04.000 align:start\nArabic: مرحبا\n"))->toBeNull()
        ->and(VttValidator::problem("WEBVTT\r\n\r\n00:00:01.000 --> 00:00:02.000\r\nCRLF\r\n"))->toBeNull()
        ->and(VttValidator::problem(str_repeat('a', VttValidator::MAX_BYTES + 1)))->toBe('size');
})->group('T-P3-14', 'A1', 'ED-008');

it('keeps one track per language, a single default, and cleans up files', function (): void {
    [$instructor, $lesson] = stLesson();
    $subs = app(ManageSubtitleTracks::class);

    $ar = $subs->add($instructor, $lesson, 'ar', 'العربية', stFile(stGood('مرحبا')));
    $en = $subs->add($instructor, $lesson, 'en', 'English', stFile(stGood()));
    expect($ar->is_default)->toBeTrue()->and($en->is_default)->toBeFalse();

    $subs->setDefault($instructor, $en);
    expect($en->refresh()->is_default)->toBeTrue()->and($ar->refresh()->is_default)->toBeFalse();

    $oldPath = $en->path;
    $replaced = $subs->add($instructor, $lesson, 'en', 'English (fixed)', stFile(stGood('Fixed')));
    expect(SubtitleTrack::query()->count())->toBe(2)->and($replaced->label)->toBe('English (fixed)')->and($replaced->is_default)->toBeTrue()
        ->and(Storage::disk('local')->exists($oldPath))->toBeFalse()->and(Storage::disk('local')->exists($replaced->path))->toBeTrue();

    $subs->remove($instructor, $replaced);
    expect(SubtitleTrack::query()->count())->toBe(1)->and($ar->refresh()->is_default)->toBeTrue()
        ->and(Storage::disk('local')->exists($replaced->path))->toBeFalse();
})->group('T-P3-14', 'A1', 'ED-008');

it('refuses other lesson types, other languages, bad labels and unauthorized people', function (): void {
    [$instructor, $lesson, $course] = stLesson();
    $subs = app(ManageSubtitleTracks::class);

    expect(fn () => $subs->add($instructor, $lesson, 'fr', 'Francais', stFile(stGood())))->toThrow(ValidationException::class)
        ->and(fn () => $subs->add($instructor, $lesson, 'en', '  ', stFile(stGood())))->toThrow(ValidationException::class)
        ->and(fn () => $subs->add(User::factory()->create()->assignRole('student'), $lesson, 'en', 'English', stFile(stGood())))->toThrow(AuthorizationException::class);

    $viewOnly = User::factory()->create()->assignRole('instructor_academy');
    $course->instructors()->attach($viewOnly->id, ['role' => 'instructor', 'scope' => 'view']);
    expect(fn () => $subs->add($viewOnly, $lesson, 'en', 'English', stFile(stGood())))->toThrow(AuthorizationException::class);

    [$textInstructor, $textLesson] = stLesson('text');
    expect(fn () => $subs->add($textInstructor, $textLesson, 'en', 'English', stFile(stGood())))->toThrow(ValidationException::class, __('media.vtt.lesson_type'));
})->group('T-P3-14', 'A1', 'ED-008');

it('puts tracks in the descriptor only for viewers who may open the lesson files', function (): void {
    [$instructor, $lesson] = stLesson();
    $subs = app(ManageSubtitleTracks::class);
    $subs->add($instructor, $lesson, 'ar', 'العربية', stFile(stGood('مرحبا')));
    $subs->add($instructor, $lesson, 'en', 'English', stFile(stGood()));
    $student = User::factory()->create()->assignRole('student');

    stEntitle(true);
    $descriptor = LessonPlayback::for($lesson->refresh(), $student);
    expect($descriptor->tracks)->toHaveCount(2)->and($descriptor->tracks[0]['locale'])->toBe('ar')->and($descriptor->tracks[0]['default'])->toBeTrue()
        ->and($descriptor->tracks[0]['url'])->toContain('/files/subtitle/')->and($descriptor->toArray()['tracks'])->toHaveCount(2);

    stEntitle(false);
    expect(LessonPlayback::for($lesson->refresh(), $student)->tracks)->toBe([])
        ->and(LessonPlayback::for($lesson->refresh())->tracks)->toBe([]); // no viewer, no links
})->group('T-P3-14', 'A2', 'ED-008');

it('serves a caption link to its account only and refuses it to others or after access is lost', function (): void {
    [$instructor, $lesson] = stLesson();
    app(ManageSubtitleTracks::class)->add($instructor, $lesson, 'en', 'English', stFile(stGood('Served line')));
    $student = User::factory()->create()->assignRole('student');
    $other = User::factory()->create()->assignRole('student');

    stEntitle(true);
    $url = LessonPlayback::for($lesson->refresh(), $student)->tracks[0]['url'];

    $this->actingAs($student)->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->actingAs($other)->get($url)->assertForbidden();

    stEntitle(false);
    $this->actingAs($student)->get($url)->assertForbidden();
})->group('T-P3-14', 'A2', 'ED-008');
