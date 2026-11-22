<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
});

it('renders /admin/staff-users cleanly when email-less child accounts are present', function (): void {
    $admin = User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('admin');
    User::factory()->create([
        'email' => null,
        'username' => 'child.notext',
        'name' => 'No Email Child',
        'status' => 'active',
    ])->assignRole('student');

    $this->actingAs($admin)
        ->get('/admin/staff-users?tab=students')
        ->assertOk()
        ->assertSee('@child.notext');
});

it('uses the URL locale segment on fallback 404 pages without persisting session locale', function (): void {
    $this->get('/en/definitely-missing-page-check')
        ->assertNotFound()
        ->assertSee('lang="en" dir="ltr"', false)
        ->assertSessionMissing('locale');

    $this->get('/ar/definitely-missing-page-check')
        ->assertNotFound()
        ->assertSee('lang="ar" dir="rtl"', false)
        ->assertSessionMissing('locale');
});

it('serves localized /newsletter and /teach public pages under /ar and /en', function (): void {
    $this->get('/ar/newsletter')->assertOk()->assertSee('lang="ar" dir="rtl"', false);
    $this->get('/en/newsletter')->assertOk()->assertSee('lang="en" dir="ltr"', false);
    $this->get('/ar/teach')->assertOk()->assertSee('lang="ar" dir="rtl"', false);
    $this->get('/en/teach')->assertOk()->assertSee('lang="en" dir="ltr"', false);
});
