<?php

declare(strict_types=1);

use App\Filament\Resources\StaffUsers\Pages\ListStaffUsers;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    $this->actingAs(User::factory()->create(['locale' => 'en'])->assignRole('super_admin'));
    $this->get('/admin/staff-users')->assertOk();
});

it('shows the real current page and disabled boundaries even for one page', function (string $locale) {
    app()->setLocale($locale);
    $html = Livewire::test(ListStaffUsers::class)->html();

    expect($html)->toContain('yg-workspace-pagination')
        ->and($html)->toContain('aria-current="page"')
        ->and($html)->toContain(__('staff.pagination_summary', ['first' => 1, 'last' => 1, 'total' => 1], $locale))
        ->and(substr_count($html, 'data-workspace-page-boundary="disabled"'))->toBe(2);
})->with(['ar', 'en'])->group('T-P1-08', 'A4', 'D-026');

it('keeps native page navigation and resets the actual results when searching', function () {
    $users = User::factory()->count(19)->create();
    $last = User::factory()->create(['name' => 'Last learner']);
    $page = Livewire::test(ListStaffUsers::class)->call('gotoPage', 3, 'page');

    $page->assertCanSeeTableRecords([$last])->assertCanNotSeeTableRecords($users)
        ->assertSeeHtml('data-workspace-current-page="3"');
    $page->searchTable('Last learner')->assertCanSeeTableRecords([$last])
        ->assertCanNotSeeTableRecords($users)->assertSeeHtml('data-workspace-current-page="1"');

    $page->searchTable('No matching account')->assertSeeHtml('fi-ta-empty-state')
        ->assertDontSeeHtml('class="fi-pagination yg-workspace-pagination"');
})->group('T-P1-08', 'A4', 'D-026');
