<?php

declare(strict_types=1);

use App\Filament\Resources\RolePermissions\Pages\ListRolePermissions;
use App\Filament\Resources\StaffUsers\Pages\ListStaffUsers;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
});

it('composes real user summaries and role cards around the native searchable table', function () {
    $actor = User::factory()->create(['locale' => 'en'])->assignRole('super_admin');
    $student = User::factory()->create(['name' => 'Learner One'])->assignRole('student');
    User::factory()->create()->assignRole('student');
    User::factory()->create(['status' => 'suspended'])->assignRole('parent');
    $this->actingAs($actor)->get('/admin/staff-users')->assertOk();

    Livewire::test(ListStaffUsers::class)
        ->assertSee('yg-summary-strip', false)
        ->assertSeeHtml('data-workspace-value="4"')
        ->assertSeeHtml('data-workspace-value="3"')
        ->assertSeeHtml('data-workspace-value="1"')
        ->assertSee(__('staff.roles_overview'))
        ->assertSee(__('staff.recent_activity'))
        ->assertSeeHtml('data-workspace-role="student"')
        ->searchTable('Learner One')
        ->assertCanSeeTableRecords([$student])
        ->assertCanNotSeeTableRecords([$actor]);
})->group('T-P1-08', 'A1', 'A4', 'D-026');

it('hides audit cards and permission-management links from a basic user viewer', function () {
    $actor = User::factory()->create(['locale' => 'en'])->assignRole('support');
    Activity::query()->create(['log_name' => 'audit', 'event' => 'private_event', 'description' => 'Private audit description']);
    $this->actingAs($actor)->get('/admin/staff-users')->assertOk();

    Livewire::test(ListStaffUsers::class)
        ->assertSee(__('staff.roles_overview'))
        ->assertDontSee(__('staff.recent_activity'))
        ->assertDontSee('Private audit description')
        ->assertDontSee('private_event')
        ->assertDontSeeHtml('data-workspace-manage-roles');
})->group('T-P1-08', 'A1', 'D-026');

it('keeps the quick role filter and native tabs connected to the actual table query', function () {
    $actor = User::factory()->create(['locale' => 'en'])->assignRole('super_admin');
    $student = User::factory()->create()->assignRole('student');
    $parent = User::factory()->create()->assignRole('parent');
    $this->actingAs($actor)->get('/admin/staff-users')->assertOk();
    Livewire::test(ListStaffUsers::class)->filterTable('role', Role::findByName('student')->id)
        ->assertCanSeeTableRecords([$student])->assertCanNotSeeTableRecords([$parent, $actor]);
    Livewire::test(ListStaffUsers::class)->set('activeTab', 'students')
        ->assertCanSeeTableRecords([$student])->assertCanNotSeeTableRecords([$parent, $actor]);
})->group('T-P1-08', 'A1', 'D-026');

it('finds a role by its displayed Arabic label rather than only its database key', function () {
    app()->setLocale('ar');
    $actor = User::factory()->create(['locale' => 'ar'])->assignRole('super_admin');
    $role = Role::findByName('content_manager');
    $other = Role::findByName('finance');
    $this->actingAs($actor)->get('/admin/role-permissions')->assertOk();
    Livewire::test(ListRolePermissions::class)
        ->searchTable(__('roles.content_manager'))
        ->assertCanSeeTableRecords([$role])->assertCanNotSeeTableRecords([$other]);
})->group('T-P1-08', 'A1', 'D-026');

it('has a human display label for every configured permission in both languages', function (string $locale) {
    foreach (config('permissions.permissions') as $permission) {
        $label = __('permissions.'.$permission['name'], [], $locale);
        expect($label)->not->toBe('permissions.'.$permission['name'])->not->toBe($permission['name']);
    }
})->with(['ar', 'en'])->group('T-P1-08', 'A1', 'D-026');
