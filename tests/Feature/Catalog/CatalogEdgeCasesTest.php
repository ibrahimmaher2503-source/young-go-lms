<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\SaveCategory;
use App\Domain\Catalog\Actions\SaveCourseWizard;
use App\Domain\Catalog\AgeBandResolver;
use App\Domain\Catalog\CourseDescription;
use App\Domain\Catalog\CourseStatus;
use App\Domain\Catalog\Models\AgeBand;
use App\Domain\Catalog\Queries\PublicCategory;
use App\Domain\Catalog\Queries\PublicCourseDetail;
use App\Domain\Catalog\Rules\AgeRangeRule;
use App\Domain\Commerce\CoursePrice;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function t7CatalogUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function t7CatalogCategory(array $attributes = []): Category
{
    return Category::query()->create([
        'name' => ['en' => 'Category '.uniqid()],
        'slug_ar' => 'cat-ar-'.uniqid(),
        'slug_en' => 'cat-en-'.uniqid(),
        'status' => 'active',
        'sort' => 1,
        ...$attributes,
    ]);
}

function t7CatalogCourse(string $suffix, array $attributes = []): Course
{
    return Course::query()->create([
        'title' => ['en' => 'Course '.$suffix, 'ar' => 'U.O�O�U+ '.$suffix],
        'slug_ar' => 'course-ar-'.$suffix.'-'.uniqid(),
        'slug_en' => 'course-en-'.$suffix.'-'.uniqid(),
        'status' => CourseStatus::Published,
        ...$attributes,
    ]);
}

function t7CatalogItem(Course $course, CourseSection $section, string $title, string $status, int $sort, bool $preview = false, int $duration = 0, mixed $archivedAt = null): CurriculumItem
{
    $lesson = Lesson::query()->create([
        'title' => ['en' => $title, 'ar' => $title],
        'type' => 'text',
        'is_preview' => $preview,
        'duration_seconds' => $duration,
        'archived_at' => $archivedAt,
    ]);

    return $lesson->item()->create([
        'course_id' => $course->id,
        'section_id' => $section->id,
        'sort' => $sort,
        'status' => $status,
    ]);
}

it('rejects self, unknown and second-level parents without changing category data', function (): void {
    $this->actingAs(t7CatalogUser('admin'));
    $root = t7CatalogCategory(['name' => ['en' => 'Root']]);
    $child = t7CatalogCategory(['parent_id' => $root->id, 'name' => ['en' => 'Child']]);
    $action = app(SaveCategory::class);

    expect(fn () => $action(['name' => ['en' => 'Renamed'], 'parent_id' => $root->id], $root))
        ->toThrow(ValidationException::class);
    expect(fn () => $action(['name' => ['en' => 'Renamed'], 'parent_id' => 999999], $root))
        ->toThrow(ValidationException::class);
    expect(fn () => $action(['name' => ['en' => 'Renamed'], 'parent_id' => $child->id], $root))
        ->toThrow(ValidationException::class);

    expect($root->refresh()->parent_id)->toBeNull()
        ->and($root->getTranslation('name', 'en'))->toBe('Root');
})->group('T-P2-01', 'A2', 'YG-WEB-003');

it('refuses to move a category that already has children under another parent', function (): void {
    $this->actingAs(t7CatalogUser('admin'));
    $root = t7CatalogCategory(['name' => ['en' => 'Root']]);
    t7CatalogCategory(['parent_id' => $root->id, 'name' => ['en' => 'Child']]);
    $otherRoot = t7CatalogCategory(['name' => ['en' => 'Other root']]);

    expect(fn () => app(SaveCategory::class)(['name' => ['en' => 'Root'], 'parent_id' => $otherRoot->id], $root))
        ->toThrow(ValidationException::class);
    expect($root->refresh()->parent_id)->toBeNull();
})->group('T-P2-01', 'A2');

