<?php

declare(strict_types=1);

use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Media\Actions\UploadLessonMedia;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\MediaAsset;
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

function uploadFixture(string $kind): File
{
    $bytes = match ($kind) {
        'mp4' => "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom".str_repeat("\0", 64),
        'pdf' => "%PDF-1.4\n1 0 obj<< /Type /Catalog >>endobj\ntrailer<< /Root 1 0 R >>\n%%EOF\n",
        'exe' => "MZ\x90\x00\x03\x00\x00\x00\x04\x00\x00\x00\xFF\xFF".str_repeat("\0", 200),
        'text' => "just some text pretending to be a video\n",
        'jpeg' => (static function (): string {
            $image = imagecreatetruecolor(40, 30);
            imagefill($image, 0, 0, 0x336699);
            ob_start();
            imagejpeg($image);
            $jpeg = (string) ob_get_clean();
            $exif = "Exif\0\0GPSLatitude-secret-31.2N";

            // Insert an EXIF (APP1) segment right after the start-of-image marker.
            return substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($exif) + 2).$exif.substr($jpeg, 2);
        })(),
        'png' => (static function (): string {
            $image = imagecreatetruecolor(20, 20);
            imagealphablending($image, false);
            imagesavealpha($image, true);
            imagefill($image, 0, 0, imagecolorallocatealpha($image, 10, 20, 30, 60));
            ob_start();
            imagepng($image);

            return (string) ob_get_clean();
        })(),
    };
    $path = tempnam(sys_get_temp_dir(), 'ygm');
    file_put_contents($path, $bytes);

    return new File($path);
}

function uploadLesson(): array
{
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['en' => 'Up'], 'slug_ar' => 'u-'.uniqid(), 'slug_en' => 'u-'.uniqid()]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'S']]);
    $item = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'L']]);

    return [$instructor, $item->itemable];
}

it('stores an upload privately under a random name and attaches it as protected media', function (string $provider, string $fixture, string $extension): void {
    [$instructor, $lesson] = uploadLesson();

    $asset = app(UploadLessonMedia::class)($instructor, $lesson, $provider, uploadFixture($fixture), 'My Holiday Video.'.$extension, 125);

    expect($asset->protection_tier)->toBe('T1')->and($asset->status)->toBe('ready')->and($asset->duration_seconds)->toBe(125)
        ->and($asset->path)->toMatch('#^media/[0-9a-f-]{36}\.'.($provider === 'image' ? '(jpg|png)' : $extension).'$#')
        ->and($asset->path)->not->toContain('Holiday');
    Storage::disk('local')->assertExists($asset->path);
    Storage::disk('public')->assertMissing($asset->path);
})->with([
    'video' => ['signed_storage', 'mp4', 'mp4'],
    'document' => ['document', 'pdf', 'pdf'],
    'image' => ['image', 'jpeg', 'jpg'],
])->group('T-P3-06', 'A1', 'PRD-09.8');

it('rejects files whose content is not what the name or source claims', function (string $provider, string $fixture, string $clientName): void {
    [$instructor, $lesson] = uploadLesson();

    expect(fn () => app(UploadLessonMedia::class)($instructor, $lesson, $provider, uploadFixture($fixture), $clientName))->toThrow(ValidationException::class);
    expect(MediaAsset::query()->count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'executable renamed to mp4' => ['signed_storage', 'exe', 'clip.mp4'],
    'text renamed to mp4' => ['signed_storage', 'text', 'clip.mp4'],
    'pdf offered as a video' => ['signed_storage', 'pdf', 'clip.mp4'],
    'video offered as a pdf' => ['document', 'mp4', 'notes.pdf'],
    'honest exe' => ['signed_storage', 'exe', 'setup.exe'],
    'real mp4 with a php name' => ['signed_storage', 'mp4', 'shell.php'],
    'link provider' => ['youtube', 'mp4', 'clip.mp4'],
    'unknown provider' => ['dropbox', 'mp4', 'clip.mp4'],
])->group('T-P3-06', 'A1');

it('rejects empty and oversized files', function (): void {
    [$instructor, $lesson] = uploadLesson();
    config(['media.max_kilobytes' => 1]);
    $big = uploadFixture('mp4');
    file_put_contents($big->getPathname(), "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom".str_repeat("\0", 2048));
    $empty = uploadFixture('mp4');
    file_put_contents($empty->getPathname(), '');
    clearstatcache();

    foreach ([new File($big->getPathname()), new File($empty->getPathname())] as $file) {
        expect(fn () => app(UploadLessonMedia::class)($instructor, $lesson, 'signed_storage', $file, 'clip.mp4'))->toThrow(ValidationException::class);
    }
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->group('T-P3-06', 'A1');

it('strips camera metadata by re-encoding images', function (): void {
    [$instructor, $lesson] = uploadLesson();
    $source = uploadFixture('jpeg');
    expect(file_get_contents($source->getPathname()))->toContain('GPSLatitude-secret');

    $asset = app(UploadLessonMedia::class)($instructor, $lesson, 'image', $source, 'photo.jpg');
    $stored = Storage::disk('local')->get($asset->path);

    expect($stored)->not->toContain('GPSLatitude-secret')->and($stored)->not->toContain('Exif')->and(str_starts_with($stored, "\xFF\xD8"))->toBeTrue();
})->group('T-P3-06', 'A1');

it('keeps transparency for PNG images', function (): void {
    [$instructor, $lesson] = uploadLesson();

    $asset = app(UploadLessonMedia::class)($instructor, $lesson, 'image', uploadFixture('png'), 'logo.png');

    expect($asset->path)->toEndWith('.png')->and(str_starts_with(Storage::disk('local')->get($asset->path), "\x89PNG"))->toBeTrue();
})->group('T-P3-06', 'A1');

it('refuses a user who cannot manage the course before writing any file', function (): void {
    [, $lesson] = uploadLesson();

    foreach ([User::factory()->create()->assignRole('instructor_academy'), User::factory()->create()->assignRole('student')] as $actor) {
        expect(fn () => app(UploadLessonMedia::class)($actor, $lesson, 'signed_storage', uploadFixture('mp4'), 'clip.mp4'))->toThrow(AuthorizationException::class);
    }
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->group('T-P3-06', 'A1', 'I02');

it('deletes the replaced file when a new upload replaces it', function (): void {
    [$instructor, $lesson] = uploadLesson();
    $first = app(UploadLessonMedia::class)($instructor, $lesson, 'signed_storage', uploadFixture('mp4'), 'a.mp4');
    $second = app(UploadLessonMedia::class)($instructor, $lesson->refresh(), 'signed_storage', uploadFixture('mp4'), 'b.mp4');

    Storage::disk('local')->assertMissing($first->path);
    Storage::disk('local')->assertExists($second->path);
    expect(Lesson::query()->find($lesson->id)->media->path)->toBe($second->path);
})->group('T-P3-06', 'A2');
