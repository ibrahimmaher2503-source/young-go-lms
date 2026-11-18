<?php

declare(strict_types=1);

use App\Filament\Shared\WorkspaceAvatarProvider;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
});

it('uses the shared branded login layout on every panel', function (string $panel) {
    $this->get('/'.$panel.'/login')->assertOk()->assertSee('yg-auth-layout', false);
})->with(['admin', 'instructor', 'finance'])->group('T-P1-08', 'A1', 'A4', 'D-026');

it('shows the admin overview with a real learner count and authorized resources', function () {
    $actor = User::factory()->create(['locale' => 'en'])->assignRole('super_admin');
    User::factory()->count(2)->create()->each(fn (User $learner) => $learner->assignRole('student'));
    $this->actingAs($actor)->get('/admin')->assertOk()
        ->assertSee(__('staff.overview', [], 'en'))
        ->assertSee('yg-summary-strip', false)
        ->assertSee('data-workspace-value="2"', false)
        ->assertSee('data-dashboard-manage-courses', false)
        ->assertSee('href="'.route('filament.admin.resources.staff-users.index').'"', false);
})->group('T-P1-08', 'A4', 'D-026');

it('gives an instructor a designed empty workspace without administrator metrics', function () {
    $actor = User::factory()->create(['locale' => 'en'])->assignRole('instructor_academy');
    $this->actingAs($actor)->get('/instructor')->assertOk()
        ->assertSee('yg-dashboard-intro', false)
        ->assertDontSee('yg-summary-strip', false)
        ->assertDontSee('href="'.route('filament.admin.resources.staff-users.index').'"', false);
})->group('T-P1-08', 'A1', 'D-026');

it('renders account avatars locally without sending names to a third party', function () {
    expect(class_exists(WorkspaceAvatarProvider::class))->toBeTrue();
    $user = User::factory()->make(['name' => 'سارة أحمد']);
    $uri = app(WorkspaceAvatarProvider::class)->get($user);
    expect($uri)->toStartWith('data:image/svg+xml;base64,');
    $svg = base64_decode(substr($uri, strlen('data:image/svg+xml;base64,')), true);
    expect($svg)->toContain('>س</text>')->not->toContain('https://', 'سارة أحمد');
})->group('T-P1-08', 'A4', 'D-026');
