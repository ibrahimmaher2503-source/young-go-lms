<?php

// T-P3-03 | PRD 9.2 9.4 | A4 (media and SEO values; screenshots are out of scope here)

declare(strict_types=1);

use App\Domain\Catalog\Actions\SaveCourseWizard;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    config(['markets.EG.active' => true]);
    Storage::fake('public');
});

function t303mUser(): User
{
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole('instructor_independent');

    return $user;
}

function t303mDraft(User $instructor): Course
{
    $category = Category::query()->create(['name' => ['ar' => 'فنون', 'en' => 'Arts'], 'slug_ar' => 'فنون-'.uniqid(), 'slug_en' => 'arts-'.uniqid(), 'sort' => 1, 'status' => 'active']);

    return app(SaveCourseWizard::class)($instructor, [
        'title' => ['ar' => 'الرسم', 'en' => 'Media course'],
        'short_description' => ['ar' => 'مقدمة', 'en' => 'Intro'],
        'category_id' => $category->id, 'level' => 'beginner', 'content_language' => 'en', 'format' => 'recorded',
        'min_age' => 7, 'max_age' => 12,
    ], null, 1, true);
}

function t303mMediaStep(User $instructor, Course $course, array $data): Course
{
    return app(SaveCourseWizard::class)($instructor, $data, $course, 4, false);
}

/** @return array{0: int, 1: int} */
function t303mSize(string $path): array
{
    $info = getimagesizefromstring((string) Storage::disk('public')->get($path));

    return [$info[0], $info[1]];
}

it('stores an uploaded jpeg, png or webp cover on the public disk', function (string $name): void {
    $instructor = t303mUser();
    $course = t303mDraft($instructor);

    $saved = t303mMediaStep($instructor, $course, ['cover_image' => UploadedFile::fake()->image($name, 1280, 720)]);

    expect($saved->cover_image)->toStartWith('courses/')
        ->and(Storage::disk('public')->exists($saved->cover_image))->toBeTrue();
})->with(['c.jpg', 'c.png', 'c.webp'])->group('T-P3-03', 'A4');

it('crops a square upload to 16:9', function (): void {
    $instructor = t303mUser();
    $course = t303mDraft($instructor);

    $saved = t303mMediaStep($instructor, $course, ['cover_image' => UploadedFile::fake()->image('square.jpg', 1000, 1000)]);

    [$width, $height] = t303mSize($saved->cover_image);
    expect($width / $height)->toBeGreaterThan(1.76)->toBeLessThan(1.80);
})->group('T-P3-03', 'A4');

it('refuses covers narrower than 640px, text renamed to jpg, and files over 4 MB', function (Closure $file, string $messageKey): void {
    $instructor = t303mUser();
    $course = t303mDraft($instructor);

    expect(fn () => t303mMediaStep($instructor, $course, ['cover_image' => $file()]))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('cover_image')
            ->and($e->errors()['cover_image'][0])->toBe(__($messageKey, ['width' => 640])));

    expect($course->fresh()->cover_image)->toBeNull();
})->with([
    'too narrow' => [fn () => UploadedFile::fake()->image('small.jpg', 400, 400), 'course_wizard.cover_too_small'],
    'text renamed to jpg' => [fn () => UploadedFile::fake()->createWithContent('fake.jpg', 'just some text, not an image'), 'media.invalid_type'],
    'over 4 MB' => [fn () => UploadedFile::fake()->create('huge.jpg', 4200, 'image/jpeg'), 'media.too_large'],
])->group('T-P3-03', 'A4');

it('deletes the old file on replace and clears the column and file on removal', function (): void {
    $instructor = t303mUser();
    $course = t303mDraft($instructor);
    $first = t303mMediaStep($instructor, $course, ['cover_image' => UploadedFile::fake()->image('a.jpg', 1280, 720)])->cover_image;

    $second = t303mMediaStep($instructor, $course->fresh(), ['cover_image' => UploadedFile::fake()->image('b.jpg', 1280, 720)])->cover_image;
    expect($second)->not->toBe($first)
        ->and(Storage::disk('public')->exists($first))->toBeFalse()
        ->and(Storage::disk('public')->exists($second))->toBeTrue();

    $removed = t303mMediaStep($instructor, $course->fresh(), ['cover_image' => []]);
    expect($removed->cover_image)->toBeNull()
        ->and(Storage::disk('public')->exists($second))->toBeFalse();
})->group('T-P3-03', 'A4');

it('treats resubmitting the stored path as no change and refuses a foreign path', function (): void {
    $instructor = t303mUser();
    $course = t303mDraft($instructor);
    $stored = t303mMediaStep($instructor, $course, ['cover_image' => UploadedFile::fake()->image('a.jpg', 1280, 720)])->cover_image;

    $same = t303mMediaStep($instructor, $course->fresh(), ['cover_image' => [$stored]]);
    expect($same->cover_image)->toBe($stored)
        ->and(Storage::disk('public')->exists($stored))->toBeTrue();

    expect(fn () => t303mMediaStep($instructor, $course->fresh(), ['cover_image' => ['courses/someone-else.jpg']]))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('cover_image'));
})->group('T-P3-03', 'A4');

