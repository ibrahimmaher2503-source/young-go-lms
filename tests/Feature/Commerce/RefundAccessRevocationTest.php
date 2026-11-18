<?php

declare(strict_types=1);

// T-P8-GAPS | PRD 17, 29.7 | A1 A2 A3 A4 A5

use App\Domain\Commerce\Actions\ApproveRefund;
use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Actions\ExecuteRefund;
use App\Domain\Commerce\Actions\GrantOrderEntitlements;
use App\Domain\Commerce\Actions\RequestRefund;
use App\Domain\Commerce\Actions\SaveBundle;
use App\Domain\Commerce\Actions\TransitionOrder;
use App\Domain\Commerce\Bundle;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\ProductType;
use App\Domain\Commerce\Refund;
use App\Domain\Commerce\RefundStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Shared\Markets\Market;
use App\Domain\Subscriptions\Actions\SavePlan;
use App\Domain\Subscriptions\Actions\SendSubscriptionRenewalReminders;
use App\Domain\Subscriptions\Subscription;
use App\Domain\Subscriptions\SubscriptionPeriod;
use App\Domain\Subscriptions\SubscriptionPlan;
use App\Domain\Subscriptions\SubscriptionReminder;
use App\Domain\Subscriptions\SubscriptionStatus;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    config(['refunds.revoke_access' => 'full', 'subscriptions.reminder_offsets_days' => [7, 3, 1]]);
    Carbon::setTestNow('2026-11-18 09:00:00');
});

afterEach(fn () => Carbon::setTestNow());

// ---------------------------------------------------------------------------
// Helpers (prefix tp8g keeps them apart from the other suites)
// ---------------------------------------------------------------------------

function tp8gStaff(string $role = 'finance'): User
{
    return User::factory()->create(['status' => 'active'])->assignRole($role);
}

function tp8gLearner(): User
{
    return User::factory()->create(['status' => 'active'])->assignRole('student');
}

/** A published course. Subscription-only courses carry no price; sold courses get an Egypt price. */
function tp8gCourse(string $access = 'subscription_only', int $priceMinor = 0): Course
{
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة استرداد', 'en' => 'Refund Gap Course '.uniqid()],
        'slug_ar' => 'tp8g-ar-'.uniqid(), 'slug_en' => 'tp8g-en-'.uniqid(),
        'status' => 'published', 'access_mode' => $access,
    ]);
    if ($priceMinor > 0) {
        CoursePrice::query()->create(['course_id' => $course->id, 'market' => Market::Egypt->value, 'currency' => 'EGP', 'amount_minor' => $priceMinor]);
    }

    return $course;
}

/** @param  list<Course>  $courses */
function tp8gPlan(User $staff, array $courses, int $priceMinor = 5000): SubscriptionPlan
{
    return app(SavePlan::class)($staff, [
        'name_ar' => 'باقة استرداد', 'name_en' => 'Refund Plan '.uniqid(), 'term' => 'monthly', 'is_active' => true,
        'prices' => [Market::Egypt->value => $priceMinor],
        'course_ids' => array_map(static fn (Course $c): int => $c->id, $courses),
    ]);
}

/** @param  list<Course>  $courses */
function tp8gBundle(User $staff, array $courses, int $priceMinor = 30000): Bundle
{
    return app(SaveBundle::class)($staff, [
        'name_ar' => 'باقة تجميع', 'name_en' => 'Refund Bundle '.uniqid(), 'is_active' => true,
        'prices' => [Market::Egypt->value => $priceMinor],
        'items' => array_map(static fn (Course $c): array => ['course_id' => $c->id, 'weight' => null], $courses),
    ]);
}

/** @param  list<OrderLine>  $lines */
function tp8gOrder(User $payer, array $lines): Order
{
    return app(CreateOrder::class)($payer, Market::Egypt, $lines, 'tp8g-'.uniqid());
}

