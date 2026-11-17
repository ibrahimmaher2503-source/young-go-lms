<?php

declare(strict_types=1);

use App\Domain\Entitlements\Actions\EnrollInFreeCourse;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\CourseAccessSummary;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Entitlements\Filament\ManualGrantResource;
use App\Domain\Entitlements\Filament\Pages\ListManualGrants;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
});

function egCourse(string $access = 'free', string $status = 'published'): Course
{
    return Course::query()->create(['title' => ['en' => 'Free course', 'ar' => 'دورة'], 'slug_ar' => 'f-'.uniqid(), 'slug_en' => 'f-'.uniqid(), 'access_mode' => $access, 'status' => $status]);
}

it('enrolls a learner in a free published course once', function (): void {
    $course = egCourse();
    $learner = User::factory()->create()->assignRole('student');

    $first = app(EnrollInFreeCourse::class)($learner, $learner, $course);
    $again = app(EnrollInFreeCourse::class)($learner, $learner, $course);

    expect($again->id)->toBe($first->id)->and($first->source_type)->toBe(EntitlementSource::Free)->and(Entitlement::query()->count())->toBe(1)
        ->and(app(EntitlementService::class)->canAccessCourse($learner, $course)->allowed)->toBeTrue();
})->group('T-P4-02', 'A1');

it('refuses paid, closed and inactive cases', function (): void {
    $learner = User::factory()->create()->assignRole('student');
    $enroll = app(EnrollInFreeCourse::class);

    expect(fn () => $enroll($learner, $learner, egCourse('paid')))->toThrow(ValidationException::class)
        ->and(fn () => $enroll($learner, $learner, egCourse('free', 'draft')))->toThrow(ValidationException::class);

    $learner->forceFill(['status' => 'suspended'])->save();
    expect(fn () => $enroll($learner, $learner, egCourse()))->toThrow(AuthorizationException::class);
})->group('T-P4-02', 'A1');