it('refuses a caller-supplied image path and unreadable images', function (): void {
    $this->actingAs(t7CatalogUser('admin'));
    $action = app(SaveCategory::class);
    Storage::fake('public');

    expect(fn () => $action(['name' => ['en' => 'Injected'], 'image' => 'categories/existing.jpg']))
        ->toThrow(ValidationException::class);
    expect(fn () => $action(['name' => ['en' => 'Fake'], 'image' => 'not-an-image']))
        ->toThrow(ValidationException::class);
    expect(fn () => $action(['name' => ['en' => 'Huge'], 'image' => str_repeat('x', 4096 * 1024 + 1)]))
        ->toThrow(ValidationException::class);

    expect(Category::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles())->toBe([]);
})->group('T-P2-01', 'A2', 'ED-031');

it('removes a newly stored image when the category update is rejected', function (): void {
    $this->actingAs(t7CatalogUser('admin'));
    Storage::fake('public');
    $category = t7CatalogCategory();
    $bytes = UploadedFile::fake()->image('new.jpg', 20, 20)->get();

    expect(fn () => app(SaveCategory::class)(['name' => ['en' => 'Broken'], 'parent_id' => $category->id], $category, $bytes))
        ->toThrow(ValidationException::class);

    expect(Storage::disk('public')->allFiles())->toBe([])
        ->and($category->refresh()->getAttribute('image'))->toBeNull();
})->group('T-P2-01', 'A2', 'ED-031');

it('does not authorise category creation for non-staff accounts', function (): void {
    $this->actingAs(t7CatalogUser('student'));

    expect(fn () => app(SaveCategory::class)(['name' => ['en' => 'Nope']]))
        ->toThrow(AuthorizationException::class);
    expect(Category::query()->count())->toBe(0);
})->group('T-P2-01', 'A2');

it('deletes the replaced category image from public storage', function (): void {
    $this->actingAs(t7CatalogUser('admin'));
    Storage::fake('public');
    $action = app(SaveCategory::class);
    $category = $action(['name' => ['en' => 'Illustrated'], 'slug_ar' => 'illustrated-ar', 'slug_en' => 'illustrated-en'], null, UploadedFile::fake()->image('one.jpg', 20, 20)->get());
    $old = (string) $category->getAttribute('image');

    $action(['name' => ['en' => 'Illustrated']], $category, UploadedFile::fake()->image('two.jpg', 20, 20)->get());
    $new = (string) $category->refresh()->getAttribute('image');

    expect($new)->not->toBe($old)
        ->and(Storage::disk('public')->exists($new))->toBeTrue()
        ->and(Storage::disk('public')->exists($old))->toBeFalse();
})->group('T-P2-01', 'A2', 'ED-031');

it('keeps safe description markup and strips executable attributes and elements', function (): void {
    $clean = CourseDescription::clean(
        '<p onclick="alert(1)">Hi <strong>there</strong> '
        .'<a href="https://young-go.example/lesson" onclick="alert(2)">safe</a> '
        .'<a href="javascript:alert(3)">bad</a> '
        .'<a href="/en/courses">relative</a> '
        .'<img src="x" onerror="alert(4)"><script>alert(5)</script><iframe src="https://evil.example"></iframe></p>',
    );

    expect($clean)->toContain('<strong>there</strong>')
        ->toContain('href="https://young-go.example/lesson"')
        ->toContain('href="/en/courses"')
        ->not->toContain('onclick')
        ->not->toContain('onerror')
        ->not->toContain('javascript:')
        ->not->toContain('<script')
        ->not->toContain('<iframe')
        ->not->toContain('<img');
})->group('T-P3-02', 'PRD-09.2');

it('validates ordered non-negative age ranges and defers non-integer input', function (): void {
    $rule = new AgeRangeRule;

    foreach ([[10, 10], [null, null], [0, 0], [null, 18]] as [$min, $max]) {
        expect(Validator::make(['min_age' => $min, 'max_age' => $max], ['max_age' => [$rule]])->passes())->toBeTrue();
    }

    foreach ([[12, 7], [-1, 5], [5, -1]] as [$min, $max]) {
        expect(Validator::make(['min_age' => $min, 'max_age' => $max], ['max_age' => [$rule]])->fails())->toBeTrue();
    }

    expect(Validator::make(['min_age' => 'ten', 'max_age' => 5], ['max_age' => [$rule]])->passes())->toBeTrue();
})->group('T-P1-07', 'A1');