/** Marks the order paid and grants it with the given source (purchase, no actor needed). */
function tp8gPay(Order $order): Order
{
    app(TransitionOrder::class)($order, OrderStatus::Paid);
    app(GrantOrderEntitlements::class)($order->refresh(), EntitlementSource::Purchase);

    return $order->refresh();
}

/** Request, approve and execute a manual refund of the given amount (external reference supplied). */
function tp8gRefund(User $finance, Order $order, int $amountMinor, string $reference = 'TP8G-BANK-0001'): Refund
{
    $refund = app(RequestRefund::class)($finance, $order, $amountMinor, 'Customer asked for a refund');
    app(ApproveRefund::class)($finance, $refund);

    return app(ExecuteRefund::class)($finance, $refund->refresh(), $reference);
}

/** The subscription that a plan order created, found through its period. */
function tp8gSubscriptionOf(Order $order): Subscription
{
    $period = SubscriptionPeriod::query()->whereHas('orderItem', static fn ($items) => $items->where('order_id', $order->getKey()))->firstOrFail();

    return Subscription::query()->findOrFail($period->subscription_id);
}

function tp8gPeriodOf(Order $order): SubscriptionPeriod
{
    return SubscriptionPeriod::query()->whereHas('orderItem', static fn ($items) => $items->where('order_id', $order->getKey()))->firstOrFail();
}

function tp8gHasAccess(User $learner, Course $course): bool
{
    app(EntitlementService::class)->flush();

    return app(EntitlementService::class)->canAccessCourse($learner, $course)->allowed;
}

function tp8gCount(string $source, string $ref, string $status): int
{
    return Entitlement::query()->where('source_type', $source)->where('source_ref', $ref)->where('status', $status)->count();
}

function tp8gAudit(string $event, ?int $subjectId = null): int
{
    return Activity::query()->where('event', $event)->when($subjectId !== null, static fn ($q) => $q->where('subject_id', $subjectId))->count();
}

/** A role that can request and execute refunds but not grant manual access. */
function tp8gRefunderWithoutGrant(): User
{
    Role::findOrCreate('tp8g-refunder', 'web')->syncPermissions(['payment.refund']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return User::factory()->create(['status' => 'active'])->assignRole('tp8g-refunder');
}

// ---------------------------------------------------------------------------
// A1: refund of a plan order ends its subscription access
// ---------------------------------------------------------------------------

it('A1 a full refund of a paid plan order revokes its subscription entitlements, closes the period and expires the subscription', function (): void {
    $staff = tp8gStaff('admin');
    $courses = [tp8gCourse(), tp8gCourse()];
    $plan = tp8gPlan($staff, $courses);
    $learner = tp8gLearner();
    $order = tp8gPay(tp8gOrder($learner, [new OrderLine(ProductType::Plan, $plan->id, $learner)]));
    $period = tp8gPeriodOf($order);
    $subscription = tp8gSubscriptionOf($order);

    expect(tp8gHasAccess($learner, $courses[0]))->toBeTrue()
        ->and(tp8gCount('subscription', $period->entitlementRef(), 'active'))->toBe(2);

    tp8gRefund($staff, $order, $order->total_minor);

    expect(tp8gHasAccess($learner, $courses[0]))->toBeFalse()
        ->and(tp8gHasAccess($learner, $courses[1]))->toBeFalse()
        ->and(tp8gCount('subscription', $period->entitlementRef(), 'revoked'))->toBe(2);

    $period->refresh();
    expect($period->ends_at->toIso8601String())->toBe(now()->toIso8601String())
        ->and($period->grace_ends_at->toIso8601String())->toBe(now()->toIso8601String());

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::Expired)
        ->and($subscription->expired_at)->not->toBeNull()
        ->and($order->refresh()->status)->toBe(OrderStatus::Refunded)
        ->and(tp8gAudit('subscription.refunded', $subscription->getKey()))->toBe(1);

    $refundAudit = Activity::query()->where('event', 'refund.executed')->latest('id')->firstOrFail();
    expect(json_encode($refundAudit->properties))->toContain('"entitlements_revoked":2');
})->group('T-P8-GAPS', 'A1');

