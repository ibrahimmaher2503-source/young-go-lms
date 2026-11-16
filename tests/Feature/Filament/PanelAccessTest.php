<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects guests to the matching staff panel login', function (string $panel) {
    $this->get('/'.$panel)->assertRedirect('/'.$panel.'/login');
})->with(['admin', 'instructor', 'finance'])->group('T-P1-08', 'A1');

it('permits only the assigned panel routes for each role', function (string $role, array $allowed, string $panel) {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    $user = User::factory()->create()->assignRole($role);
    $this->actingAs($user);

    $response = $this->get('/'.$panel)->assertStatus(in_array($panel, $allowed, true) ? 200 : 403);
    $menus = [
        'staff-users' => in_array($role, ['super_admin', 'admin', 'support'], true),
        'audit.audit-logs' => in_array($role, ['super_admin', 'admin'], true),
        'role-permissions' => $role === 'super_admin',
    ];
    foreach ($menus as $resource => $visible) {
        $link = 'href="'.route('filament.admin.resources.'.$resource.'.index').'"';
        if ($panel === 'admin' && $visible) {
            $response->assertSee($link, false);
        } else {
            $response->assertDontSee($link, false);
        }
    }
})->with([
    ['super_admin', ['admin', 'instructor', 'finance']],
    ['admin', ['admin']],
    ['content_manager', ['admin']],
    ['support', ['admin']],
    ['marketing', ['admin']],
    ['instructor_academy', ['instructor']],
    ['instructor_independent', ['instructor']],
    ['finance', ['finance']],
    ['student', []],
    ['parent', []],
])->with(['admin', 'instructor', 'finance'])->group('T-P1-08', 'A1');

it('persists the panel language and returns to the same panel', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    $user = User::factory()->create(['locale' => 'en'])->assignRole('finance');
    $this->actingAs($user)->get('/finance')->assertOk()
        ->assertSee('name="return_panel" value="finance"', false);
    $this->post('/locale', ['locale' => 'ar', 'return_panel' => 'finance'])->assertRedirect('/finance');
    expect($user->refresh()->locale)->toBe('ar');
    $this->get('/finance')->assertOk()->assertSee('dir="rtl"', false);
    $this->postJson('/locale', ['locale' => 'en', 'return_panel' => 'external'])->assertUnprocessable();
})->group('T-P1-08', 'A1');
