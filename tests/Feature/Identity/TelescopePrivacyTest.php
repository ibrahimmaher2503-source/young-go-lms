<?php

declare(strict_types=1);

use App\Http\Middleware\EnforceTwoFactor;
use App\Http\Middleware\EnsureActiveAccount;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Laravel\Telescope\Http\Middleware\Authorize;
use Laravel\Telescope\Telescope;

uses(RefreshDatabase::class);

it('keeps diagnostic authorization independent of staff overrides', function (): void {
    $this->seed(RolePermissionSeeder::class);
    $staff = User::factory()->create()->assignRole('super_admin');

    expect(Gate::forUser($staff)->denies('viewTelescope'))->toBeTrue();
})->group('T-P1-03', 'YG-GAP-SEC-003');

it('restricts local diagnostics to authorized staff', function (): void {
    $this->seed(RolePermissionSeeder::class);
    app()->instance('env', 'local');
    $request = Request::create('/');

    expect(Telescope::check($request))->toBeFalse();
    $this->actingAs(User::factory()->create()->assignRole('student'));
    expect(Telescope::check($request))->toBeFalse();
    $this->actingAs(User::factory()->create()->assignRole('super_admin'));
    expect(Telescope::check($request))->toBeTrue();
})->group('T-P1-03', 'T-P1-11', 'YG-GAP-SEC-003');

it('requires active authenticated sessions and staff two factor on diagnostic routes', function (): void {
    expect(config('telescope.middleware'))->toBe([
        'web',
        'auth',
        EnsureActiveAccount::class,
        'auth.session',
        EnforceTwoFactor::class,
        Authorize::class,
    ]);
})->group('T-P1-11', 'ED-016');

it('excludes identity credential routes from diagnostic recording', function (string $path): void {
    expect(Request::create($path)->is(config('telescope.ignore_paths')))->toBeTrue();
})->with([
    '/security/two-factor',
    '/security/two-factor/confirm',
    '/security/two-factor/recovery-codes',
    '/two-factor-challenge',
    '/confirm-password',
    '/guardian/children',
    '/guardian/children/123/password',
])->group('T-P1-11', 'YG-GAP-SEC-003', 'YG-GAP-FAM-007');

it('keeps credential fields out of old input on validation redirects', function (): void {
    Route::post('/fixture/validation', static function (Request $request): void {
        $request->validate(['display_name' => ['integer']]);
    })->middleware('web');

    $this->from('/fixture/form')->post('/fixture/validation', [
        'display_name' => 'Example',
        'child_password' => 'fixture-value',
        'child_password_confirmation' => 'fixture-value',
        'code' => 'fixture-value',
    ])->assertRedirect('/fixture/form')->assertSessionHasErrors('display_name')
        ->assertSessionHas('_old_input', ['display_name' => 'Example']);
})->group('T-P1-11', 'YG-GAP-SEC-003', 'YG-GAP-FAM-007');