it('A1 a partial refund under the full policy revokes nothing and leaves the period and subscription as they were', function (): void {
    $staff = tp8gStaff('admin');
    $course = tp8gCourse();
    $plan = tp8gPlan($staff, [$course]);
    $learner = tp8gLearner();
    $order = tp8gPay(tp8gOrder($learner, [new OrderLine(ProductType::Plan, $plan->id, $learner)]));
    $period = tp8gPeriodOf($order);
    $endsBefore = $period->ends_at->toIso8601String();

    tp8gRefund($staff, $order, intdiv($order->total_minor, 2));

    expect(tp8gHasAccess($learner, $course))->toBeTrue()
        ->and(tp8gCount('subscription', $period->entitlementRef(), 'revoked'))->toBe(0)
        ->and($period->refresh()->ends_at->toIso8601String())->toBe($endsBefore)
        ->and(tp8gSubscriptionOf($order)->status)->toBe(SubscriptionStatus::Active)
        ->and(tp8gAudit('subscription.refunded'))->toBe(0)
        ->and($order->refresh()->status)->toBe(OrderStatus::PartiallyRefunded);
})->group('T-P8-GAPS', 'A1');

it('A1 under the any policy a partial refund revokes the plan access and expires the subscription', function (): void {
    config(['refunds.revoke_access' => 'any']);
    $staff = tp8gStaff('admin');
    $course = tp8gCourse();
    $plan = tp8gPlan($staff, [$course]);
    $learner = tp8gLearner();
    $order = tp8gPay(tp8gOrder($learner, [new OrderLine(ProductType::Plan, $plan->id, $learner)]));

    tp8gRefund($staff, $order, intdiv($order->total_minor, 2));

    expect(tp8gHasAccess($learner, $course))->toBeFalse()
        ->and(tp8gSubscriptionOf($order)->status)->toBe(SubscriptionStatus::Expired)
        ->and($order->refresh()->status)->toBe(OrderStatus::PartiallyRefunded);
})->group('T-P8-GAPS', 'A1');

it('A1 under the none policy a full refund never revokes plan access or touches the period', function (): void {
    config(['refunds.revoke_access' => 'none']);
    $staff = tp8gStaff('admin');
    $course = tp8gCourse();
    $plan = tp8gPlan($staff, [$course]);
    $learner = tp8gLearner();
    $order = tp8gPay(tp8gOrder($learner, [new OrderLine(ProductType::Plan, $plan->id, $learner)]));
    $endsBefore = tp8gPeriodOf($order)->ends_at->toIso8601String();

    tp8gRefund($staff, $order, $order->total_minor);

    expect(tp8gHasAccess($learner, $course))->toBeTrue()
        ->and(tp8gPeriodOf($order)->ends_at->toIso8601String())->toBe($endsBefore)
        ->and(tp8gSubscriptionOf($order)->status)->toBe(SubscriptionStatus::Active)
        ->and($order->refresh()->status)->toBe(OrderStatus::Refunded)
        ->and(tp8gAudit('subscription.refunded'))->toBe(0);
})->group('T-P8-GAPS', 'A1');

