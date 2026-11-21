<?php

declare(strict_types=1);

use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\Actions\RevokeEntitlement;
use App\Domain\Entitlements\Actions\UpdateEntitlement;
use App\Domain\Entitlements\Actions\UpdateSourceEntitlementDates;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Entitlements\EntitlementSource;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Carbon::setTestNow('2026-11-21 10:00:00');
});

afterEach(fn () => Carbon::setTestNow());

it('allows staff with manual_entitlement.grant to update starts_at, ends_at, lifetime and reason and records entitlement.updated audit', function (): void {
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة', 'en' => 'Course'],
        'slug_ar' => 'c-ar-1', 'slug_en' => 'c-en-1', 'status' => 'published', 'access_mode' => 'paid', 'manual_access_allowed' => true,
    ]);

    $grant = app(GrantEntitlement::class)($learner, $course, EntitlementSource::ManualGrant, 'manual-1', now()->subDays(10), now()->subDay(), $admin, 'Initial grant');
    expect(app(EntitlementService::class)->canAccessCourse($learner, $course)->allowed)->toBeFalse();

    $updated = app(UpdateEntitlement::class)($admin, $grant, now()->subDays(5), now()->addDays(30), 'Extended for learner');
    expect($updated->status)->toBe(Entitlement::ACTIVE)
        ->and($updated->ends_at?->toDateString())->toBe(now()->addDays(30)->toDateString())
        ->and($updated->reason)->toBe('Extended for learner')
        ->and(app(EntitlementService::class)->canAccessCourse($learner, $course)->allowed)->toBeTrue()
        ->and(Activity::query()->where('event', 'entitlement.updated')->count())->toBe(1);

    $lifetime = app(UpdateEntitlement::class)($admin, $updated, now()->subDays(5), null, 'Converted to lifetime');
    expect($lifetime->ends_at)->toBeNull();
});

it('rejects editing a revoked entitlement, invalid dates or unauthorized actors', function (): void {
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة 2', 'en' => 'Course 2'],
        'slug_ar' => 'c-ar-2', 'slug_en' => 'c-en-2', 'status' => 'published', 'access_mode' => 'paid', 'manual_access_allowed' => true,
    ]);
    $grant = app(GrantEntitlement::class)($learner, $course, EntitlementSource::ManualGrant, 'manual-2', now(), now()->addDays(5), $admin, 'Initial');

    expect(fn () => app(UpdateEntitlement::class)($learner, $grant, now(), now()->addDays(10), 'Try hack'))
        ->toThrow(AuthorizationException::class);

    expect(fn () => app(UpdateEntitlement::class)($admin, $grant, now(), now()->subDay(), 'Bad end date'))
        ->toThrow(ValidationException::class);

    app(RevokeEntitlement::class)($admin, $grant, 'Revoked now');
    expect(fn () => app(UpdateEntitlement::class)($admin, $grant->refresh(), now(), now()->addDays(10), 'Reopen'))
        ->toThrow(ValidationException::class);
});

it('updates all non-revoked entitlements for a given source reference', function (): void {
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');
    $c1 = Course::query()->create(['title' => ['ar' => '١', 'en' => 'C1'], 'slug_ar' => 's1-ar', 'slug_en' => 's1-en', 'status' => 'published', 'access_mode' => 'subscription']);
    $c2 = Course::query()->create(['title' => ['ar' => '٢', 'en' => 'C2'], 'slug_ar' => 's2-ar', 'slug_en' => 's2-en', 'status' => 'published', 'access_mode' => 'subscription']);

    app(GrantEntitlement::class)($learner, $c1, EntitlementSource::Subscription, 'subscription_period:99', now()->subMonth(), now()->subDay(), $admin);
    app(GrantEntitlement::class)($learner, $c2, EntitlementSource::Subscription, 'subscription_period:99', now()->subMonth(), now()->subDay(), $admin);

    $count = app(UpdateSourceEntitlementDates::class)($admin, EntitlementSource::Subscription, 'subscription_period:99', now()->subMonth(), now()->addMonth(), 'Extended period');
    expect($count)->toBe(2)
        ->and(app(EntitlementService::class)->canAccessCourse($learner, $c1)->allowed)->toBeTrue()
        ->and(app(EntitlementService::class)->canAccessCourse($learner, $c2)->allowed)->toBeTrue();
});
