<?php

declare(strict_types=1);

use App\Domain\Identity\Actions\UpdateRolePermissions;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('updates role grants and records their changes in the audit log', function () {
    $this->seed(RolePermissionSeeder::class);
    $actor = User::factory()->create()->assignRole('super_admin');
    $role = Role::findByName('finance', 'web');
    $before = $role->permissions->pluck('name')->sort()->values()->all();

    app(UpdateRolePermissions::class)->handle($actor, $role, ['payment.view']);

    expect($role->fresh()->permissions->pluck('name')->all())->toBe(['payment.view']);
    $entry = Activity::query()->where('event', 'role.permissions_changed')->sole();
    expect($entry->causer_id)->toBe($actor->id)
        ->and($entry->subject_id)->toBe($role->id)
        ->and($entry->properties['before']['permissions'])->toBe($before)
        ->and($entry->properties['after']['permissions'])->toBe(['payment.view']);
})->group('T-P1-08', 'A2');

it('rejects changes by other roles, super-admin mutations, and unknown permissions', function () {
    $this->seed(RolePermissionSeeder::class);
    $actor = User::factory()->create()->assignRole('super_admin');
    $admin = User::factory()->create()->assignRole('admin');
    $role = Role::findByName('finance', 'web');
    $superRole = Role::findByName('super_admin', 'web');
    $action = app(UpdateRolePermissions::class);

    expect(fn () => $action->handle($admin, $role, ['payment.view']))->toThrow(AuthorizationException::class)
        ->and(fn () => $action->handle($actor, $superRole, []))->toThrow(AuthorizationException::class)
        ->and(fn () => $action->handle($actor, $role, ['unknown.permission']))->toThrow(ValidationException::class);
    expect($role->fresh()->hasPermissionTo('payment.view'))->toBeTrue();
    expect(Activity::query()->where('event', 'role.permissions_changed')->count())->toBe(0);
})->group('T-P1-08', 'A2');