it('A1 refunding the later of two plan periods revokes only that period and keeps the subscription active', function (): void {
    $staff = tp8gStaff('admin');
    $course = tp8gCourse();
    $plan = tp8gPlan($staff, [$course]);
    $learner = tp8gLearner();
    $first = tp8gPay(tp8gOrder($learner, [new OrderLine(ProductType::Plan, $plan->id, $learner)]));
    $firstPeriod = tp8gPeriodOf($first);

    // Three days before the first period ends the renewal window is open; the second period starts when the first one ends.
    Carbon::setTestNow($firstPeriod->ends_at->copy()->subDays(3));
    $second = tp8gPay(app(CreateOrder::class)($learner, Market::Egypt, [new OrderLine(ProductType::Plan, $plan->id, $learner)], 'tp8g-renew-'.uniqid()));
    $secondPeriod = tp8gPeriodOf($second);
    expect($secondPeriod->starts_at->toIso8601String())->toBe($firstPeriod->ends_at->toIso8601String());

    tp8gRefund($staff, $second, $second->total_minor);

    expect(tp8gCount('subscription', $secondPeriod->entitlementRef(), 'revoked'))->toBe(1)
        ->and(tp8gCount('subscription', $firstPeriod->entitlementRef(), 'active'))->toBe(1)
        ->and(tp8gSubscriptionOf($first)->status)->toBe(SubscriptionStatus::Active)
        ->and(tp8gHasAccess($learner, $course))->toBeTrue()
        ->and($firstPeriod->refresh()->ends_at->toIso8601String())->toBe($firstPeriod->ends_at->toIso8601String());
})->group('T-P8-GAPS', 'A1');

it('A1 executing an executed plan refund again is a no-op with no second audit', function (): void {
    $staff = tp8gStaff('admin');
    $course = tp8gCourse();
    $plan = tp8gPlan($staff, [$course]);
    $learner = tp8gLearner();
    $order = tp8gPay(tp8gOrder($learner, [new OrderLine(ProductType::Plan, $plan->id, $learner)]));
    $refund = tp8gRefund($staff, $order, $order->total_minor);
    $subscription = tp8gSubscriptionOf($order);
    $audits = tp8gAudit('subscription.refunded', $subscription->getKey());
    $executed = tp8gAudit('refund.executed');

    $again = app(ExecuteRefund::class)($staff, $refund->refresh(), 'TP8G-BANK-0001');

    expect($again->status)->toBe(RefundStatus::Executed)
        ->and(tp8gAudit('subscription.refunded', $subscription->getKey()))->toBe($audits)
        ->and(tp8gAudit('refund.executed'))->toBe($executed);
})->group('T-P8-GAPS', 'A1');

it('A1 a plan refund leaves no renewal reminder: the same subscription gets its 7-day reminder before the refund and nothing after it', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-12-01 09:00:00'));
    $staff = tp8gStaff('admin');
    $course = tp8gCourse();
    $plan = tp8gPlan($staff, [$course]);
    $learner = tp8gLearner();
    $order = tp8gPay(tp8gOrder($learner, [new OrderLine(ProductType::Plan, $plan->id, $learner)]));
    $subscription = tp8gSubscriptionOf($order);
    $endsAt = tp8gPeriodOf($order)->ends_at->copy();

    // Control: six days before the end the 7-day reminder goes out for this live subscription.
    Carbon::setTestNow($endsAt->copy()->subDays(6));
    expect(app(SendSubscriptionRenewalReminders::class)())->toBe(1);

    // Three days before the end, a live subscription would get the 3-day reminder. After the refund it must not.
    Carbon::setTestNow($endsAt->copy()->subDays(3));
    tp8gRefund($staff, $order, $order->total_minor);

    expect(app(SendSubscriptionRenewalReminders::class)())->toBe(0)
        ->and(SubscriptionReminder::query()->where('period_id', tp8gPeriodOf($order)->getKey())->pluck('offset_days')->sort()->values()->all())->toBe([7]);
})->group('T-P8-GAPS', 'A1');

