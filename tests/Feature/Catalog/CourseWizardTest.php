<?php

declare(strict_types=1);

use App\Domain\Catalog\Actions\SaveCourseWizard;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function wizardUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function wizardCategory(): Category
{
    return Category::query()->create(['name' => ['ar' => 'فنون', 'en' => 'Arts'], 'slug_ar' => 'فنون-'.uniqid(), 'slug_en' => 'arts-'.uniqid(), 'sort' => 1, 'status' => 'active']);
}

function basicStep(array $overrides = []): array
{
    return [...[
        'title' => ['ar' => 'الرسم للمبتدئين', 'en' => 'Drawing for beginners'],
        'short_description' => ['ar' => 'مقدمة', 'en' => 'Intro'],
        'category_id' => wizardCategory()->id,
        'level' => 'beginner',
        'content_language' => 'both',
        'format' => 'recorded',
        'min_age' => 7, 'max_age' => 12,
    ], ...$overrides];
}

function instructorCourse(User $instructor, string $scope = 'manage'): Course
{
    $course = Course::query()->create(['title' => ['en' => 'Assigned'], 'slug_ar' => 'a-'.uniqid(), 'slug_en' => 'a-'.uniqid()]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => $scope]);

    return $course;
}

it('creates a draft from the Basic step and generates unique bilingual slugs', function (): void {
    $admin = wizardUser('admin');

    $first = app(SaveCourseWizard::class)($admin, basicStep(), null, 1, true);
    $second = app(SaveCourseWizard::class)($admin, basicStep(), null, 1, true);

    expect($first->status->value)->toBe('draft')
        ->and($first->slug_en)->toBe('drawing-for-beginners')
        ->and($first->slug_ar)->toBe('الرسم-للمبتدئين')
        ->and($second->slug_en)->toBe('drawing-for-beginners-2')
        ->and($first->wizard_completed)->toBe(['1' => true])
        ->and($first->wizard_step)->toBe(2);
    expect(Activity::query()->where('event', 'course.draft_created')->count())->toBe(2);
})->group('T-P3-02', 'A1', 'PRD-09.2');

it('saves an incomplete draft at any step and resumes with the data intact', function (): void {
    $admin = wizardUser('admin');
    $course = app(SaveCourseWizard::class)($admin, ['title' => ['en' => 'Half done'], 'level' => 'advanced'], null, 1, false);

    $resumed = Course::query()->findOrFail($course->id);
    expect($resumed->getTranslation('title', 'en'))->toBe('Half done')
        ->and($resumed->level)->toBe('advanced')
        ->and($resumed->category_id)->toBeNull()
        ->and($resumed->wizard_step)->toBe(1)
        ->and($resumed->wizard_completed)->toBe([]);

    app(SaveCourseWizard::class)($admin, ['faqs' => [['question' => ['en' => 'Q?'], 'answer' => ['en' => 'A.']]]], $resumed, 2, false);
    $again = Course::query()->findOrFail($course->id);
    expect($again->wizard_step)->toBe(2)
        ->and($again->getTranslation('title', 'en'))->toBe('Half done')
        ->and($again->faqs)->toBe([['question' => ['en' => 'Q?'], 'answer' => ['en' => 'A.']]]);
})->group('T-P3-02', 'A1');

it('generates distinct bounded slugs for repeated long titles and titles sharing their prefix', function (): void {
    $admin = wizardUser('admin');
    $title = ['ar' => str_repeat('ر', 130), 'en' => str_repeat('a', 130)];

    $first = app(SaveCourseWizard::class)($admin, ['title' => $title]);
    $second = app(SaveCourseWizard::class)($admin, ['title' => $title]);
    $third = app(SaveCourseWizard::class)($admin, ['title' => ['ar' => $title['ar'].'ب', 'en' => $title['en'].'b']]);

    expect($first->slug_en)->toBe(str_repeat('a', 120))
        ->and($second->slug_en)->toBe(str_repeat('a', 118).'-2')
        ->and($third->slug_en)->toBe(str_repeat('a', 118).'-3')
        ->and($first->slug_ar)->toBe(str_repeat('ر', 120))
        ->and($second->slug_ar)->toBe(str_repeat('ر', 118).'-2')
        ->and($third->slug_ar)->toBe(str_repeat('ر', 118).'-3');
})->group('T-P3-02', 'A1', 'PRD-09.2');

it('enforces required Basic fields only when the step is passed', function (): void {
    $admin = wizardUser('admin');

    expect(fn () => app(SaveCourseWizard::class)($admin, ['title' => ['en' => 'No category']], null, 1, true))->toThrow(ValidationException::class);
    expect(fn () => app(SaveCourseWizard::class)($admin, basicStep(['title' => ['ar' => '', 'en' => '']]), null, 1, true))->toThrow(ValidationException::class);
    expect(Course::query()->count())->toBe(0);
})->group('T-P3-02', 'A1');

it('validates ages, slugs, enums and limits', function (array $override, string $field): void {
    $admin = wizardUser('admin');
    Course::query()->create(['title' => ['en' => 'Taken'], 'slug_ar' => 'taken', 'slug_en' => 'taken']);

    try {
        app(SaveCourseWizard::class)($admin, basicStep($override), null, 1, false);
        $failed = null;
    } catch (ValidationException $exception) {
        $failed = array_keys($exception->errors());
    }

    expect($failed)->toContain($field);
})->with([
    'max below min' => [['min_age' => 12, 'max_age' => 7], 'max_age'],
    'bad level' => [['level' => 'expert'], 'level'],
    'bad format' => [['format' => 'podcast'], 'format'],
    'duplicate slug' => [['slug_en' => 'taken'], 'slug_en'],
    'unsafe slug' => [['slug_en' => 'a b/c'], 'slug_en'],
    'unknown category' => [['category_id' => 99999], 'category_id'],
    'too long title' => [['title' => ['en' => str_repeat('x', 201)]], 'title.en'],
])->group('T-P3-02', 'A1');

