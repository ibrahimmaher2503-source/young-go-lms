<?php

declare(strict_types=1);

// T-P8-GAPS | PRD 17, 29.7 | A1 A2 A3 A4 A5

use App\Domain\Demo\Actions\ResetDemoData;
use App\Domain\Demo\DemoAnchors;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    app()->setLocale('en');
});

it('demo:seed creates the finance demo account with the finance role and anchors it', function (): void {
    test()->artisan('demo:seed')->assertExitCode(0);

    $finance = User::query()->where('email', 'finance@demo.younggo.test')->firstOrFail();

    expect($finance->hasRole('finance'))->toBeTrue()
        ->and(DemoAnchors::ids('users'))->toContain($finance->getKey());
})->group('T-P8-GAPS', 'A1');

it('demo:reset removes the finance demo account with the other anchored demo records', function (): void {
    test()->artisan('demo:seed')->assertExitCode(0);
    $financeId = User::query()->where('email', 'finance@demo.younggo.test')->value('id');
    expect($financeId)->not->toBeNull();

    app(ResetDemoData::class)();

    expect(User::query()->whereKey($financeId)->exists())->toBeFalse()
        ->and(DB::table('demo_anchors')->where('table_name', 'users')->where('record_id', $financeId)->exists())->toBeFalse();
})->group('T-P8-GAPS', 'A1');
