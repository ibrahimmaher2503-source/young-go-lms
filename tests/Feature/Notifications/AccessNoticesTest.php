<?php

declare(strict_types=1);

use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\Actions\RevokeEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Models\Course;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->learner = User::factory()->create(['locale' => 'ar'])->assignRole('student');
    $this->admin = User::factory()->create()->assignRole('admin');
    $this->course = Course::query()->create([
        'title' => ['en' => 'Drawing', 'ar' => 'الرسم'], 'slug_ar' => 'access-notices-ar', 'slug_en' => 'access-notices-en',
        'status' => 'published', 'format' => 'recorded', 'access_mode' => 'paid',
    ]);
});

it('sends a localized access notice once and only after commit', function (): void {
    $grant = app(GrantEntitlement::class);

    DB::beginTransaction();
    $grant($this->learner, $this->course, EntitlementSource::Free);
    expect($this->learner->notifications()->count())->toBe(0);
    DB::rollBack();
    expect($this->learner->notifications()->count())->toBe(0);

    DB::transaction(function () use ($grant): void {
        $grant($this->learner, $this->course, EntitlementSource::Free);
        $grant($this->learner, $this->course, EntitlementSource::Free);
        expect($this->learner->notifications()->count())->toBe(0);
    });

    $notice = $this->learner->notifications()->sole();
    expect($notice->data['title'])->toBe(__('notification_events.templates.access_granted.title', ['course' => 'الرسم'], 'ar'))
        ->and($notice->data['url'])->toBe(route('learn.course', $this->course))
        ->and($this->admin->notifications()->count())->toBe(0);
})->group('T-P9-05', 'T-P4-02', 'PRD-A20');

it('reports removal only after the last active entitlement is revoked', function (): void {
    $grant = app(GrantEntitlement::class);
    $revoke = app(RevokeEntitlement::class);
    $free = $grant($this->learner, $this->course, EntitlementSource::Free);
    $purchase = $grant($this->learner, $this->course, EntitlementSource::Purchase);
    $this->learner->notifications()->delete();

    $revoke($this->admin, $free, 'Removed free access');
    expect($this->learner->notifications()->count())->toBe(0);

    DB::beginTransaction();
    $revoke($this->admin, $purchase, 'Removed purchase access');
    expect($this->learner->notifications()->count())->toBe(0);
    DB::rollBack();

    $revoke($this->admin, $purchase, 'Removed purchase access');
    $revoke($this->admin, $purchase, 'Removed purchase access');

    expect($this->learner->notifications()->sole()->data['title'])
        ->toBe(__('notification_events.templates.access_revoked.title', ['course' => 'الرسم'], 'ar'));
})->group('T-P9-05', 'T-P4-02', 'PRD-A20');

it('sends each due expiry window once for the effective end date in the learner timezone', function (): void {
    $this->travelTo(CarbonImmutable::parse('2027-03-01 03:00 UTC'));
    $this->learner->forceFill(['locale' => 'en', 'timezone' => 'America/New_York'])->save();
    $grant = app(GrantEntitlement::class);
    $endsAt = now()->addDays(14);
    $grant($this->learner, $this->course, EntitlementSource::Purchase, endsAt: $endsAt);
    $grant($this->learner, $this->course, EntitlementSource::Free, endsAt: now()->addDays(12));
    $this->learner->notifications()->delete();

    $this->travelTo($endsAt->copy()->subDays(8));
    $this->artisan('entitlements:send-expiry-notices')->assertSuccessful();
    expect($this->learner->notifications()->count())->toBe(0);

    $this->travelTo($endsAt->copy()->subDays(5));
    $this->artisan('entitlements:send-expiry-notices')->assertSuccessful();
    expect($this->learner->notifications()->count())->toBe(0);

    foreach ([7, 3, 1] as $index => $days) {
        $this->travelTo($endsAt->copy()->subDays($days));
        $this->artisan('entitlements:send-expiry-notices')->assertSuccessful();
        $this->artisan('entitlements:send-expiry-notices')->assertSuccessful();
        expect($this->learner->notifications()->count())->toBe($index + 1);
    }

    expect($this->learner->notifications()->first()->data['message'])->toContain('March 14, 2027');
})->group('T-P9-05', 'T-P4-02', 'PRD-A20');

it('suppresses the expiry reminder when a renewal keeps access continuous', function (): void {
    $this->travelTo(CarbonImmutable::parse('2027-03-01 03:00 UTC'));
    $grant = app(GrantEntitlement::class);
    $endsAt = now()->addDays(14);
    $grant($this->learner, $this->course, EntitlementSource::Purchase, 'initial', endsAt: $endsAt);
    $grant($this->learner, $this->course, EntitlementSource::Purchase, 'renewal', startsAt: $endsAt, endsAt: $endsAt->copy()->addDays(14));
    $this->learner->notifications()->delete();

    $this->travelTo($endsAt->copy()->subDay());
    $this->artisan('entitlements:send-expiry-notices')->assertSuccessful();
    expect($this->learner->notifications()->count())->toBe(0);
})->group('T-P9-05', 'T-P4-02', 'PRD-A20');
