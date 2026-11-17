<?php

declare(strict_types=1);

use App\Filament\Resources\StaffUsers\Pages\ListStaffUsers;
use App\Filament\Resources\StaffUsers\StaffUserResource;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
});

it('shows the searchable and filterable users list only to users with view permission', function () {
    $viewer = User::factory()->create();
    $viewer->givePermissionTo('panel.admin.access');

    $this->actingAs($viewer)
        ->get(route('filament.admin.resources.staff-users.index'))
        ->assertForbidden();

    $viewer->givePermissionTo('user.view');

    $first = User::factory()->create(['name' => 'Nora Mahmoud', 'email' => 'nora@example.test']);
    $second = User::factory()->create(['name' => 'Omar Hassan', 'email' => 'omar@example.test']);

    $this->get(route('filament.admin.resources.staff-users.index'))->assertOk();

    Livewire::test(ListStaffUsers::class)
        ->assertCanSeeTableRecords([$first, $second])
        ->assertTableFilterExists('status')
        ->searchTable('nora@example.test')
        ->assertCanSeeTableRecords([$first])
        ->assertCanNotSeeTableRecords([$second]);
});

it('keeps user records read only outside the audited role assignment action', function () {
    expect(StaffUserResource::canCreate())->toBeFalse()
        ->and(StaffUserResource::canEdit(new User))->toBeFalse()
        ->and(StaffUserResource::canDelete(new User))->toBeFalse()
        ->and(StaffUserResource::canDeleteAny())->toBeFalse();
});

it('lets only an active super admin assign non-super-admin roles through the audited action', function () {
    $actor = User::factory()->create();
    $actor->assignRole('super_admin');

    $target = User::factory()->create();
    $target->assignRole('student');

    $this->actingAs($actor)
        ->get(route('filament.admin.resources.staff-users.index'))
        ->assertOk();

    Livewire::test(ListStaffUsers::class)
        ->callAction(TestAction::make('assign_roles')->table($target), ['roles' => ['admin']])
        ->assertHasNoActionErrors();

    expect($target->fresh()->getRoleNames()->all())->toBe(['admin'])
        ->and(Activity::query()->where('event', 'user.roles_changed')->where('subject_id', $target->id)->exists())->toBeTrue();

});

it('prevents non-super-admins from submitting the role assignment action', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('panel.admin.access', 'user.view');

    $target = User::factory()->create();
    $target->assignRole('student');

    $this->actingAs($actor)
        ->get(route('filament.admin.resources.staff-users.index'))
        ->assertOk();

    Livewire::test(ListStaffUsers::class)
        ->assertTableActionHidden('assign_roles', $target);

    expect($target->fresh()->getRoleNames()->all())->toBe(['student']);
});

it('exports the current user filter only with the separate export permission', function () {
    $actor = User::factory()->create()->assignRole('super_admin');
    User::factory()->create(['name' => 'Visible export', 'email' => 'export@example.test', 'status' => 'active']);
    User::factory()->create(['name' => 'Hidden export', 'email' => 'hidden@example.test', 'status' => 'suspended']);
    $this->actingAs($actor)->get(route('filament.admin.resources.staff-users.index'))->assertOk();

    $component = Livewire::test(ListStaffUsers::class)
        ->filterTable('status', 'active')->searchTable('export@example.test')
        ->callAction(TestAction::make('exportCsv'))
        ->assertFileDownloaded('users.csv');

    $csv = base64_decode($component->effects['download']['content'], true);
    expect($csv)->toContain('Visible export', 'export@example.test')->not->toContain('Hidden export', 'hidden@example.test');
})->group('T-P1-08', 'A3');

it('does not download users for a viewer without export permission', function () {
    $actor = User::factory()->create();
    $actor->givePermissionTo('panel.admin.access', 'user.view');
    $this->actingAs($actor)->get(route('filament.admin.resources.staff-users.index'))->assertOk();

    Livewire::test(ListStaffUsers::class)
        ->assertActionHidden(TestAction::make('exportCsv'))
        ->assertNoFileDownloaded();
})->group('T-P1-08', 'A3');

it('renders localized user headings and account statuses in both locales', function (string $locale) {
    $actor = User::factory()->create(['locale' => $locale])->assignRole('super_admin');
    $this->actingAs($actor)->get(route('filament.admin.resources.staff-users.index'))
        ->assertOk()
        ->assertSee(__('staff.manage_users', [], $locale))
        ->assertSee('lang="'.$locale.'"', false);

    app()->setLocale($locale);
    Livewire::test(ListStaffUsers::class)
        ->assertTableColumnFormattedStateSet('status', __('staff.active', [], $locale), $actor);
})->with(['ar', 'en'])->group('T-P1-08', 'A4');
