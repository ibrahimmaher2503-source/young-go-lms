<?php

declare(strict_types=1);

use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Actions\GrantOrderEntitlements;
use App\Domain\Commerce\Actions\TransitionOrder;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\ProductType;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Shared\Markets\Market;
use App\Domain\Subscriptions\Actions\CancelSubscription;
use App\Domain\Subscriptions\Actions\ExpireSubscriptions;
use App\Domain\Subscriptions\Actions\SavePlan;
use App\Domain\Subscriptions\Actions\UpdateSubscriptionPeriod;
use App\Domain\Subscriptions\Subscription;
use App\Domain\Subscriptions\SubscriptionStatus;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Carbon::setTestNow('2026-11-21 10:00:00');
});

afterEach(fn () => Carbon::setTestNow());

it('allows admin to extend or edit a subscription period end date, syncs course entitlements and reactivates expired/cancelled subscriptions', function (): void {
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة اشتراك', 'en' => 'Sub Course'],
        'slug_ar' => 'sub-c-ar', 'slug_en' => 'sub-c-en', 'status' => 'published', 'access_mode' => Course::ACCESS_SUBSCRIPTION,
    ]);
    $plan = app(SavePlan::class)($admin, [
        'name_ar' => 'خطة شهرية', 'name_en' => 'Monthly Plan', 'term' => 'monthly', 'is_active' => true,
        'prices' => [Market::Egypt->value => 15000], 'course_ids' => [$course->id],
    ]);

    $order = app(CreateOrder::class)($learner, Market::Egypt, [new OrderLine(ProductType::Plan, $plan->id, $learner)], 'sub-edit-1');
    app(TransitionOrder::class)($order, OrderStatus::Paid);
    app(GrantOrderEntitlements::class)($order->refresh(), EntitlementSource::Purchase);

    $subscription = Subscription::query()->where('learner_id', $learner->id)->sole();
    app(CancelSubscription::class)($admin, $subscription);

    // Move past grace so it expires
    Carbon::setTestNow('2027-01-10 10:00:00');
    app(ExpireSubscriptions::class)();
    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Expired)
        ->and(app(EntitlementService::class)->canAccessCourse($learner, $course)->allowed)->toBeFalse();

    $newEndsAt = now()->addDays(20)->endOfDay();
    $period = app(UpdateSubscriptionPeriod::class)($admin, $subscription, $newEndsAt, true, 'Admin extension');

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->cancelled_at)->toBeNull()
        ->and($subscription->expired_at)->toBeNull()
        ->and($period->ends_at->toDateString())->toBe($newEndsAt->toDateString())
        ->and($period->grace_ends_at->toDateString())->toBe($newEndsAt->copy()->addDays($plan->grace_days)->toDateString())
        ->and(app(EntitlementService::class)->canAccessCourse($learner, $course)->allowed)->toBeTrue()
        ->and(Activity::query()->where('event', 'subscription.updated')->where('subject_id', $subscription->id)->count())->toBe(1);
});

it('refuses unauthorized actors or an end date before period start', function (): void {
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة ٢', 'en' => 'Sub Course 2'],
        'slug_ar' => 'sub-c2-ar', 'slug_en' => 'sub-c2-en', 'status' => 'published', 'access_mode' => Course::ACCESS_SUBSCRIPTION,
    ]);
    $plan = app(SavePlan::class)($admin, [
        'name_ar' => 'خطة ٢', 'name_en' => 'Plan 2', 'term' => 'monthly', 'is_active' => true,
        'prices' => [Market::Egypt->value => 15000], 'course_ids' => [$course->id],
    ]);
    $order = app(CreateOrder::class)($learner, Market::Egypt, [new OrderLine(ProductType::Plan, $plan->id, $learner)], 'sub-edit-2');
    app(TransitionOrder::class)($order, OrderStatus::Paid);
    app(GrantOrderEntitlements::class)($order->refresh(), EntitlementSource::Purchase);
    $subscription = Subscription::query()->where('learner_id', $learner->id)->sole();

    expect(fn () => app(UpdateSubscriptionPeriod::class)($learner, $subscription, now()->addMonth(), true, 'Hack'))
        ->toThrow(AuthorizationException::class);

    expect(fn () => app(UpdateSubscriptionPeriod::class)($admin, $subscription, now()->subDays(5), true, 'Before start'))
        ->toThrow(ValidationException::class);
});