it('resolves overlapping age bands deterministically and rejects an invalid birth month', function (): void {
    $this->travelTo('2026-06-15 12:00:00');
    AgeBand::query()->create(['min' => 0, 'max' => 99, 'sort' => 2, 'label' => ['en' => 'Wide', 'ar' => 'U^O�O3O1']]);
    $narrow = AgeBand::query()->create(['min' => 12, 'max' => 12, 'sort' => 1, 'label' => ['en' => 'Twelve', 'ar' => 'O�U+O�O� O�O3O�']]);
    $open = AgeBand::query()->create(['min' => 18, 'max' => null, 'sort' => 0, 'label' => ['en' => 'Adult', 'ar' => 'O"O�O�U,']]);
    $resolver = app(AgeBandResolver::class);

    expect($resolver->resolve(2014, 1)?->id)->toBe($narrow->id)
        ->and($resolver->resolve(2014, 7)?->getAttribute('min'))->toBe(0)
        ->and($resolver->resolve(2000, 1)?->id)->toBe($open->id)
        ->and($resolver->resolve(2030, 1))->toBeNull()
        ->and(fn () => $resolver->resolve(2014, 0))->toThrow(InvalidArgumentException::class);
})->group('T-P1-07', 'A1');

it('lists only active categories that hold a published course and resolves them by id or slug', function (): void {
    $root = t7CatalogCategory(['name' => ['en' => 'Arts']]);
    $used = t7CatalogCategory(['parent_id' => $root->id, 'name' => ['en' => 'Theatre']]);
    $unused = t7CatalogCategory(['parent_id' => $root->id, 'name' => ['en' => 'Empty']]);
    $draftOnly = t7CatalogCategory(['parent_id' => $root->id, 'name' => ['en' => 'Draft only']]);
    $inactive = t7CatalogCategory(['parent_id' => $root->id, 'name' => ['en' => 'Hidden'], 'status' => 'inactive']);
    $inactiveRoot = t7CatalogCategory(['name' => ['en' => 'Inactive root'], 'status' => 'inactive']);

    t7CatalogCourse('theatre', ['category_id' => $used->id]);
    t7CatalogCourse('draft', ['category_id' => $draftOnly->id, 'status' => CourseStatus::Draft]);
    t7CatalogCourse('hidden', ['category_id' => $inactive->id]);
    t7CatalogCourse('hidden-root', ['category_id' => $inactiveRoot->id]);

    $query = app(PublicCategory::class);
    $listed = $query->listed()->pluck('id')->all();
    $children = $query->children($root)->pluck('id')->all();

    expect($listed)->toEqualCanonicalizing([$root->id, $used->id])
        ->and($children)->toBe([$used->id])
        ->and($listed)->not->toContain($unused->id, $draftOnly->id, $inactive->id)
        ->and($query->find((string) $used->id)?->id)->toBe($used->id)
        ->and($query->find($used->slug_en)?->id)->toBe($used->id)
        ->and($query->find($used->slug_ar)?->id)->toBe($used->id)
        ->and($query->find('missing-category'))->toBeNull()
        ->and($query->find((string) $inactive->id))->toBeNull();
})->group('T-P2-09', 'A1', 'YG-WEB-003');

