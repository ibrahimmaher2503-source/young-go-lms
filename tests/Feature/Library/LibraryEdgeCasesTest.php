<?php

declare(strict_types=1);

// T-P3-13 | YG-GAP-CNT-003, ED-026, ED-031 | A1 reference safety, A2 ownership and authorization, A3 upload hygiene

use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Library\Actions\AddLibraryItem;
use App\Domain\Library\Actions\AttachLibraryItem;
use App\Domain\Library\Actions\DeleteLibraryItem;
use App\Domain\Library\LibraryAccess;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LibraryItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\Support\PdfFixture;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');
});

function lxBytes(string $contents, string $suffix = ''): File
{
    $path = tempnam(sys_get_temp_dir(), 'lx').$suffix;
    file_put_contents($path, $contents);

    return new File($path);
}

function lxPdf(string $marker = ''): string
{
    return PdfFixture::bytes(marker: $marker);
}

/** @return array{0: User, 1: Lesson} */
function lxLesson(?User $instructor = null): array
{
    $instructor ??= User::factory()->create()->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['en' => 'Lib edges'], 'slug_ar' => 'lx-'.uniqid(), 'slug_en' => 'lx-'.uniqid()]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'S']]);
    $lesson = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'L']])->itemable;

    return [$instructor, $lesson];
}

it('trims titles, keeps the literal title zero and normalizes tags', function (): void {
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $add = app(AddLibraryItem::class);

    $item = $add($instructor, lxBytes(lxPdf()), [
        'title' => ['en' => '  Sheet  ', 'ar' => '   '],
        'tags' => [' Intro ', 'intro', '', '   ', ' MATH '],
    ]);
    $zero = $add($instructor, lxBytes(lxPdf()), ['title' => ['en' => '0']]);

    expect($item->getTranslations('title'))->toBe(['en' => 'Sheet'])
        ->and($item->tags)->toBe(['intro', 'math'])
        ->and($zero->getTranslations('title'))->toBe(['en' => '0']);

    foreach ([
        ['title' => ['en' => '  ']],
        ['title' => ['en' => str_repeat('a', 151)]],
        ['title' => ['en' => 'Tags'], 'tags' => array_map(static fn (int $i): string => 't'.$i, range(1, 11))],
        ['title' => ['en' => 'Tags'], 'tags' => [str_repeat('t', 41)]],
    ] as $meta) {
        expect(fn () => $add($instructor, lxBytes(lxPdf()), $meta))->toThrow(ValidationException::class);
    }
    expect(LibraryItem::query()->count())->toBe(2);
});

it('rejects empty, oversized and unknown files and trusts content over the file name', function (): void {
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $add = app(AddLibraryItem::class);
    config()->set('media.max_kilobytes', 1);

    expect(fn () => $add($instructor, lxBytes(str_repeat('x', 2048)), ['title' => ['en' => 'Big']]))->toThrow(ValidationException::class)
        ->and(fn () => $add($instructor, lxBytes(''), ['title' => ['en' => 'Empty']]))->toThrow(ValidationException::class)
        ->and(fn () => $add($instructor, lxBytes("MZ\x90\x00\x03\x00\x00\x00"), ['title' => ['en' => 'Exe']]))->toThrow(ValidationException::class)
        ->and(LibraryItem::query()->count())->toBe(0);

    $disguised = $add($instructor, lxBytes('plain notes', '.pdf'), ['title' => ['en' => 'Notes']]);

    expect($disguised->kind)->toBe('file')->and($disguised->mime)->toBe('text/plain')->and($disguised->path)->toEndWith('.txt');
});

it('re-encodes images so appended bytes and metadata never reach storage', function (): void {
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    $item = app(AddLibraryItem::class)($instructor, lxBytes($png.'SECRET_MARKER'), ['title' => ['en' => 'Pixel']]);
    $stored = Storage::disk('local')->get($item->path);

    expect($item->kind)->toBe('image')->and($item->mime)->toBeIn(['image/png', 'image/jpeg'])
        ->and($stored)->not->toContain('SECRET_MARKER')
        ->and($item->size)->toBe(strlen($stored))
        ->and($item->sha256)->toBe(hash('sha256', $stored));
});

it('attaches a library document as protected media with the requested duration', function (): void {
    [$instructor, $lesson] = lxLesson();
    $item = app(AddLibraryItem::class)($instructor, lxBytes(lxPdf()), ['title' => ['en' => 'Slides']]);

    $media = app(AttachLibraryItem::class)->asMedia($instructor, $item, $lesson, 300);

    expect($media->provider)->toBe('document')->and($media->path)->toBe($item->path)
        ->and($media->duration_seconds)->toBe(300)->and($media->library_item_id)->toBe($item->id)
        ->and($item->refresh()->referenceCount())->toBe(1);

    expect(fn () => app(DeleteLibraryItem::class)($instructor, $item))->toThrow(ValidationException::class);
});

it('refuses a curriculum PDF copy when the library file is gone', function (): void {
    [$instructor, $lesson] = lxLesson();
    $item = app(AddLibraryItem::class)($instructor, lxBytes(lxPdf()), ['title' => ['en' => 'Lost']]);
    Storage::disk('local')->delete($item->path);

    expect(fn () => app(AttachLibraryItem::class)->asCurriculumPdf($instructor, $item, $lesson))->toThrow(ValidationException::class);
});

it('hides the library from inactive staff even when they hold the permission', function (): void {
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $admin = User::factory()->create()->assignRole('admin');
    $item = app(AddLibraryItem::class)($instructor, lxBytes(lxPdf()), ['title' => ['en' => 'Mine']]);
    $admin->forceFill(['status' => 'suspended'])->save();
    $instructor->forceFill(['status' => 'suspended'])->save();

    expect(LibraryAccess::managesAll($admin))->toBeFalse()
        ->and(LibraryAccess::mayUseLibrary($instructor))->toBeFalse()
        ->and(LibraryAccess::visibleTo($admin)->count())->toBe(0)
        ->and(LibraryAccess::visibleTo($instructor)->count())->toBe(0)
        ->and(LibraryAccess::canUse($instructor, $item))->toBeFalse()
        ->and(fn () => app(DeleteLibraryItem::class)($instructor, $item))->toThrow(NotFoundHttpException::class)
        ->and(LibraryItem::query()->count())->toBe(1);

    $activeAdmin = User::factory()->create()->assignRole('admin');
    app(DeleteLibraryItem::class)($activeAdmin, $item);
    expect(LibraryItem::query()->count())->toBe(0);
});

it('keeps an academy item undeletable by the instructor who owns the upload', function (): void {
    [$instructor] = lxLesson();
    $admin = User::factory()->create()->assignRole('admin');
    $item = app(AddLibraryItem::class)($admin, lxBytes(lxPdf()), ['title' => ['en' => 'Academy'], 'scope' => 'academy']);

    expect($item->owner_id)->toBeNull()
        ->and(LibraryAccess::canUse($instructor, $item))->toBeTrue()
        ->and(LibraryAccess::canDelete($instructor, $item))->toBeFalse()
        ->and(fn () => app(DeleteLibraryItem::class)($instructor, $item))->toThrow(AuthorizationException::class)
        ->and(LibraryItem::query()->count())->toBe(1);
});
