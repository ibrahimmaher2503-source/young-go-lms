<?php

declare(strict_types=1);

use App\Filament\Resources\RolePermissions\Pages\ListRolePermissions;
use App\Filament\Resources\RolePermissions\RolePermissionResource;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('shows role permissions only to active super admins', function () {
    $this->seed(RolePermissionSeeder::class);

    expect(RolePermissionResource::canViewAny())->toBeFalse();

    $inactiveSuperAdmin = User::factory()->create(['status' => 'inactive'])->assignRole('super_admin');
    $this->actingAs($inactiveSuperAdmin);
    expect(RolePermissionResource::canViewAny())->toBeFalse();

    $admin = User::factory()->create()->assignRole('admin');
    $this->actingAs($admin);
    expect(RolePermissionResource::canViewAny())->toBeFalse();

    $superAdmin = User::factory()->create()->assignRole('super_admin');
    $this->actingAs($superAdmin);
    expect(RolePermissionResource::canViewAny())->toBeTrue();
})->group('T-P1-08', 'roles-permissions-ui');

it('lists editable web roles without exposing super admin or CRUD operations', function () {
    $this->seed(RolePermissionSeeder::class);
    $superAdmin = User::factory()->create()->assignRole('super_admin');
    $this->actingAs($superAdmin);

    $roleNames = RolePermissionResource::getEloquentQuery()->pluck('name')->all();
    $role = Role::findByName('finance', 'web');

    expect($roleNames)->toContain('finance')->not->toContain('super_admin')
        ->and(RolePermissionResource::canCreate())->toBeFalse()
        ->and(RolePermissionResource::canEdit($role))->toBeFalse()
        ->and(RolePermissionResource::canDelete($role))->toBeFalse()
        ->and(RolePermissionResource::canDeleteAny())->toBeFalse();
})->group('T-P1-08', 'roles-permissions-ui');

it('protects the route and audits the permissions dialog submission', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    $admin = User::factory()->create()->assignRole('admin');
    $super = User::factory()->create()->assignRole('super_admin');
    $role = Role::findByName('finance', 'web');
    $url = route('filament.admin.resources.role-permissions.index');
    $this->actingAs($admin)->get($url)->assertForbidden();
    $this->actingAs($super)->get($url)->assertOk();

    Livewire::test(ListRolePermissions::class)
        ->callAction(TestAction::make('permissions')->table($role), ['permissions' => ['payment.view']])
        ->assertHasNoActionErrors();

    expect($role->fresh()->permissions->pluck('name')->all())->toBe(['payment.view']);
    expect(Activity::query()->where('event', 'role.permissions_changed')->where('subject_id', $role->id)->count())->toBe(1);
})->group('T-P1-08', 'A2');
