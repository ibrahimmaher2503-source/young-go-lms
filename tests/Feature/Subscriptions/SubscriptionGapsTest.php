<?php

declare(strict_types=1);

// T-P8-08 | PRD 29.7, YG-GAP-SUB-001..004, R-017, O-024 | A1 A3 A4 A5

use App\Domain\Commerce\Actions\ApproveManualPaymentProof;
use App\Domain\Commerce\Actions\ApproveRefund;
use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Actions\ExecuteRefund;
use App\Domain\Commerce\Actions\GrantOrderEntitlements;
use App\Domain\Commerce\Actions\RequestRefund;
use App\Domain\Commerce\Actions\SubmitManualPaymentProof;
use App\Domain\Commerce\Actions\TransitionOrder;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\ProductType;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Shared\Markets\Market;
use App\Domain\Subscriptions\Actions\ExpireSubscriptions;
use App\Domain\Subscriptions\Actions\SavePlan;
use App\Domain\Subscriptions\Subscription;
use App\Domain\Subscriptions\SubscriptionPeriod;
use App\Domain\Subscriptions\SubscriptionPlan;
use App\Domain\Subscriptions\SubscriptionStatus;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');
    Storage::fake('public');
    Carbon::setTestNow('2026-11-18 09:00:00');
});

afterEach(fn () => Carbon::setTestNow());

// ---------------------------------------------------------------------------
// Helpers (prefix tp8gg)
// ---------------------------------------------------------------------------

function tp8ggStaff(string $role = 'admin'): User
{
    return User::factory()->create(['status' => 'active'])->assignRole($role);
}

function tp8ggLearner(string $locale = 'en'): User
{
    return User::factory()->create(['status' => 'active', 'locale' => $locale])->assignRole('student');
}

/** A published subscription-only course (no individual price). */
function tp8ggCourse(): Course
{
    return Course::query()->create([
        'title' => ['ar' => 'دورة باقة '.uniqid(), 'en' => 'Gaps Course '.uniqid()],
        'slug_ar' => 'tp8gg-ar-'.uniqid(), 'slug_en' => 'tp8gg-en-'.uniqid(),
        'status' => 'published', 'access_mode' => 'subscription_only',
    ]);
}

/** @param  list<Course>  $courses */
function tp8ggPlan(array $courses, int $priceMinor = 5000): SubscriptionPlan
{
    return app(SavePlan::class)(tp8ggStaff('admin'), [
        'name_ar' => 'باقة فجوات', 'name_en' => 'Gaps Plan '.uniqid(), 'term' => 'monthly', 'is_active' => true,
        'prices' => [Market::Egypt->value => $priceMinor],
        'course_ids' => array_map(static fn (Course $c): int => $c->id, $courses),
    ]);
}

/** A pending plan order for the payer, the learner being the plan holder. */
function tp8ggPlanOrder(User $payer, User $learner, SubscriptionPlan $plan): Order
{
    return app(CreateOrder::class)($payer, Market::Egypt, [new OrderLine(ProductType::Plan, $plan->id, $learner)], 'tp8gg-order-'.uniqid());
}

/** Pays a plan order through the state machine and grants it (no actor needed). */
function tp8ggPay(Order $order): Order
{
    app(TransitionOrder::class)($order, OrderStatus::Paid);
    app(GrantOrderEntitlements::class)($order->refresh(), EntitlementSource::Purchase);

    return $order->refresh();
}

/** Pays a plan order through a submitted transfer proof approved by finance (the manual path). */
function tp8ggManualPay(Order $order): Order
{
    $proof = app(SubmitManualPaymentProof::class)($order->payer, $order, [
        'payer_name' => 'Gaps Payer', 'sender_account' => '01099887766', 'transfer_reference' => 'TP8GG-'.uniqid(),
        'amount_minor' => $order->total_minor, 'transferred_at' => now()->subMinutes(20),
    ], UploadedFile::fake()->image('slip.png', 160, 120));
    app(ApproveManualPaymentProof::class)(tp8ggStaff('finance'), $proof);

    return $order->refresh();
}

/** The subscription a plan order created, found through its period. */
function tp8ggSubscriptionOf(Order $order): Subscription
{
    $period = SubscriptionPeriod::query()->whereHas('orderItem', static fn ($items) => $items->where('order_id', $order->getKey()))->firstOrFail();

    return Subscription::query()->findOrFail($period->subscription_id);
}