it('lets a verified parent enroll their child and gives the entitlement to the child', function (): void {
    $course = egCourse();
    $parent = User::factory()->create()->assignRole('parent');
    $child = User::factory()->create()->assignRole('student');
    $stranger = User::factory()->create()->assignRole('student');
    GuardianChildLink::query()->create(['guardian_id' => $parent->id, 'child_id' => $child->id, 'status' => GuardianChildLink::STATUS_VERIFIED, 'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT, 'verified_at' => now()]);

    $entitlement = app(EnrollInFreeCourse::class)($parent, $child, $course);

    expect($entitlement->learner_id)->toBe($child->id)->and(Entitlement::query()->where('learner_id', $parent->id)->exists())->toBeFalse();
    expect(fn () => app(EnrollInFreeCourse::class)($parent, $stranger, $course))->toThrow(AuthorizationException::class);

    GuardianChildLink::query()->update(['status' => GuardianChildLink::STATUS_PENDING]);
    expect(fn () => app(EnrollInFreeCourse::class)($parent, User::factory()->create(), $course))->toThrow(AuthorizationException::class);
})->group('T-P4-02', 'A3');

it('shows grants to finance and admin only and summarises access by source', function (): void {
    $course = egCourse();
    $other = egCourse('paid');
    $admin = User::factory()->create()->assignRole('admin');
    $grant = app(GrantEntitlement::class);
    $grant(User::factory()->create(), $course, EntitlementSource::Free);
    $grant(User::factory()->create(), $course, EntitlementSource::ManualGrant, 'a', null, now()->addDay(), $admin, 'gift');
    $ended = $grant(User::factory()->create(), $course, EntitlementSource::ManualGrant, 'b', now()->subDays(3), now()->subDay(), $admin, 'old');
    $grant(User::factory()->create(), $other, EntitlementSource::Purchase, 'order-9');

    $all = CourseAccessSummary::for();
    $one = CourseAccessSummary::for($course->id);

    expect($all['active'])->toBe(3)->and($all['expired'])->toBe(1)->and($one['active'])->toBe(2)->and($one['by_source']['manual_grant'])->toBe(['active' => 1, 'expired' => 1, 'revoked' => 0])
        ->and($one['by_source'])->not->toHaveKey('purchase');

    $this->actingAs($admin)->get('/admin/manual-grants')->assertOk()->assertSee(__('entitlements.grant'));
    $this->actingAs(User::factory()->create()->assignRole('finance'))->get('/finance/manual-grants')->assertOk();
    $this->actingAs(User::factory()->create()->assignRole('instructor_academy'))->get('/admin/manual-grants')->assertForbidden();
    $this->actingAs(User::factory()->create()->assignRole('student'))->get('/admin/manual-grants')->assertForbidden();
    expect($ended->refresh()->status)->toBe('active'); // swept later; summary already counts it as expired
})->group('T-P4-02', 'A1');

it('grants by hand without any order and revokes with a reason that blocks access at once', function (): void {
    $course = egCourse('paid');
    $admin = User::factory()->create()->assignRole('admin');
    $learner = User::factory()->create()->assignRole('student');
    $this->actingAs($admin);

    Livewire::test(ListManualGrants::class)
        ->callAction('grant', ['learner_id' => $learner->id, 'course_id' => $course->id, 'lifetime' => true, 'reason' => 'scholarship'])
        ->assertHasNoActionErrors();

    $entitlement = Entitlement::query()->firstOrFail();
    expect($entitlement->source_type)->toBe(EntitlementSource::ManualGrant)->and($entitlement->ends_at)->toBeNull()->and($entitlement->granted_by)->toBe($admin->id)
        ->and(app(EntitlementService::class)->canAccessCourse($learner, $course)->allowed)->toBeTrue()
        ->and(Schema::hasTable('orders'))->toBeFalse();

    Livewire::test(ListManualGrants::class)
        ->assertCanSeeTableRecords([$entitlement])
        ->callTableAction('revoke', $entitlement, ['reason' => 'granted by mistake'])
        ->assertHasNoTableActionErrors();

    expect($entitlement->refresh()->status)->toBe(Entitlement::REVOKED)->and(app(EntitlementService::class)->canAccessCourse($learner, $course)->allowed)->toBeFalse()
        ->and(Activity::query()->where('event', 'entitlement.revoked')->count())->toBe(1);
})->group('T-P4-02', 'A1', 'A2');

it('filters grants by status, source and course', function (): void {
    $course = egCourse();
    $other = egCourse();
    $admin = User::factory()->create()->assignRole('admin');
    $grant = app(GrantEntitlement::class);
    $free = $grant(User::factory()->create(), $course, EntitlementSource::Free);
    $ended = $grant(User::factory()->create(), $other, EntitlementSource::ManualGrant, 'x', now()->subDays(3), now()->subDay(), $admin, 'old');
    $this->actingAs($admin);

    Livewire::test(ListManualGrants::class)
        ->filterTable('status', 'expired')->assertCanSeeTableRecords([$ended])->assertCanNotSeeTableRecords([$free])
        ->filterTable('status', 'active')->assertCanSeeTableRecords([$free])->assertCanNotSeeTableRecords([$ended])
        ->removeTableFilters()->filterTable('source_type', 'free')->assertCanSeeTableRecords([$free])->assertCanNotSeeTableRecords([$ended])
        ->removeTableFilters()->filterTable('course_id', $other->id)->assertCanSeeTableRecords([$ended])->assertCanNotSeeTableRecords([$free]);
})->group('T-P4-02', 'A1');

it('exports the filtered grants as CSV without letting cells run as formulas', function (): void {
    $course = Course::query()->create(['title' => ['en' => '=HYPERLINK("x")'], 'slug_ar' => 'x-'.uniqid(), 'slug_en' => 'x-'.uniqid(), 'access_mode' => 'free', 'status' => 'published']);
    $admin = User::factory()->create()->assignRole('admin');
    app(GrantEntitlement::class)(User::factory()->create(['email' => 'csv@example.com']), $course, EntitlementSource::ManualGrant, 'c', null, null, $admin, '+cmd');
    $this->actingAs($admin);

    $response = Livewire::test(ListManualGrants::class)->callAction('export')->assertFileDownloaded();

    expect(ListManualGrants::safe('=1+1'))->toBe("'=1+1")->and(ListManualGrants::safe('+x'))->toBe("'+x")->and(ListManualGrants::safe('plain'))->toBe('plain')
        ->and(ManualGrantResource::canViewAny())->toBeTrue();
})->group('T-P4-02', 'A1');
