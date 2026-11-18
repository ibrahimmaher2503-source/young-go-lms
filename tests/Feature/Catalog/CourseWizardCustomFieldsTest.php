<?php

// T-P3-03 | PRD 9.2 9.4 | A3

declare(strict_types=1);

use App\Domain\Catalog\Actions\SaveCourseCustomField;
use App\Domain\Catalog\Actions\SaveCourseWizard;
use App\Domain\Catalog\CourseStatus;
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
    config(['markets.EG.active' => true]);
});

function t303cUser(string $role): User
{
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole($role);

    return $user;
}

/** @return array<string, mixed> */
function t303cDefinition(string $key, string $type, array $overrides = []): array
{
    return [...[
        'field_key' => $key, 'type' => $type, 'label' => ['en' => ucfirst(str_replace('_', ' ', $key)), 'ar' => 'حقل'],
        'is_required' => false, 'is_translatable' => false, 'is_active' => true, 'visibility' => 'public', 'sort' => 1,
    ], ...$overrides];
}

function t303cField(User $admin, string $key, string $type, array $overrides = []): void
{
    app(SaveCourseCustomField::class)($admin, t303cDefinition($key, $type, $overrides));
}

function t303cDraft(User $instructor, array $overrides = []): Course
{
    $category = Category::query()->create(['name' => ['ar' => 'فنون', 'en' => 'Arts'], 'slug_ar' => 'فنون-'.uniqid(), 'slug_en' => 'arts-'.uniqid(), 'sort' => 1, 'status' => 'active']);

    return app(SaveCourseWizard::class)($instructor, [...[
        'title' => ['ar' => 'الرسم', 'en' => 'Custom values course'],
        'short_description' => ['ar' => 'مقدمة', 'en' => 'Intro'],
        'category_id' => $category->id, 'level' => 'beginner', 'content_language' => 'en', 'format' => 'recorded',
        'min_age' => 7, 'max_age' => 12,
    ], ...$overrides], null, 1, true);
}

it('lets only an admin with publish rights define custom fields, and writes an audit entry', function (): void {
    $admin = t303cUser('admin');
    $instructor = t303cUser('instructor_academy');

    expect(fn () => app(SaveCourseCustomField::class)($instructor, t303cDefinition('hidden_one', 'text')))
        ->toThrow(AuthorizationException::class);

    t303cField($admin, 'grade_level', 'text');

    expect(Activity::query()->where('event', 'course_custom_field.saved')->count())->toBeGreaterThanOrEqual(1);
})->group('T-P3-03', 'A3');

it('refuses a duplicate key, a malformed key and a select with a single option', function (string $key, string $type, array $overrides, string $error): void {
    $admin = t303cUser('admin');
    t303cField($admin, 'taken_key', 'text');

    expect(fn () => app(SaveCourseCustomField::class)($admin, t303cDefinition($key, $type, $overrides)))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey($error));
})->with([
    'duplicate key' => ['taken_key', 'text', [], 'field_key'],
    'malformed key with a dash' => ['Bad-Key', 'text', [], 'field_key'],
    'key starting with a digit' => ['1key', 'text', [], 'field_key'],
    'select with one option' => ['picker', 'select', ['options' => ['only']], 'options'],
])->group('T-P3-03', 'A3');

