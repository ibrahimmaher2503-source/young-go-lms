<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('loads the bundled Arabic typeface in the actual staff workspace', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->actingAs(User::factory()->create(['locale' => 'ar'])->assignRole('super_admin'));
    $page = $this->visit('/admin/staff-users');
    $font = $page->script("async () => { await document.fonts.ready; return {family: getComputedStyle(document.body).fontFamily, loaded: document.fonts.check('400 16px \"Noto Sans Arabic\"')}; }");
    expect($font['family'])->toContain('Noto Sans Arabic')
        ->and($font['loaded'])->toBeTrue();
})->group('browser', 'T-P1-08', 'A4', 'D-026');