it('shows only published curriculum items and reports the first preview and total minutes', function (): void {
    $course = t7CatalogCourse('curriculum');
    $visible = CourseSection::query()->create(['course_id' => $course->id, 'published' => true, 'sort' => 1, 'title' => ['en' => 'Visible']]);
    $draftSection = CourseSection::query()->create(['course_id' => $course->id, 'published' => false, 'sort' => 2, 'title' => ['en' => 'Draft section']]);
    $archivedSection = CourseSection::query()->create(['course_id' => $course->id, 'published' => true, 'sort' => 3, 'title' => ['en' => 'Archived section'], 'archived_at' => now()]);

    $preview = t7CatalogItem($course, $visible, 'Preview lesson', CurriculumItem::STATUS_PUBLISHED, 1, true, 90);
    $second = t7CatalogItem($course, $visible, 'Second lesson', CurriculumItem::STATUS_PUBLISHED, 2, false, 150);
    t7CatalogItem($course, $visible, 'Draft lesson', CurriculumItem::STATUS_DRAFT, 3, true, 60);
    t7CatalogItem($course, $visible, 'Archived lesson', CurriculumItem::STATUS_PUBLISHED, 4, false, 60, now());
    t7CatalogItem($course, $draftSection, 'Hidden by section', CurriculumItem::STATUS_PUBLISHED, 1, false, 60);
    t7CatalogItem($course, $archivedSection, 'Hidden by archive', CurriculumItem::STATUS_PUBLISHED, 1, false, 60);

    $curriculum = app(PublicCourseDetail::class)->curriculum($course);

    expect($curriculum['sections'])->toHaveCount(1)
        ->and($curriculum['sections'][0]['title'])->toBe('Visible')
        ->and($curriculum['sections'][0]['items'])->toHaveCount(2)
        ->and(array_column($curriculum['sections'][0]['items'], 'title'))->toBe(['Preview lesson', 'Second lesson'])
        ->and($curriculum['sections'][0]['items'][0]['preview'])->toBeTrue()
        ->and($curriculum['sections'][0]['items'][1]['preview'])->toBeFalse()
        ->and($curriculum['preview_item'])->toBe($preview->id)
        ->and($curriculum['lessons'])->toBe(2)
        ->and($curriculum['minutes'])->toBe(5)
        ->and($curriculum['sections'][0]['items'][0]['id'])->toBe($preview->id)
        ->and($curriculum['sections'][0]['items'][1]['id'])->toBe($second->id);
})->group('T-P2-05', 'A1', 'YG-WEB-004');

it('saves exact active-market prices and does not require a price for a disabled market', function (): void {
    $admin = t7CatalogUser('admin');
    $course = t7CatalogCourse('pricing');
    $action = app(SaveCourseWizard::class);

    $action($admin, ['access_mode' => 'paid', 'market_prices' => ['EG' => '125.50']], $course, 3, true);

    $price = CoursePrice::query()->where('course_id', $course->id)->where('market', 'EG')->sole();
    expect($course->refresh()->access_mode)->toBe('paid')
        ->and($price->amount_minor)->toBe(12550)
        ->and($price->currency)->toBe('EGP')
        ->and(CoursePrice::query()->where('course_id', $course->id)->where('market', 'SA')->exists())->toBeFalse();
})->group('T-P3-03', 'A1', 'PRD-09.2');

it('requires the Egypt price before completing the paid step', function (): void {
    $admin = t7CatalogUser('admin');
    $course = t7CatalogCourse('missing-price');

    expect(fn () => app(SaveCourseWizard::class)($admin, ['access_mode' => 'paid', 'market_prices' => []], $course, 3, true))
        ->toThrow(ValidationException::class);

    expect(CoursePrice::query()->where('course_id', $course->id)->count())->toBe(0);
})->group('T-P3-03', 'A1');

it('does not let instructors set course pricing', function (): void {
    $instructor = t7CatalogUser('instructor_academy');
    $course = t7CatalogCourse('instructor-priced');
    $course->instructors()->attach($instructor, ['role' => 'instructor', 'scope' => 'manage']);

    expect(fn () => app(SaveCourseWizard::class)($instructor, ['access_mode' => 'paid'], $course, 3, false))
        ->toThrow(AuthorizationException::class);
    expect($course->refresh()->access_mode)->not->toBe('paid');
})->group('T-P3-03', 'A2');

it('clears persisted prices when a course becomes free', function (): void {
    $admin = t7CatalogUser('admin');
    $course = t7CatalogCourse('free-again');
    $action = app(SaveCourseWizard::class);

    $action($admin, ['access_mode' => 'paid', 'market_prices' => ['EG' => '125.50']], $course, 3, true);
    $action($admin, ['access_mode' => 'free'], $course, 3, true);

    expect($course->refresh()->access_mode)->toBe('free')
        ->and(CoursePrice::query()->where('course_id', $course->id)->count())->toBe(0);
})->group('T-P3-03', 'A1', 'PRD-09.2');
