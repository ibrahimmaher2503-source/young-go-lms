<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\DeleteCourse;
use App\Domain\Catalog\Actions\DuplicateCourse;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Library\Actions\AddLibraryItem;
use App\Domain\Library\Actions\AttachLibraryItem;
use App\Domain\Library\Actions\DeleteLibraryItem;
use App\Domain\Library\LibraryAccess;
use App\Domain\Media\Actions\AttachLessonMedia;
use App\Domain\Media\Actions\ManageLessonResources;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\LessonResource;
use App\Models\LibraryItem;
use App\Models\MediaAsset;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');
});

function lbFile(string $contents, string $suffix = ''): File
{
    $path = tempnam(sys_get_temp_dir(), 'lb').$suffix;
    file_put_contents($path, $contents);

    return new File($path);
}

function lbPdf(): string
{
    return "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";
}

/** @return array{0: Course, 1: User, 2: list<Lesson>} a course managed by the returned instructor with two lessons */
function lbCourse(?User $instructor = null): array
{
    $instructor ??= User::factory()->create()->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['en' => 'Lib'], 'slug_ar' => 'lb-'.uniqid(), 'slug_en' => 'lb-'.uniqid()]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'S']]);
    $lessons = [
        app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'One'], 'type' => 'video'])->itemable,
        app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Two'], 'type' => 'video'])->itemable,
    ];

    return [$course, $instructor, $lessons];
}

it('attaches one library file to two lessons by reference and blocks deletion while used', function (): void {
    [, $instructor, $lessons] = lbCourse();
    $item = app(AddLibraryItem::class)($instructor, lbFile(lbPdf()), ['title' => ['en' => 'Worksheet', 'ar' => 'ورقة عمل'], 'tags' => ['Intro', 'intro'], 'scope' => 'mine']);
    $attach = app(AttachLibraryItem::class);

    expect($item->kind)->toBe('document')->and($item->tags)->toBe(['intro'])->and($item->owner_id)->toBe($instructor->id);
    Storage::disk('local')->assertExists($item->path);

    $first = $attach->asResource($instructor, $item, $lessons[0]);
    $second = $attach->asResource($instructor, $item, $lessons[1], ['title' => ['en' => 'Worksheet again']]);
    $media = $attach->asMedia($instructor, $item, $lessons[1]);

    expect($first->path)->toBe($item->path)->and($second->path)->toBe($item->path)->and($media->path)->toBe($item->path)
        ->and($first->library_item_id)->toBe($item->id)->and($second->getTranslation('title', 'en'))->toBe('Worksheet again')->and($first->getTranslation('title', 'ar'))->toBe('ورقة عمل')
        ->and($item->refresh()->referenceCount())->toBe(3);

    expect(fn () => app(DeleteLibraryItem::class)($instructor, $item))->toThrow(ValidationException::class, '3 lesson');
    Storage::disk('local')->assertExists($item->path);

    // Removing a reference never deletes the library file, and the item frees up once nothing uses it.
    app(ManageLessonResources::class)->remove($instructor, $first);
    Storage::disk('local')->assertExists($item->path);
    $second->delete();
    MediaAsset::query()->delete();
    expect($item->refresh()->referenceCount())->toBe(0);

    app(DeleteLibraryItem::class)($instructor, $item);
    expect(LibraryItem::query()->count())->toBe(0);
    Storage::disk('local')->assertMissing($item->path);
    expect(Activity::query()->where('event', 'library.item_added')->count())->toBe(1)->and(Activity::query()->where('event', 'library.item_deleted')->count())->toBe(1);
})->group('T-P3-13', 'A1', 'YG-GAP-CNT-003', 'ED-026');

it('replacing a lesson media keeps the library file', function (): void {
    [, $instructor, $lessons] = lbCourse();
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    $item = app(AddLibraryItem::class)($instructor, lbFile($png), ['title' => ['en' => 'Pic'], 'scope' => 'mine']);
    expect($item->kind)->toBe('image')->and($item->mime)->toBeIn(['image/png', 'image/jpeg']);

    app(AttachLibraryItem::class)->asMedia($instructor, $item, $lessons[0]);
    Storage::disk('local')->put('media/other.mp4', 'v');
    app(AttachLessonMedia::class)($instructor, $lessons[0], ['provider' => 'signed_storage', 'path' => 'media/other.mp4']);

    Storage::disk('local')->assertExists($item->path);
    expect($item->refresh()->referenceCount())->toBe(0);
})->group('T-P3-13', 'A1');

