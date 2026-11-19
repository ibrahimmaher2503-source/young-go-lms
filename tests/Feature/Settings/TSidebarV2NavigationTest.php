<?php

declare(strict_types=1);

// T-SIDEBAR-V2 | cases 1, 2, 3, 5, 6 | StaffNavigation, HasQueueBadge, OrderResource

use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Coupons\CouponResource;
use App\Filament\Resources\Courses\CourseResource;
use App\Filament\Resources\HelpRequests\HelpRequestResource;
use App\Filament\Resources\ManualPayments\ManualPaymentProofResource;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Refunds\RefundResource;
use App\Filament\Shared\StaffNavigation;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Cache::flush();
});

it('orders the sidebar groups per panel as the catalog says', function (): void {
    expect(array_slice(StaffNavigation::order('admin'), 0, 3))->toBe(['inbox', 'sales', 'courses'])
        ->and(array_slice(StaffNavigation::order('finance'), 0, 2))->toBe(['sales', 'finance'])
        ->and(array_slice(StaffNavigation::order('instructor'), 0, 2))->toBe(['courses', 'inbox']);

    $admin = StaffNavigation::order('admin');
    expect(array_search('people', $admin, true))->toBeGreaterThan(array_search('courses', $admin, true))
        ->and(array_search('settings', $admin, true))->toBeGreaterThan(array_search('reports', $admin, true));
})->group('T-SIDEBAR-V2', 'case-1');

it('shows the rendered admin navigation with Dashboard first and groups in catalog order')
    ->todo('case 1 rendered groups: group label translation keys not confirmed; enable after app:check-translations keys are verified')
    ->group('T-SIDEBAR-V2', 'case-1');

it('keeps the item order inside each group', function (): void {
    $sales = StaffNavigation::GROUPS['sales'];
    expect(array_search(OrderResource::class, $sales, true))
        ->toBeLessThan(array_search(ManualPaymentProofResource::class, $sales, true))
        ->toBeLessThan(array_search(RefundResource::class, $sales, true))
        ->toBeLessThan(array_search(CouponResource::class, $sales, true));

    $courses = StaffNavigation::GROUPS['courses'];
    expect(array_search(CourseResource::class, $courses, true))->toBeLessThan(array_search(CategoryResource::class, $courses, true));

    expect(StaffNavigation::GROUPS['inbox'][0])->toBe(HelpRequestResource::class);
})->group('T-SIDEBAR-V2', 'case-2');

it('registers OrderResource in admin and finance but never in instructor', function (): void {
    expect(Filament::getPanel('admin')->getResources())->toContain(OrderResource::class)
        ->and(Filament::getPanel('finance')->getResources())->toContain(OrderResource::class)
        ->and(Filament::getPanel('instructor')->getResources())->not->toContain(OrderResource::class);
})->group('T-SIDEBAR-V2', 'case-3');

it('shows no queue badge to a user without access to the resource', function (): void {
    $marketing = User::factory()->create(['status' => 'active'])->assignRole('marketing');
    $this->actingAs($marketing);

    expect(RefundResource::getNavigationBadge())->toBeNull();
})->group('T-SIDEBAR-V2', 'case-5');

it('shows no badge and a warning colour when nothing waits, and caches the count per user', function (): void {
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $this->actingAs($admin);

    expect(RefundResource::getNavigationBadge())->toBeNull()
        ->and(RefundResource::getNavigationBadgeColor())->toBe('warning')
        ->and(Cache::has('nav-badge:'.RefundResource::class.':'.$admin->id))->toBeTrue();
})->group('T-SIDEBAR-V2', 'case-5');

it('keeps the badge cache separate for each user', function (): void {
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $finance = User::factory()->create(['status' => 'active'])->assignRole('finance');

    $this->actingAs($admin);
    RefundResource::getNavigationBadge();
    $this->actingAs($finance);
    RefundResource::getNavigationBadge();

    expect(Cache::has('nav-badge:'.RefundResource::class.':'.$admin->id))->toBeTrue()
        ->and(Cache::has('nav-badge:'.RefundResource::class.':'.$finance->id))->toBeTrue();
})->group('T-SIDEBAR-V2', 'case-5');

it('lets payment.view holders open the orders resource and blocks writes', function (): void {
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $this->actingAs($admin);

    expect(OrderResource::canViewAny())->toBeTrue()
        ->and(OrderResource::canCreate())->toBeFalse();

    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $this->actingAs($instructor);
    expect(OrderResource::canViewAny())->toBeFalse();
})->group('T-SIDEBAR-V2', 'case-6');

it('lists reference, learner, amount, status and gateway, filters by status and shows order lines')
    ->todo('case 6 table columns, gateway label and view dialog need a Livewire table fixture; not written yet')
    ->group('T-SIDEBAR-V2', 'case-6');