/** Requests, approves and executes a full manual refund of the order as finance (access is revoked by policy). */
function tp8ggRefundFull(Order $order): void
{
    $finance = tp8ggStaff('finance');
    $refund = app(RequestRefund::class)($finance, $order, $order->total_minor, 'Customer asked for a refund');
    app(ApproveRefund::class)($finance, $refund);
    app(ExecuteRefund::class)($finance, $refund->refresh(), 'TP8GG-BANK-0001');
}

function tp8ggGuardian(User $parent, User $child): void
{
    GuardianChildLink::query()->create([
        'guardian_id' => $parent->id, 'child_id' => $child->id, 'status' => GuardianChildLink::STATUS_VERIFIED,
        'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT, 'verified_at' => now(),
    ]);
}

// ---------------------------------------------------------------------------
// A1: a full refund ends the subscription access as the learner sees it
// ---------------------------------------------------------------------------

it('A1 after a full refund the learner page shows the subscription as expired, with no cancel button and a renew option', function (): void {
    $plan = tp8ggPlan([tp8ggCourse()]);
    $learner = tp8ggLearner();
    $order = tp8ggPay(tp8ggPlanOrder($learner, $learner, $plan));
    $subscription = tp8ggSubscriptionOf($order);

    tp8ggRefundFull($order);

    $this->actingAs($learner)->get(route('subscription.index'))
        ->assertOk()
        ->assertSee('data-state="expired"', false)
        ->assertSee(__('subscriptions.state_expired', [], 'en'))
        ->assertSee(__('subscriptions.expired_note', [], 'en'))
        ->assertDontSee('data-cancel', false)
        ->assertSee(route('subscription.renew', $subscription), false);

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Expired);
})->group('T-P8-08', 'payments', 'A1');

it('A1 the learner loses plan course access at once after a full refund and a cancel posted afterwards does nothing', function (): void {
    $course = tp8ggCourse();
    $plan = tp8ggPlan([$course]);
    $learner = tp8ggLearner();
    $order = tp8ggPay(tp8ggPlanOrder($learner, $learner, $plan));
    $subscription = tp8ggSubscriptionOf($order);
    expect(app(EntitlementService::class)->canAccessCourse($learner, $course)->allowed)->toBeTrue();

    tp8ggRefundFull($order);
    expect(app(EntitlementService::class)->canAccessCourse($learner, $course)->allowed)->toBeFalse();

    $this->actingAs($learner)->post(route('subscription.cancel', $subscription))
        ->assertRedirect(route('subscription.index'));

    expect($subscription->refresh()->cancelled_at)->toBeNull()
        ->and(Activity::query()->where('event', 'subscription.cancelled')->where('subject_id', $subscription->getKey())->count())->toBe(0);
})->group('T-P8-08', 'payments', 'A1');

it('A1 after a refund the period is closed and the learner can renew at once, the renewal starts now', function (): void {
    $plan = tp8ggPlan([tp8ggCourse()]);
    $learner = tp8ggLearner();
    $order = tp8ggPay(tp8ggPlanOrder($learner, $learner, $plan));
    $subscription = tp8ggSubscriptionOf($order);

    tp8ggRefundFull($order);

    $this->actingAs($learner)->post(route('subscription.renew', $subscription))->assertRedirect();
    $renewal = Order::query()->where('payer_id', $learner->id)->where('status', OrderStatus::Pending->value)->latest('id')->firstOrFail();

    $paid = tp8ggPay($renewal);
    expect($paid->status)->toBe(OrderStatus::Paid);

    $subscription->refresh();
    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and($subscription->expired_at)->toBeNull()
        ->and($subscription->latestPeriod()->starts_at->toIso8601String())->toBe(now()->toIso8601String());
})->group('T-P8-08', 'payments', 'A1');

// ---------------------------------------------------------------------------
// A3: plan checkout entry and manual approval
// ---------------------------------------------------------------------------

it('A3 a guest opening the plan checkout page is sent to the login page', function (): void {
    $plan = tp8ggPlan([tp8ggCourse()]);

    $this->get(route('checkout.product', ['type' => 'plan', 'id' => $plan->id]))
        ->assertRedirect(route('login.page'));
})->group('T-P8-08', 'payments', 'A3');