it('keeps private items private: instructors see the academy items and their own only', function (): void {
    $mine = User::factory()->create()->assignRole('instructor_academy');
    $other = User::factory()->create()->assignRole('instructor_academy');
    $admin = User::factory()->create()->assignRole('admin');
    $add = app(AddLibraryItem::class);

    $academy = $add($admin, lbFile(lbPdf()), ['title' => ['en' => 'Shared sheet'], 'scope' => 'academy']);
    $own = $add($mine, lbFile(lbPdf().' '), ['title' => ['en' => 'My sheet'], 'scope' => 'mine']);
    $theirs = $add($other, lbFile(lbPdf().'  '), ['title' => ['en' => 'Their sheet'], 'scope' => 'mine']);

    expect(LibraryAccess::visibleTo($mine)->pluck('id')->sort()->values()->all())->toBe([$academy->id, $own->id])
        ->and(LibraryAccess::visibleTo($other)->pluck('id')->sort()->values()->all())->toBe([$academy->id, $theirs->id])
        ->and(LibraryAccess::visibleTo($admin)->count())->toBe(3)
        ->and(LibraryAccess::visibleTo(User::factory()->create()->assignRole('student'))->count())->toBe(0);

    [, , $lessons] = lbCourse($mine);
    expect(fn () => app(AttachLibraryItem::class)->asResource($mine, $theirs, $lessons[0]))->toThrow(NotFoundHttpException::class)
        ->and(fn () => app(DeleteLibraryItem::class)($mine, $theirs))->toThrow(NotFoundHttpException::class)
        ->and(app(AttachLibraryItem::class)->asResource($mine, $academy, $lessons[0])->library_item_id)->toBe($academy->id);

    // Academy items are removed only by staff with the media permission; an instructor cannot share with the whole academy.
    expect(fn () => app(DeleteLibraryItem::class)($mine, $academy))->toThrow(AuthorizationException::class)
        ->and(fn () => $add($mine, lbFile(lbPdf().'   '), ['title' => ['en' => 'x'], 'scope' => 'academy']))->toThrow(AuthorizationException::class)
        ->and(fn () => $add(User::factory()->create()->assignRole('student'), lbFile(lbPdf()), ['title' => ['en' => 'x']]))->toThrow(AuthorizationException::class);
    app(DeleteLibraryItem::class)($mine, $own);
    app(DeleteLibraryItem::class)($admin, $theirs);
    expect(LibraryItem::query()->count())->toBe(1);
})->group('T-P3-13', 'A2');

it('needs permission on the lesson, valid files and sensible uses', function (): void {
    [, $instructor, $lessons] = lbCourse();
    $stranger = User::factory()->create()->assignRole('instructor_academy');
    $add = app(AddLibraryItem::class);
    $pdf = $add($instructor, lbFile(lbPdf()), ['title' => ['en' => 'Sheet']]);

    expect(fn () => $add($instructor, lbFile('MZ � �� binary'), ['title' => ['en' => 'Bad']]))->toThrow(ValidationException::class)
        ->and(fn () => $add($instructor, lbFile(lbPdf().'x'), ['title' => []]))->toThrow(ValidationException::class)
        ->and(LibraryItem::query()->count())->toBe(1);

    $theirs = $add($stranger, lbFile(lbPdf().'y'), ['title' => ['en' => 'Mine']]);
    expect(fn () => app(AttachLibraryItem::class)->asResource($stranger, $theirs, $lessons[0]))->toThrow(AuthorizationException::class); // not allowed to edit that course

    $text = $add($instructor, lbFile('plain notes'), ['title' => ['en' => 'Notes']]);
    expect($text->kind)->toBe('file')
        ->and(fn () => app(AttachLibraryItem::class)->asMedia($instructor, $text, $lessons[0]))->toThrow(ValidationException::class)
        ->and(fn () => app(AttachLibraryItem::class)->asCurriculumPdf($instructor, $text, $lessons[0]))->toThrow(ValidationException::class);
})->group('T-P3-13', 'A2');

it('uses a library PDF as the curriculum PDF through the normal upload and approval pipeline', function (): void {
    [, $instructor, $lessons] = lbCourse();
    $pdf = app(AddLibraryItem::class)($instructor, lbFile(lbPdf()), ['title' => ['en' => 'Lesson sheet']]);

    $document = app(AttachLibraryItem::class)->asCurriculumPdf($instructor, $pdf, $lessons[0]);

    expect($document->status)->toBe(LessonCurriculumDocument::STATUS_UPLOADED)->and($document->active)->toBeFalse()->and($document->path)->not->toBe($pdf->path)
        ->and($lessons[0]->activeCurriculumDocument())->toBeNull();
    Storage::disk('local')->assertExists($document->path);
    Storage::disk('local')->assertExists($pdf->path);
    expect($pdf->refresh()->referenceCount())->toBe(0); // a pipeline copy, not a reference
})->group('T-P3-13', 'A1', 'ED-026');

it('shares library files by reference when a course is duplicated and never deletes them with a course', function (): void {
    $admin = User::factory()->create()->assignRole('admin');
    [$course, $instructor, $lessons] = lbCourse();
    $item = app(AddLibraryItem::class)($admin, lbFile(lbPdf()), ['title' => ['en' => 'Shared sheet'], 'scope' => 'academy']);
    app(AttachLibraryItem::class)->asResource($instructor, $item, $lessons[0]);

    $copy = app(DuplicateCourse::class)($admin, $course);
    $copied = LessonResource::query()->where('library_item_id', $item->id)->get();
    expect($copied)->toHaveCount(2)->and($copied->pluck('path')->unique()->all())->toBe([$item->path]);
    Storage::disk('local')->assertExists($item->path);

    app(DeleteCourse::class)($admin, $copy);
    app(DeleteCourse::class)($admin, $course);
    Storage::disk('local')->assertExists($item->path);
    expect(LessonResource::query()->count())->toBe(0)->and($item->refresh()->referenceCount())->toBe(0);
    app(DeleteLibraryItem::class)($admin, $item);
    Storage::disk('local')->assertMissing($item->path);
})->group('T-P3-13', 'A1');