it('enforces text length, number range, select options, https urls, date format and toggles on the wizard values', function (): void {
    $admin = t303cUser('admin');
    $instructor = t303cUser('instructor_independent');
    t303cField($admin, 'short_text', 'text', ['min_value' => 3, 'max_value' => 10]);
    t303cField($admin, 'count_value', 'number', ['min_value' => 1, 'max_value' => 10]);
    t303cField($admin, 'picker', 'select', ['options' => ['alpha', 'beta']]);
    t303cField($admin, 'site', 'url');
    t303cField($admin, 'starts', 'date');
    t303cField($admin, 'featured_flag', 'toggle');
    $course = t303cDraft($instructor);

    $bad = [
        'short_text' => 'ab', // too short
        'count_value' => 11, // above max
        'picker' => 'gamma', // not an option
        'site' => 'http://example.com', // not https
        'starts' => '2026/01/31', // wrong date format
    ];
    foreach ($bad as $key => $value) {
        expect(fn () => app(SaveCourseWizard::class)($instructor, ['custom' => [$key => $value]], $course, 6, true))
            ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('custom.'.$key));
    }

    $saved = app(SaveCourseWizard::class)($instructor, ['custom' => [
        'short_text' => 'Hello', 'count_value' => 7, 'picker' => 'beta', 'site' => 'https://example.com/a', 'starts' => '2026-02-01', 'featured_flag' => true,
    ]], $course, 6, true);

    expect($saved->custom_values)->toMatchArray([
        'short_text' => 'Hello', 'picker' => 'beta', 'site' => 'https://example.com/a', 'starts' => '2026-02-01', 'featured_flag' => true,
    ]);
})->group('T-P3-03', 'A3');

it('drops unknown keys and keeps stored values for keys that are not submitted', function (): void {
    $admin = t303cUser('admin');
    $instructor = t303cUser('instructor_independent');
    t303cField($admin, 'keep_me', 'text');
    t303cField($admin, 'also_kept', 'text');
    $course = t303cDraft($instructor);
    app(SaveCourseWizard::class)($instructor, ['custom' => ['keep_me' => 'first', 'also_kept' => 'second']], $course, 6, false);

    $saved = app(SaveCourseWizard::class)($instructor, ['custom' => ['also_kept' => 'updated', 'not_defined' => 'x']], $course->fresh(), 6, false);

    expect($saved->custom_values)->toBe(['keep_me' => 'first', 'also_kept' => 'updated']);
})->group('T-P3-03', 'A3');

it('enforces required custom fields only when step 6 is completed, not on a lenient draft save', function (): void {
    $admin = t303cUser('admin');
    $instructor = t303cUser('instructor_independent');
    t303cField($admin, 'parent_consent', 'text', ['is_required' => true]);
    $course = t303cDraft($instructor);

    $draft = app(SaveCourseWizard::class)($instructor, ['custom' => []], $course, 6, false);
    expect($draft->custom_values ?? [])->not->toHaveKey('parent_consent');

    expect(fn () => app(SaveCourseWizard::class)($instructor, ['custom' => []], $course, 6, true))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('custom.parent_consent'));
})->group('T-P3-03', 'A3');

it('renders only public custom fields with a value on the public course page, escaped and per locale', function (): void {
    $admin = t303cUser('admin');
    $instructor = t303cUser('instructor_independent');
    t303cField($admin, 'public_note', 'text', ['visibility' => 'public', 'is_translatable' => true]);
    t303cField($admin, 'internal_note', 'text', ['visibility' => 'internal']);
    t303cField($admin, 'empty_public', 'text', ['visibility' => 'public']);
    $course = t303cDraft($instructor);
    app(SaveCourseWizard::class)($instructor, ['custom' => [
        'public_note' => ['ar' => 'ملاحظة عامة', 'en' => 'Public <b>note</b>'],
        'internal_note' => 'SECRET-INTERNAL',
    ]], $course, 6, false);
    Course::query()->whereKey($course->getKey())->update(['status' => CourseStatus::Published->value]);
    $slug = $course->fresh()->slug_en;

    $this->get('/en/courses/'.$slug)->assertOk()
        ->assertSee('data-section="custom-fields"', false)
        ->assertSee('Public &lt;b&gt;note&lt;/b&gt;', false)
        ->assertDontSee('Public <b>note</b>', false)
        ->assertDontSee('SECRET-INTERNAL')
        ->assertDontSee('Empty public');

    $this->get('/ar/courses/'.$course->fresh()->slug_ar)->assertOk()->assertSee('ملاحظة عامة');
})->group('T-P3-03', 'A3');
