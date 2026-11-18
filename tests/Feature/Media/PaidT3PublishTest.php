<?php

declare(strict_types=1);

use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Media\Actions\AttachLessonMedia;
use App\Domain\Media\Actions\GrantMediaException;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
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
});

/** @return array{0: User, 1: Course, 2: CurriculumItem, 3: CourseSection} */
function paidLesson(string $source = 'youtube', string $access = 'paid', bool $preview = false): array
{
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['en' => 'Paid'], 'slug_ar' => 'p-'.uniqid(), 'slug_en' => 'p-'.uniqid(), 'access_mode' => $access]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'S']]);
    $item = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'L'], 'is_preview' => $preview]);

    $payload = match ($source) {
        'youtube' => ['provider' => 'youtube', 'url' => 'https://youtu.be/dQw4w9WgXcQ'],
        'vimeo' => ['provider' => 'vimeo', 'url' => 'https://vimeo.com/123456789'],
        default => ['provider' => 'rich_text', 'text' => 'plain'],
    };
    app(AttachLessonMedia::class)($instructor, $item->itemable, $payload);

    return [$instructor, $course, $item->refresh(), $section];
}

it('blocks publishing a paid lesson that uses an open source', function (string $source): void {
    [$instructor, $course, $item] = paidLesson($source);

    expect(fn () => app(SetCurriculumStatus::class)($instructor, $course, [$item->id], 'published'))->toThrow(ValidationException::class);
    expect($item->refresh()->status)->toBe('draft')->and(Activity::query()->where('event', 'curriculum.items_published')->count())->toBe(0);
})->with(['youtube', 'vimeo'])->group('T-P3-05', 'A2', 'YG-GAP-VID-002');

it('blocks publishing the whole section too', function (): void {
    [$instructor, , $item, $section] = paidLesson('youtube');

    expect(fn () => app(ManageSections::class)->setPublished($instructor, $section, true))->toThrow(ValidationException::class);
    expect($section->refresh()->published)->toBeFalse()->and($item->refresh()->status)->toBe('draft');
})->group('T-P3-05', 'A2', 'YG-GAP-VID-002');

it('allows protected video and text sources on paid lessons', function (string $source): void {
    Storage::disk('local')->put('media/clip.mp4', 'v');
    [$instructor, $course, $item] = paidLesson($source);
    if ($source === 'signed_storage') {
        app(AttachLessonMedia::class)($instructor, $item->itemable, ['provider' => 'signed_storage', 'path' => 'media/clip.mp4']);
    }

    expect(app(SetCurriculumStatus::class)($instructor, $course, [$item->id], 'published'))->toBe(1);
})->with(['signed_storage', 'text'])->group('T-P3-05', 'A2');

it('allows an open source for free courses and for free-preview lessons', function (): void {
    [$instructor, $course, $item] = paidLesson('youtube', 'free');
    expect(app(SetCurriculumStatus::class)($instructor, $course, [$item->id], 'published'))->toBe(1);

    [$instructor, $course, $item] = paidLesson('youtube', 'paid', true);
    expect(app(SetCurriculumStatus::class)($instructor, $course, [$item->id], 'published'))->toBe(1);
})->group('T-P3-05', 'A2');

it('lets an admin record an exception with a reason, audited, and then publishing works', function (): void {
    [$instructor, $course, $item] = paidLesson('youtube');
    $admin = User::factory()->create()->assignRole('admin');
    $lesson = $item->itemable;

    $media = app(GrantMediaException::class)($admin, $lesson, 'Legacy course migrated from the old site, re-upload planned.');

    expect($media->exception_reason)->toContain('Legacy course')->and($media->exception_by)->toBe($admin->id)->and($media->exception_at)->not->toBeNull();
    $entry = Activity::query()->where('event', 'media.exception_granted')->sole();
    expect($entry->causer_id)->toBe($admin->id)->and($entry->properties->get('context'))->toBe(['reason' => 'Legacy course migrated from the old site, re-upload planned.']);

    expect(app(SetCurriculumStatus::class)($instructor, $course, [$item->id], 'published'))->toBe(1);
})->group('T-P3-05', 'A2', 'YG-GAP-VID-002');

