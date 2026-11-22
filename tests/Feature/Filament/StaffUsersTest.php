<?php

declare(strict_types=1);

use App\Domain\Identity\Actions\CreateManagedAccount;
use App\Filament\Resources\StaffUsers\Pages\ListStaffUsers;
use App\Filament\Resources\StaffUsers\StaffUserResource;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
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

    $first = User::factory()->create(['name' => 'Nora Mahmoud', 'email' => 'nora@example.test', 'created_at' => now()->subDays(2)]);
    $second = User::factory()->create(['name' => 'Omar Hassan', 'email' => 'omar@example.test', 'created_at' => now()->subDay()]);

    $this->get(route('filament.admin.resources.staff-users.index'))->assertOk();

    Livewire::test(ListStaffUsers::class)
        ->sortTable('created_at', 'asc')
        ->assertCanSeeTableRecords([$first, $second], inOrder: true)
        ->sortTable('created_at', 'desc')
        ->assertCanSeeTableRecords([$second, $first], inOrder: true)
        ->assertTableFilterExists('status')
        ->searchTable('nora@example.test')
        ->assertCanSeeTableRecords([$first])
        ->assertCanNotSeeTableRecords([$second]);
});

it('keeps the generic user CRUD disabled outside the explicit audited actions', function () {
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

it('replaces user export with audited account creation for a user manager', function () {
    $actor = User::factory()->create()->assignRole('admin');
    $this->actingAs($actor)->get(route('filament.admin.resources.staff-users.index'))
        ->assertOk()->assertSee(__('staff.add_user'))->assertDontSee(__('common.export_csv'));

    $page = Livewire::test(ListStaffUsers::class)
        ->assertActionDoesNotExist('exportCsv')
        ->callAction('addUser', ['name' => '', 'email' => 'invalid'])->assertHasActionErrors(['name', 'email'])
        ->unmountAction()
        ->callAction('addUser', ['name' => 'Managed account', 'email' => 'Managed@Example.test'])
        ->assertHasNoActionErrors();

    $account = User::where('email', 'managed@example.test')->sole();
    expect($account->name)->toBe('Managed account')
        ->and($account->getRoleNames())->toBeEmpty()
        ->and($account->hasVerifiedEmail())->toBeFalse();
    $this->assertDatabaseMissing('consents', ['user_id' => $account->id]);
    $this->assertDatabaseHas('activity_log', ['event' => 'user.created', 'subject_id' => $account->id, 'causer_id' => $actor->id]);
    $page->callAction('addUser', ['name' => 'Duplicate', 'email' => 'MANAGED@example.test'])->assertHasActionErrors(['email']);

    $this->post(route('password.update'), [
        'token' => Password::createToken($account), 'email' => $account->email,
        'password' => 'new password from mailbox', 'password_confirmation' => 'new password from mailbox',
    ])->assertNoContent();
})->group('T-P1-08', 'A3', 'PRD-19.1');

it('does not expose user creation to a read-only staff viewer', function () {
    $actor = User::factory()->create()->assignRole('support');
    $this->actingAs($actor)->get(route('filament.admin.resources.staff-users.index'))
        ->assertOk()->assertDontSee(__('staff.add_user'));

    Livewire::test(ListStaffUsers::class)->assertActionHidden('addUser');
    expect(fn () => app(CreateManagedAccount::class)($actor, 'Blocked', 'blocked@example.test'))
        ->toThrow(AuthorizationException::class);
    $this->assertDatabaseMissing('users', ['email' => 'blocked@example.test']);
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
