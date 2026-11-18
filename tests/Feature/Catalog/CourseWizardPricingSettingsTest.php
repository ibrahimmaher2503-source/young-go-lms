<?php

// T-P3-03 | PRD 9.2 9.4 | A2

declare(strict_types=1);

use App\Domain\Catalog\Actions\SaveCourseWizard;
use App\Domain\Commerce\Actions\SaveBundle;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    config(['markets.EG.active' => true]);
});

function t303pUser(string $role): User
{
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole($role);

    return $user;
}

/** An academy course the admin can price, with a manage-scope academy instructor. */
function t303pCourse(array $fields = []): Course
{
    $category = Category::query()->create(['name' => ['en' => 'Arts'], 'slug_ar' => 'ar-'.uniqid(), 'slug_en' => 'en-'.uniqid(), 'sort' => 1, 'status' => 'active']);
    $course = Course::query()->create([
        'title' => ['en' => 'Pricing course'], 'slug_ar' => 'pc-'.uniqid(), 'slug_en' => 'pc-'.uniqid(),
        'owner_type' => Course::OWNER_ACADEMY, 'category_id' => $category->id, 'level' => 'beginner',
        'content_language' => 'en', 'format' => 'recorded', 'access_mode' => Course::ACCESS_FREE, ...$fields,
    ]);

    return $course;
}

function t303pPricingStep(User $actor, Course $course, array $data): Course
{
    return app(SaveCourseWizard::class)($actor, $data, $course, 3, true);
}

it('saves and reloads the access duration and the two eligibility flags through the pricing step', function (): void {
    $admin = t303pUser('admin');
    $course = t303pCourse();

    t303pPricingStep($admin, $course, ['access_duration_days' => 30, 'bundle_eligible' => false, 'manual_access_allowed' => false]);

    $reloaded = Course::query()->findOrFail($course->getKey());
    expect($reloaded->access_duration_days)->toBe(30)
        ->and($reloaded->bundle_eligible)->toBeFalse()
        ->and($reloaded->manual_access_allowed)->toBeFalse();

    t303pPricingStep($admin, $reloaded, ['access_duration_days' => '', 'bundle_eligible' => true]);
    expect($reloaded->fresh()->access_duration_days)->toBeNull()
        ->and($reloaded->fresh()->bundle_eligible)->toBeTrue();
})->group('T-P3-03', 'A2');

it('rejects invalid access durations on the duration field', function (mixed $value): void {
    $admin = t303pUser('admin');
    $course = t303pCourse();

    expect(fn () => t303pPricingStep($admin, $course, ['access_duration_days' => $value]))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('access_duration_days'));

    expect($course->fresh()->access_duration_days)->toBeNull();
})->with([0, 3651, 1.5, 'thirty'])->group('T-P3-03', 'A2');

it('refuses the pricing settings from a user without pricing rights', function (): void {
    $instructor = t303pUser('instructor_academy');
    $course = t303pCourse();
    $course->instructors()->attach($instructor, ['role' => 'instructor', 'scope' => Course::INSTRUCTOR_SCOPE_MANAGE]);

    expect(fn () => t303pPricingStep($instructor, $course, ['access_duration_days' => 14]))
        ->toThrow(AuthorizationException::class);
    expect(fn () => t303pPricingStep($instructor, $course, ['bundle_eligible' => false]))
        ->toThrow(AuthorizationException::class);
    expect($course->fresh()->access_duration_days)->toBeNull()
        ->and($course->fresh()->bundle_eligible)->toBeTrue();
})->group('T-P3-03', 'A2');

it('ends a free enrolment 30 days after its start when the course has a 30-day duration', function (): void {
    $admin = t303pUser('admin');
    $learner = User::factory()->create(['status' => 'active']);
    $course = t303pCourse(['access_duration_days' => 30]);
    $start = now()->startOfMinute();

    $entitlement = app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free, 'free-1', $start, null, $admin);

    expect($entitlement->ends_at->toDateTimeString())->toBe($start->copy()->addDays(30)->toDateTimeString());
})->group('T-P3-03', 'A2');

it('ends a paid order grant 30 days after its start when the course has a 30-day duration', function (): void {
    $learner = User::factory()->create(['status' => 'active']);
    $course = t303pCourse(['access_duration_days' => 30, 'access_mode' => Course::ACCESS_PAID]);
    $start = now()->startOfMinute();

    $entitlement = app(GrantEntitlement::class)($learner, $course, EntitlementSource::Purchase, 'order-1', $start);

    expect($entitlement->ends_at->toDateTimeString())->toBe($start->copy()->addDays(30)->toDateTimeString());
})->group('T-P3-03', 'A2');

it('keeps a course without a duration lifetime for free and paid grants', function (): void {
    $learner = User::factory()->create(['status' => 'active']);
    $course = t303pCourse(['access_duration_days' => null, 'access_mode' => Course::ACCESS_PAID]);

    $free = app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free, 'free-lifetime');
    $paid = app(GrantEntitlement::class)($learner, $course, EntitlementSource::Purchase, 'order-lifetime');

    expect($free->ends_at)->toBeNull()->and($paid->ends_at)->toBeNull();
})->group('T-P3-03', 'A2');

it('does not apply the duration to a manual grant', function (): void {
    $admin = t303pUser('admin');
    $learner = User::factory()->create(['status' => 'active']);
    $course = t303pCourse(['access_duration_days' => 30]);

    $manual = app(GrantEntitlement::class)($learner, $course, EntitlementSource::ManualGrant, 'manual-1', now(), null, $admin, 'Scholarship');

    expect($manual->ends_at)->toBeNull();
})->group('T-P3-03', 'A2');

it('refuses a manual grant when manual access is disabled, while free and paid grants still work', function (): void {
    $admin = t303pUser('admin');
    $learner = User::factory()->create(['status' => 'active']);
    $course = t303pCourse(['manual_access_allowed' => false, 'access_mode' => Course::ACCESS_PAID]);
    $grant = app(GrantEntitlement::class);

    expect(fn () => $grant($learner, $course, EntitlementSource::ManualGrant, 'manual-blocked', now(), null, $admin, 'Test'))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('course'));

    expect($grant($learner, $course, EntitlementSource::Free, 'free-allowed'))->not->toBeNull()
        ->and($grant($learner, $course, EntitlementSource::Purchase, 'order-allowed'))->not->toBeNull();
})->group('T-P3-03', 'A2');

it('refuses to save a bundle that contains a course that is not bundle eligible, and saves one that is', function (): void {
    $admin = t303pUser('admin');
    $ineligible = t303pCourse(['bundle_eligible' => false]);
    $eligible = t303pCourse(['bundle_eligible' => true]);
    $base = ['name_ar' => 'باقة الفنون', 'name_en' => 'Arts bundle', 'is_active' => false, 'prices' => ['EG' => 50000]];

    $second = t303pCourse(['bundle_eligible' => true]);

    expect(fn () => app(SaveBundle::class)($admin, [...$base, 'items' => [['course_id' => $eligible->getKey()], ['course_id' => $ineligible->getKey()]]]))
        ->toThrow(fn (ValidationException $e) => expect($e->errors()['items'] ?? [])->toBe([__('bundles.courses_not_eligible')]));

    $bundle = app(SaveBundle::class)($admin, [...$base, 'items' => [['course_id' => $eligible->getKey()], ['course_id' => $second->getKey()]]]);
    expect($bundle->items()->pluck('course_id')->sort()->values()->all())->toBe([$eligible->getKey(), $second->getKey()]);
})->group('T-P3-03', 'A2');