it('accepts YouTube, Vimeo and https mp4 promo videos and stores provider and source url', function (array $input, string $provider, string $sourceUrl): void {
    $instructor = t303mUser();
    $course = t303mDraft($instructor);

    $saved = t303mMediaStep($instructor, $course, ['promo_video' => $input]);

    expect($saved->promo_video['provider'])->toBe($provider)
        ->and($saved->promo_video['source_url'])->toBe($sourceUrl);
})->with([
    'youtube watch' => [['provider' => 'youtube', 'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'], 'youtube', 'https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
    'youtu.be' => [['provider' => 'youtube', 'url' => 'https://youtu.be/dQw4w9WgXcQ'], 'youtube', 'https://youtu.be/dQw4w9WgXcQ'],
    'vimeo' => [['provider' => 'vimeo', 'url' => 'https://vimeo.com/123456789'], 'vimeo', 'https://vimeo.com/123456789'],
    'mp4 link' => [['provider' => 'external_url', 'url' => 'https://cdn.example.com/intro.mp4'], 'external_url', 'https://cdn.example.com/intro.mp4'],
])->group('T-P3-03', 'A4');

it('refuses unsafe or wrong promo video inputs on the right field', function (array $input, string $field): void {
    $instructor = t303mUser();
    $course = t303mDraft($instructor);

    expect(fn () => t303mMediaStep($instructor, $course, ['promo_video' => $input]))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey($field));

    expect($course->fresh()->promo_video)->toBeNull();
})->with([
    'http scheme' => [['provider' => 'external_url', 'url' => 'http://cdn.example.com/a.mp4'], 'promo_video.url'],
    'javascript scheme' => [['provider' => 'external_url', 'url' => 'javascript:alert(1)'], 'promo_video.url'],
    'private ip' => [['provider' => 'external_url', 'url' => 'https://192.168.1.10/a.mp4'], 'promo_video.url'],
    'unknown provider' => [['provider' => 'dailymotion', 'url' => 'https://www.dailymotion.com/video/x1'], 'promo_video.provider'],
    'youtube with a non-youtube host' => [['provider' => 'youtube', 'url' => 'https://evil.example.com/watch?v=dQw4w9WgXcQ'], 'promo_video.url'],
    'provider without url' => [['provider' => 'vimeo', 'url' => ''], 'promo_video.url'],
])->group('T-P3-03', 'A4');

it('clears the promo video when provider and url are both empty', function (): void {
    $instructor = t303mUser();
    $course = t303mDraft($instructor);
    t303mMediaStep($instructor, $course, ['promo_video' => ['provider' => 'vimeo', 'url' => 'https://vimeo.com/123456789']]);

    $cleared = t303mMediaStep($instructor, $course->fresh(), ['promo_video' => ['provider' => '', 'url' => '']]);

    expect($cleared->promo_video)->toBeNull();
})->group('T-P3-03', 'A4');

it('round-trips SEO values per locale and refuses over-length values and non-https canonicals', function (): void {
    $instructor = t303mUser();
    $course = t303mDraft($instructor);

    $saved = app(SaveCourseWizard::class)($instructor, [
        'seo_title' => ['ar' => 'الرسم للأطفال', 'en' => 'Kids painting'],
        'seo_description' => ['ar' => 'وصف', 'en' => 'A description'],
        'seo_keywords' => ['ar' => 'رسم', 'en' => 'painting, kids'],
        'canonical_url' => 'https://example.com/courses/kids-painting',
    ], $course, 5, false);

    expect($saved->getTranslation('seo_title', 'en'))->toBe('Kids painting')
        ->and($saved->getTranslation('seo_title', 'ar'))->toBe('الرسم للأطفال')
        ->and($saved->canonical_url)->toBe('https://example.com/courses/kids-painting');

    expect(fn () => app(SaveCourseWizard::class)($instructor, ['seo_title' => ['en' => str_repeat('a', 161)]], $course, 5, false))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('seo_title.en'));
    expect(fn () => app(SaveCourseWizard::class)($instructor, ['canonical_url' => 'http://example.com/a'], $course, 5, false))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('canonical_url'));

    $cleared = app(SaveCourseWizard::class)($instructor, ['canonical_url' => ''], $course->fresh(), 5, false);
    expect($cleared->canonical_url)->toBeNull();
})->group('T-P3-03', 'A4');

it('keeps the wizard at seven steps and resumes at the saved step', function (): void {
    expect(SaveCourseWizard::LAST_STEP)->toBe(7);

    $instructor = t303mUser();
    $course = t303mDraft($instructor);
    t303mMediaStep($instructor, $course, ['promo_video' => ['provider' => 'vimeo', 'url' => 'https://vimeo.com/123456789']]);

    expect($course->fresh()->wizard_step)->toBe(4);
})->group('T-P3-03', 'A4');