it('stores Information rows, drops empty ones and keeps the entered order', function (): void {
    $admin = wizardUser('admin');
    $course = app(SaveCourseWizard::class)($admin, basicStep(), null, 1, true);

    app(SaveCourseWizard::class)($admin, [
        'requirements' => [['text' => ['en' => 'Pencil']], ['text' => ['ar' => '', 'en' => '']], ['text' => ['ar' => 'ورق']]],
        'outcomes' => [['text' => ['en' => 'Draw a face']], ['text' => ['en' => 'Shade']]],
        'faqs' => [['question' => ['ar' => 'هل يناسب الأطفال؟'], 'answer' => ['ar' => 'نعم']]],
    ], $course, 2, true);

    $course->refresh();
    expect($course->requirements)->toBe([['text' => ['en' => 'Pencil']], ['text' => ['ar' => 'ورق']]])
        ->and(array_column(array_column($course->outcomes, 'text'), 'en'))->toBe(['Draw a face', 'Shade'])
        ->and($course->faqs)->toHaveCount(1)
        ->and($course->wizard_completed)->toBe(['1' => true, '2' => true]);
})->group('T-P3-02', 'A1');

it('rejects more than 30 Information rows', function (): void {
    $admin = wizardUser('admin');
    $course = app(SaveCourseWizard::class)($admin, basicStep(), null, 1, true);
    $rows = array_fill(0, 31, ['text' => ['en' => 'x']]);

    expect(fn () => app(SaveCourseWizard::class)($admin, ['outcomes' => $rows], $course, 2, true))->toThrow(ValidationException::class);
})->group('T-P3-02', 'A1');

it('lets an instructor edit an assigned course but never ownership, status or featured', function (string $field, mixed $value): void {
    $instructor = wizardUser('instructor_academy');
    $course = instructorCourse($instructor);

    app(SaveCourseWizard::class)($instructor, ['title' => ['en' => 'Renamed']], $course, 1, false);
    expect($course->refresh()->getTranslation('title', 'en'))->toBe('Renamed');

    expect(fn () => app(SaveCourseWizard::class)($instructor, [$field => $value], $course, 1, false))->toThrow(AuthorizationException::class);
    expect($course->refresh()->getAttribute($field))->not->toBe($value);
})->with([
    'owner type' => ['owner_type', 'independent'],
    'owner instructor' => ['owner_instructor_id', 12345],
    'status' => ['status', 'private'],
    'featured' => ['featured', true],
])->group('T-P3-02', 'A2');

it('ignores privileged fields an instructor resubmits unchanged', function (): void {
    $instructor = wizardUser('instructor_academy');
    $course = instructorCourse($instructor);

    app(SaveCourseWizard::class)($instructor, ['title' => ['en' => 'Same'], 'owner_type' => 'academy', 'status' => 'draft', 'featured' => false], $course, 1, false);

    expect($course->refresh()->getTranslation('title', 'en'))->toBe('Same');
})->group('T-P3-02', 'A2');

it('refuses instructors who cannot create, view-only assignees and unassigned instructors', function (): void {
    $instructor = wizardUser('instructor_academy');
    $instructor->roles()->firstOrFail()->revokePermissionTo('course.create');
    $viewOnly = instructorCourse($instructor, 'view');
    $other = instructorCourse(wizardUser('instructor_academy'));

    expect(fn () => app(SaveCourseWizard::class)($instructor, basicStep(), null, 1, false))->toThrow(AuthorizationException::class);
    expect(fn () => app(SaveCourseWizard::class)($instructor, ['title' => ['en' => 'x']], $viewOnly, 1, false))->toThrow(AuthorizationException::class);
    expect(fn () => app(SaveCourseWizard::class)($instructor, ['title' => ['en' => 'x']], $other, 1, false))->toThrow(Exception::class);
})->group('T-P3-02', 'A2', 'I02');

it('lets admin set ownership and status but never publish through the wizard', function (): void {
    $admin = wizardUser('admin');
    $independent = wizardUser('instructor_independent');
    $course = app(SaveCourseWizard::class)($admin, basicStep(), null, 1, true);

    app(SaveCourseWizard::class)($admin, ['owner_type' => 'independent', 'owner_instructor_id' => $independent->id, 'status' => 'private', 'featured' => true], $course, 1, false);
    $course->refresh();
    expect($course->owner_type)->toBe('independent')->and($course->owner_instructor_id)->toBe($independent->id)
        ->and($course->status->value)->toBe('private')->and($course->featured)->toBeTrue();
    expect(Activity::query()->where('event', 'course.ownership_changed')->count())->toBe(1);

    foreach (['published', 'pending_review'] as $status) {
        expect(fn () => app(SaveCourseWizard::class)($admin, ['status' => $status], $course, 1, false))->toThrow(ValidationException::class);
    }
    expect(fn () => app(SaveCourseWizard::class)($admin, ['owner_type' => 'independent', 'owner_instructor_id' => wizardUser('student')->id], $course, 1, false))->toThrow(ValidationException::class);
    expect(fn () => app(SaveCourseWizard::class)($admin, ['owner_type' => 'independent', 'owner_instructor_id' => null], $course, 1, false))->toThrow(ValidationException::class);
})->group('T-P3-02', 'A2');