it('refuses exceptions from non-admins, without a real reason, or where none is needed', function (): void {
    [$instructor, , $item] = paidLesson('youtube');
    $lesson = $item->itemable;

    foreach ([$instructor, User::factory()->create()->assignRole('content_manager'), User::factory()->create()->assignRole('finance')] as $actor) {
        expect(fn () => app(GrantMediaException::class)($actor, $lesson, 'A perfectly good reason here.'))->toThrow(AuthorizationException::class);
    }

    $admin = User::factory()->create()->assignRole('admin');
    foreach (['', 'short', str_repeat('x', 501)] as $reason) {
        expect(fn () => app(GrantMediaException::class)($admin, $lesson, $reason))->toThrow(ValidationException::class);
    }

    [, , $protected] = paidLesson('text');
    expect(fn () => app(GrantMediaException::class)($admin, $protected->itemable, 'This one does not need an exception.'))->toThrow(ValidationException::class);
    expect($lesson->refresh()->media->exception_reason)->toBeNull();
})->group('T-P3-05', 'A2');

it('drops the exception when the media source is replaced', function (): void {
    [$instructor, $course, $item] = paidLesson('youtube');
    $admin = User::factory()->create()->assignRole('admin');
    app(GrantMediaException::class)($admin, $item->itemable, 'Approved for the migration period only.');

    app(AttachLessonMedia::class)($instructor, $item->itemable, ['provider' => 'youtube', 'url' => 'https://youtu.be/abcdefghijk']);

    expect($item->itemable->refresh()->media->hasException())->toBeFalse();
    expect(fn () => app(SetCurriculumStatus::class)($instructor, $course, [$item->id], 'published'))->toThrow(ValidationException::class);
})->group('T-P3-05', 'A2');

it('rejects an open replacement for a published paid lesson and preserves its protected media', function (): void {
    [$instructor, $course, $item] = paidLesson('text');
    Storage::disk('local')->put('media/protected.mp4', 'protected video');
    $original = app(AttachLessonMedia::class)($instructor, $item->itemable, ['provider' => 'signed_storage', 'path' => 'media/protected.mp4']);
    app(SetCurriculumStatus::class)($instructor, $course, [$item->id], 'published');
    $audits = Activity::query()->where('event', 'media.attached')->count();

    expect(fn () => app(AttachLessonMedia::class)($instructor, $item->itemable, ['provider' => 'youtube', 'url' => 'https://youtu.be/abcdefghijk']))
        ->toThrow(ValidationException::class);

    expect($item->refresh()->status)->toBe('published')
        ->and($item->itemable->media->id)->toBe($original->id)
        ->and($item->itemable->media->provider)->toBe('signed_storage')
        ->and(Activity::query()->where('event', 'media.attached')->count())->toBe($audits);
    Storage::disk('local')->assertExists('media/protected.mp4');
})->group('T-P3-05', 'A2', 'YG-GAP-VID-002');

it('rejects removing preview access from a published paid open lesson without an exception', function (): void {
    [$instructor, $course, $item] = paidLesson('youtube', 'paid', true);
    app(SetCurriculumStatus::class)($instructor, $course, [$item->id], 'published');

    expect(fn () => app(ManageLessons::class)->update($instructor, $item->itemable, ['title' => ['en' => 'Changed'], 'is_preview' => false]))
        ->toThrow(ValidationException::class);

    expect($item->refresh()->status)->toBe('published')
        ->and($item->itemable->is_preview)->toBeTrue()
        ->and($item->itemable->getTranslation('title', 'en'))->toBe('L');
})->group('T-P3-05', 'A2', 'YG-GAP-VID-002');