it('A1 a refund without manual_entitlement.grant is refused before any money moves and the refund stays approved', function (): void {
    $staff = tp8gStaff('admin');
    $course = tp8gCourse();
    $plan = tp8gPlan($staff, [$course]);
    $learner = tp8gLearner();
    $order = tp8gPay(tp8gOrder($learner, [new OrderLine(ProductType::Plan, $plan->id, $learner)]));
    $refund = app(RequestRefund::class)($staff, $order, $order->total_minor, 'Customer asked for a refund');
    app(ApproveRefund::class)($staff, $refund);

    $restricted = tp8gRefunderWithoutGrant();

    expect(fn () => app(ExecuteRefund::class)($restricted, $refund->refresh(), 'TP8G-BANK-0002'))
        ->toThrow(AuthorizationException::class);

    expect($refund->refresh()->status)->toBe(RefundStatus::Approved)
        ->and($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and(tp8gHasAccess($learner, $course))->toBeTrue()
        ->and(tp8gSubscriptionOf($order)->status)->toBe(SubscriptionStatus::Active);
})->group('T-P8-GAPS', 'A1');

it('A1 after a plan refund a new renewal order can be created and paid and reactivates the subscription', function (): void {
    $staff = tp8gStaff('admin');
    $course = tp8gCourse();
    $plan = tp8gPlan($staff, [$course]);
    $learner = tp8gLearner();
    $order = tp8gPay(tp8gOrder($learner, [new OrderLine(ProductType::Plan, $plan->id, $learner)]));
    tp8gRefund($staff, $order, $order->total_minor);
    $subscription = tp8gSubscriptionOf($order);
    expect($subscription->status)->toBe(SubscriptionStatus::Expired);

    $renewal = tp8gPay(app(CreateOrder::class)($learner, Market::Egypt, [new OrderLine(ProductType::Plan, $plan->id, $learner)], 'tp8g-after-refund'));

    expect(tp8gHasAccess($learner, $course))->toBeTrue()
        ->and($subscription->refresh()->status)->not->toBe(SubscriptionStatus::Expired)
        ->and(tp8gPeriodOf($renewal)->starts_at->toIso8601String())->toBe(now()->toIso8601String());
})->group('T-P8-GAPS', 'A1');

// ---------------------------------------------------------------------------
// A2: bundle and mixed orders
// ---------------------------------------------------------------------------

it('A2 a full refund of a bundle order revokes only the bundle-sourced courses and keeps a separate purchase', function (): void {
    $staff = tp8gStaff('admin');
    $a = tp8gCourse('paid', 10000);
    $b = tp8gCourse('paid', 10000);
    $separate = tp8gCourse('paid', 10000);
    $bundle = tp8gBundle($staff, [$a, $b]);
    $learner = tp8gLearner();

    $separateOrder = tp8gPay(tp8gOrder($learner, [new OrderLine(ProductType::Course, $separate->id, $learner)]));
    $bundleOrder = tp8gPay(tp8gOrder($learner, [new OrderLine(ProductType::Bundle, $bundle->id, $learner)]));

    expect(tp8gCount('bundle', 'order:'.$bundleOrder->reference, 'active'))->toBe(2);

    tp8gRefund($staff, $bundleOrder, $bundleOrder->total_minor);

    expect(tp8gCount('bundle', 'order:'.$bundleOrder->reference, 'revoked'))->toBe(2)
        ->and(tp8gHasAccess($learner, $a))->toBeFalse()
        ->and(tp8gHasAccess($learner, $b))->toBeFalse()
        ->and(tp8gHasAccess($learner, $separate))->toBeTrue()
        ->and(tp8gCount('purchase', 'order:'.$separateOrder->reference, 'active'))->toBe(1)
        ->and($bundleOrder->refresh()->status)->toBe(OrderStatus::Refunded);
})->group('T-P8-GAPS', 'A2');

it('A2 a bundle refund does not touch a course the learner already owned, which the allocation skipped', function (): void {
    $staff = tp8gStaff('admin');
    $owned = tp8gCourse('paid', 10000);
    $other = tp8gCourse('paid', 10000);
    $bundle = tp8gBundle($staff, [$owned, $other]);
    $learner = tp8gLearner();

    $ownedOrder = tp8gPay(tp8gOrder($learner, [new OrderLine(ProductType::Course, $owned->id, $learner)]));
    $bundleOrder = tp8gPay(tp8gOrder($learner, [new OrderLine(ProductType::Bundle, $bundle->id, $learner)]));

    // The bundle grants only the course the learner did not already hold.
    expect(tp8gCount('bundle', 'order:'.$bundleOrder->reference, 'active'))->toBe(1);

    tp8gRefund($staff, $bundleOrder, $bundleOrder->total_minor);

    expect(tp8gHasAccess($learner, $owned))->toBeTrue()
        ->and(tp8gCount('purchase', 'order:'.$ownedOrder->reference, 'active'))->toBe(1)
        ->and(tp8gHasAccess($learner, $other))->toBeFalse();
})->group('T-P8-GAPS', 'A2');

it('A2 a full refund of a mixed order (course, bundle and plan) revokes all three sources', function (): void {
    $staff = tp8gStaff('admin');
    $single = tp8gCourse('paid', 10000);
    $bundled = tp8gCourse('paid', 10000);
    $bundledTwo = tp8gCourse('paid', 10000);
    $bundle = tp8gBundle($staff, [$bundled, $bundledTwo]);
    $planCourse = tp8gCourse();
    $plan = tp8gPlan($staff, [$planCourse]);
    $learner = tp8gLearner();

    $order = tp8gPay(tp8gOrder($learner, [
        new OrderLine(ProductType::Course, $single->id, $learner),
        new OrderLine(ProductType::Bundle, $bundle->id, $learner),
        new OrderLine(ProductType::Plan, $plan->id, $learner),
    ]));
    expect(tp8gHasAccess($learner, $single))->toBeTrue()
        ->and(tp8gHasAccess($learner, $bundled))->toBeTrue()
        ->and(tp8gHasAccess($learner, $bundledTwo))->toBeTrue()
        ->and(tp8gHasAccess($learner, $planCourse))->toBeTrue();

    tp8gRefund($staff, $order, $order->total_minor);

    expect(tp8gHasAccess($learner, $single))->toBeFalse()
        ->and(tp8gHasAccess($learner, $bundled))->toBeFalse()
        ->and(tp8gHasAccess($learner, $bundledTwo))->toBeFalse()
        ->and(tp8gHasAccess($learner, $planCourse))->toBeFalse()
        ->and(tp8gSubscriptionOf($order)->status)->toBe(SubscriptionStatus::Expired)
        ->and($order->refresh()->status)->toBe(OrderStatus::Refunded);
})->group('T-P8-GAPS', 'A2');

it('A1 a finance user with manual_entitlement.grant runs the full plan refund and access is revoked', function (): void {
    $finance = tp8gStaff('finance');
    $course = tp8gCourse();
    $plan = tp8gPlan($finance, [$course]);
    $learner = tp8gLearner();
    $order = tp8gPay(tp8gOrder($learner, [new OrderLine(ProductType::Plan, $plan->id, $learner)]));

    tp8gRefund($finance, $order, $order->total_minor);

    expect(tp8gHasAccess($learner, $course))->toBeFalse()
        ->and(tp8gSubscriptionOf($order)->status)->toBe(SubscriptionStatus::Expired)
        ->and($order->refresh()->status)->toBe(OrderStatus::Refunded);
})->group('T-P8-GAPS', 'A1');

it('A2 a bundle refund keeps a free-sourced course outside the bundle', function (): void {
    $staff = tp8gStaff('admin');
    $inside = tp8gCourse('paid', 10000);
    $insideTwo = tp8gCourse('paid', 10000);
    $outside = tp8gCourse('paid', 0);
    $bundle = tp8gBundle($staff, [$inside, $insideTwo]);
    $learner = tp8gLearner();
    app(GrantEntitlement::class)($learner, $outside, EntitlementSource::Free, 'free:tp8g-outside', null, null);
    $bundleOrder = tp8gPay(tp8gOrder($learner, [new OrderLine(ProductType::Bundle, $bundle->id, $learner)]));

    tp8gRefund($staff, $bundleOrder, $bundleOrder->total_minor);

    expect(tp8gHasAccess($learner, $outside))->toBeTrue()
        ->and(tp8gCount('free', 'free:tp8g-outside', 'active'))->toBe(1)
        ->and(tp8gHasAccess($learner, $inside))->toBeFalse();
})->group('T-P8-GAPS', 'A2');