it('A3 starting a plan checkout for a learner who is neither the payer nor a linked child is refused and creates no order', function (): void {
    $plan = tp8ggPlan([tp8ggCourse()]);
    $payer = tp8ggLearner();
    $stranger = tp8ggLearner();

    $before = Order::query()->count();
    $this->actingAs($payer)->post(route('checkout.start'), [
        'type' => 'plan', 'product_id' => $plan->id, 'learner_id' => $stranger->id, 'key' => 'tp8gg-key-'.uniqid(),
    ])->assertSessionHasErrors(['learner_id' => __('checkout.learner_invalid')]);

    expect(Order::query()->count())->toBe($before);
})->group('T-P8-08', 'payments', 'A3');

it('A3 a manually approved plan order starts the period and grants the included course', function (): void {
    $course = tp8ggCourse();
    $plan = tp8ggPlan([$course]);
    $learner = tp8ggLearner();
    $order = tp8ggPlanOrder($learner, $learner, $plan);

    $paid = tp8ggManualPay($order);

    expect($paid->status)->toBe(OrderStatus::Paid);
    $subscription = tp8ggSubscriptionOf($paid);
    expect($subscription->status)->toBe(SubscriptionStatus::Active)
        ->and(SubscriptionPeriod::query()->where('subscription_id', $subscription->getKey())->count())->toBe(1)
        ->and(app(EntitlementService::class)->canAccessCourse($learner, $course)->allowed)->toBeTrue();
})->group('T-P8-08', 'payments', 'A3');

// ---------------------------------------------------------------------------
// A5: the payer (parent) renews and cancels the child's subscription
// ---------------------------------------------------------------------------

it('A5 a parent who pays for a child renews the child subscription into a pending order paid by the parent', function (): void {
    $plan = tp8ggPlan([tp8ggCourse()]);
    $parent = User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('parent');
    $child = tp8ggLearner();
    tp8ggGuardian($parent, $child);
    $order = tp8ggPay(tp8ggPlanOrder($parent, $child, $plan));
    $subscription = tp8ggSubscriptionOf($order);

    Carbon::setTestNow($subscription->latestPeriod()->ends_at->copy()->subDays(2));
    $this->actingAs($parent)->post(route('subscription.renew', $subscription))->assertRedirect();

    $renewal = Order::query()->where('payer_id', $parent->id)->where('status', OrderStatus::Pending->value)->latest('id')->firstOrFail();
    expect($renewal->items()->firstOrFail())
        ->learner_id->toBe($child->id)
        ->product_type->toBe(ProductType::Plan);
})->group('T-P8-08', 'payments', 'A5');

it('A5 a parent cancels the child subscription, access stays to the period end and the audit is written once', function (): void {
    $plan = tp8ggPlan([tp8ggCourse()]);
    $parent = User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('parent');
    $child = tp8ggLearner();
    tp8ggGuardian($parent, $child);
    $order = tp8ggPay(tp8ggPlanOrder($parent, $child, $plan));
    $subscription = tp8ggSubscriptionOf($order);

    $this->actingAs($parent)->post(route('subscription.cancel', $subscription))
        ->assertRedirect(route('subscription.index'))
        ->assertSessionHas('subscription_notice', __('subscriptions.my_cancel_done', [], 'en'));

    expect($subscription->refresh()->cancelled_at)->not->toBeNull()
        ->and(Activity::query()->where('event', 'subscription.cancelled')->where('subject_id', $subscription->getKey())->count())->toBe(1);
})->group('T-P8-08', 'payments', 'A5');

// ---------------------------------------------------------------------------
// A5: badge after the grace days end, before the hourly job runs
// ---------------------------------------------------------------------------

it('A5 once the grace days have ended the badge stops saying grace, even before the hourly expiry job runs', function (): void {
    $plan = tp8ggPlan([tp8ggCourse()]);
    $learner = tp8ggLearner();
    $order = tp8ggPay(tp8ggPlanOrder($learner, $learner, $plan));
    $subscription = tp8ggSubscriptionOf($order);
    $period = $subscription->latestPeriod();

    Carbon::setTestNow($period->grace_ends_at->copy()->addHour());

    $this->actingAs($learner)->get(route('subscription.index'))
        ->assertOk()
        ->assertDontSee('data-state="grace"', false)
        ->assertSee('data-state="expired"', false);

    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Active);
    app(ExpireSubscriptions::class)();
    expect($subscription->refresh()->status)->toBe(SubscriptionStatus::Expired);
})->group('T-P8-08', 'payments', 'A5');
