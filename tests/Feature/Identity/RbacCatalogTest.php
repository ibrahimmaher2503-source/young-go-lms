<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(DatabaseSeeder::class);
});

afterEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

it('seeds the role and permission catalog idempotently', function (): void {
    $counts = [
        Role::query()->count(),
        Permission::query()->count(),
        DB::table('role_has_permissions')->count(),
    ];

    $this->seed(DatabaseSeeder::class);

    expect(Role::query()->count())->toBe($counts[0])
        ->and(Permission::query()->count())->toBe($counts[1])
        ->and(DB::table('role_has_permissions')->count())->toBe($counts[2]);
})->group('T-P1-03', 'A1', 'PRD-06');

it('does not grant curriculum publishing or system security to finance by default', function (): void {
    $finance = Role::findByName('finance', 'web');

    expect($finance->hasPermissionTo('course.publish'))->toBeFalse()
        ->and($finance->hasPermissionTo('system.security.manage'))->toBeFalse();
})->group('T-P1-03', 'A2', 'PRD-06');

it('keeps permission names unique and in module.action form', function (): void {
    $permissions = config('permissions.permissions');
    $names = array_column($permissions, 'name');

    expect($names)->not->toBeEmpty()
        ->and(array_unique($names))->toHaveCount(count($names));

    foreach ($names as $name) {
        expect($name)->toMatch('/^[a-z][a-z0-9_]*(\.[a-z][a-z0-9_]*)+$/D');
    }

    foreach (config('permissions.roles') as $grants) {
        expect(array_diff(array_diff($grants, ['*']), $names))->toBe([]);
    }
})->group('T-P1-03', 'A3', 'PRD-06');

it('applies seeded role grants through Laravel authorization', function (string $roleName, string $ability, bool $allowed): void {
    $user = User::factory()->create();
    $user->assignRole($roleName);

    expect($user->can($ability))->toBe($allowed);
})->with([
    'content manager can manage course content' => ['content_manager', 'course.update', true],
    'independent instructor cannot publish directly' => ['instructor_independent', 'course.publish', false],
    'finance can review manual payments' => ['finance', 'payment.approve_manual', true],
    'support cannot see private AI conversations' => ['support', 'ai.conversation.view', false],
    'finance cannot publish courses' => ['finance', 'course.publish', false],
    'finance cannot manage platform security' => ['finance', 'system.security.manage', false],
    'students cannot approve payments' => ['student', 'payment.approve_manual', false],
    'content managers cannot manage platform security' => ['content_manager', 'system.security.manage', false],
])->group('T-P1-03', 'PRD-06');

it('lets super admin pass policy gates while ordinary roles still require permission', function (): void {
    $superAdmin = User::factory()->create();
    $superAdmin->assignRole('super_admin');
    $student = User::factory()->create();
    $student->assignRole('student');

    expect($superAdmin->can('system.security.manage'))->toBeTrue()
        ->and($student->can('system.security.manage'))->toBeFalse();
})->group('T-P1-03', 'PRD-06');

it('audits super admin overrides with the actor, ability and target', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    $target = User::factory()->create();

    Gate::define('test.override', static fn (User $actor, User $subject): bool => false);

    expect($admin->can('system.security.manage'))->toBeTrue()
        ->and(Activity::query()->where('event', 'permission.super_admin_bypass')->count())->toBe(0)
        ->and($admin->can('test.override', $target))->toBeTrue();

    $entry = Activity::query()->where('event', 'permission.super_admin_bypass')->sole();

    expect($entry->causer?->is($admin))->toBeTrue()
        ->and($entry->subject?->is($target))->toBeTrue()
        ->and($entry->properties->get('context'))->toBe(['ability' => 'test.override']);
})->group('T-P1-03', 'PRD-06');

it('restricts Filament panels to active users with the matching permission', function (): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $finance = User::factory()->create();
    $finance->assignRole('finance');
    $suspendedAdmin = User::factory()->create(['status' => 'suspended']);
    $suspendedAdmin->assignRole('admin');

    expect($admin->canAccessPanel(Panel::make()->id('admin')))->toBeTrue()
        ->and($finance->canAccessPanel(Panel::make()->id('admin')))->toBeFalse()
        ->and($finance->canAccessPanel(Panel::make()->id('finance')))->toBeTrue()
        ->and($suspendedAdmin->canAccessPanel(Panel::make()->id('admin')))->toBeFalse()
        ->and($admin->canAccessPanel(Panel::make()->id('unknown')))->toBeFalse();
})->group('T-P1-03', 'PRD-06');

it('preserves an intentionally revoked default grant when the database is reseeded', function (): void {
    $finance = Role::findByName('finance', 'web');
    $finance->revokePermissionTo('payment.view');

    $this->seed(DatabaseSeeder::class);

    expect($finance->fresh()->hasPermissionTo('payment.view'))->toBeFalse();
})->group('T-P1-03', 'A1', 'PRD-06');

it('denies inactive super admins and inactive users before permission lookup', function (): void {
    $inactiveSuperAdmin = User::factory()->create();
    $inactiveSuperAdmin->setAttribute('status', 'deactivated')->save();
    $inactiveSuperAdmin->assignRole('super_admin');
    $inactiveFinance = User::factory()->create();
    $inactiveFinance->setAttribute('status', 'suspended')->save();
    $inactiveFinance->assignRole('finance');

    expect($inactiveSuperAdmin->refresh()->getAttribute('status'))->toBe('deactivated')
        ->and($inactiveSuperAdmin->can('system.security.manage'))->toBeFalse()
        ->and($inactiveFinance->can('payment.view'))->toBeFalse();
})->group('T-P1-03', 'PRD-06');
it('does not query role or permission tables for unauthenticated gate checks', function (): void {
    $queries = [];
    DB::listen(static function ($query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    expect(Gate::allows('course.view'))->toBeFalse()
        ->and($queries)->toBe([]);
})->group('T-P1-03', 'PRD-06');
