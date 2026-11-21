<?php

declare(strict_types=1);

use App\Domain\Commerce\Actions\ApproveManualPaymentProof;
use App\Domain\Commerce\Actions\ApproveRefund;
use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Actions\ExecuteRefund;
use App\Domain\Commerce\Actions\RequestRefund;
use App\Domain\Commerce\Actions\SaveBundle;
use App\Domain\Commerce\Actions\SaveCoupon;
use App\Domain\Commerce\Actions\SubmitManualPaymentProof;
use App\Domain\Commerce\CouponRedemption;
use App\Domain\Commerce\CouponRedemptionStatus;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Commerce\ManualPaymentProof;
use App\Domain\Commerce\ManualPaymentStatus;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\PaymentAttempt;
use App\Domain\Commerce\PaymentAttemptStatus;
use App\Domain\Commerce\PaymentEvent;
use App\Domain\Commerce\PaymentEventOutcome;
use App\Domain\Commerce\Payments\FakePaymentGateway;
use App\Domain\Commerce\ProductType;
use App\Domain\Commerce\Refund;
use App\Domain\Commerce\RefundStatus;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\InstructorFinance\Actions\GenerateInstructorStatement;
use App\Domain\InstructorFinance\Actions\SaveCommissionRule;
use App\Domain\InstructorFinance\LedgerEntry;
use App\Domain\InstructorFinance\LedgerEntryType;
use App\Domain\InstructorFinance\Queries\LedgerTotals;
use App\Domain\Shared\Markets\Market;
use App\Domain\Subscriptions\Actions\RenewSubscription;
use App\Domain\Subscriptions\Actions\SavePlan;
use App\Domain\Subscriptions\Subscription;
use App\Domain\Subscriptions\SubscriptionPeriod;
use App\Domain\Subscriptions\SubscriptionStatus;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\LaravelPdf\Facades\Pdf;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    // A unique private disk per run (manual transfer proofs), removed again in afterEach.
    config(['media.disk' => 'p8demo-'.uniqid(), 'refunds.revoke_access' => 'full']);
    Storage::fake(config('media.disk'));
    Pdf::fake();
    Carbon::setTestNow('2026-10-15 10:00:00');
});

afterEach(function (): void {
    File::deleteDirectory(storage_path('framework/testing/disks/'.config('media.disk')));
    Carbon::setTestNow();
});

/** Acts as one user: a fresh session per actor, the way a person switching accounts gets one. */
function p8As($test, User $user)
{
    $test->flushSession();
    app('auth')->forgetGuards();

    return $test->actingAs($user);
}

function p8User(string $role): User
{
    return User::factory()->create(['status' => 'active'])->assignRole($role);
}

/** A published, paid course in Egypt (price in minor units), optionally owned by an instructor for the ledger. */
function p8Course(string $suffix, int $priceMinor = 25000, ?User $owner = null, string $access = 'paid'): Course
{
    $course = Course::query()->create([
        'title' => ['en' => 'P8 '.$suffix, 'ar' => 'P8 '.$suffix],
        'slug_ar' => 'p8-ar-'.$suffix.'-'.uniqid(), 'slug_en' => 'p8-en-'.$suffix.'-'.uniqid(),
        'status' => 'published', 'access_mode' => $access, 'owner_instructor_id' => $owner?->id,
    ]);
    CoursePrice::query()->create(['course_id' => $course->id, 'market' => Market::Egypt->value, 'currency' => 'EGP', 'amount_minor' => $priceMinor]);

    return $course;
}

/** Latest checkout attempt of an order. */
function p8Attempt(Order $order): PaymentAttempt
{
    return PaymentAttempt::query()->where('order_id', $order->getKey())->latest('sequence')->firstOrFail();
}

/** Starts a card payment from the order page (the checkout creates a fake gateway attempt). */
function p8Checkout($test, User $payer, Order $order): PaymentAttempt
{
    p8As($test, $payer)->post(route('checkout.pay', $order), ['method' => 'card', 'terms' => '1'])->assertRedirect();

    return p8Attempt($order);
}

/** The signed callback the fake gateway sends for a successful payment of an attempt. */
function p8Callback(PaymentAttempt $attempt): array
{
    return [
        'id' => 'fake_txn_'.$attempt->reference.'_pay', 'reference' => $attempt->reference, 'order_id' => $attempt->provider_order_id,
        'amount_minor' => $attempt->amount_minor, 'currency' => $attempt->currency,
        'success' => true, 'pending' => false, 'refunded' => false, 'voided' => false,
    ];
}

/** Delivers a signed callback to the webhook route, exactly as the gateway would. */
function p8Deliver($test, array $payload)
{
    return $test->postJson(route('webhooks.paymob'), $payload, ['X-Payment-Signature' => FakePaymentGateway::sign($payload)]);
}

function p8HasAccess(User $learner, Course $course): bool
{
    app(EntitlementService::class)->flush();

    return app(EntitlementService::class)->canAccessCourse($learner, $course)->allowed;
}

function p8PeriodOf(Order $order): SubscriptionPeriod
{
    return SubscriptionPeriod::query()->whereHas('orderItem', static fn ($items) => $items->where('order_id', $order->getKey()))->firstOrFail();
}

/**
 * P8 commerce demo script, executed over HTTP and through the real actions with the fake gateway only (no Paymob credentials):
 * a parent buys a course for a child with a coupon -> a failed attempt is retried -> the signed fake webhook fulfils once and a
 * replay is a no-op -> a manual proof is approved by finance -> a bundle grants each course once -> a plan starts and renews ->
 * a full refund revokes access and reverses the instructor earning -> the ledger has one entry per source event and the statement
 * reconciles -> a Saudi-market checkout is refused.
 */
it('runs the P8 demo script end to end', function (): void {
    // Accounts: admin, finance, an independent instructor who owns the paid courses, a verified parent with a child, and two learners.
    $admin = p8User('super_admin');
    $finance = p8User('finance');
    $instructor = p8User('instructor_independent');
    $parent = User::factory()->create(['name' => 'P8 Parent'])->assignRole('parent');
    $child = User::factory()->create(['name' => 'P8 Child'])->assignRole('student');
    $classmate = p8User('student');
    $buyer = p8User('student');
    $subscriber = p8User('student');
    GuardianChildLink::query()->create(['guardian_id' => $parent->id, 'child_id' => $child->id, 'status' => 'verified', 'method' => 'created_by_parent', 'verified_at' => now()]);

    $courseA = p8Course('course-a', 25000, $instructor);
    $courseB = p8Course('course-b', 15000, $instructor);
    app(SaveCommissionRule::class)($finance, ['sale_type' => 'individual', 'rate_bps' => 2500, 'effective_from' => '2026-01-01']);
    app(SaveCoupon::class)($admin, [
        'code' => 'P8FLAT50', 'name' => 'P8 flat 50', 'discount_type' => 'fixed', 'amount_minor' => 5000, 'currency' => 'EGP',
        'starts_at' => now()->subDay(), 'ends_at' => now()->addDay(), 'is_active' => true, 'course_ids' => [],
    ]);

    // 1. Parent buys course A for the child with a coupon: 250.00 EGP less 50.00 EGP.
    p8As($this, $parent)->post(route('checkout.start'), [
        'type' => 'course', 'product_id' => $courseA->id, 'learner_id' => $child->id, 'key' => 'co-'.bin2hex(random_bytes(8)),
    ])->assertRedirect();
    $order = Order::query()->where('payer_id', $parent->id)->latest('id')->firstOrFail();
    expect($order->status)->toBe(OrderStatus::Pending);

    p8As($this, $parent)->post(route('checkout.coupon.apply', $order), ['code' => 'P8FLAT50'])->assertRedirect();
    $order->refresh();
    expect($order->total_minor)->toBe(20000)
        ->and(CouponRedemption::query()->where('order_id', $order->getKey())->value('status'))->toBe(CouponRedemptionStatus::Reserved);

    // 2. The first attempt fails at the fake gateway; the order stays pending and the parent retries with a new attempt.
    $failed = p8Checkout($this, $parent, $order);
    p8As($this, $parent)->post('/fake-gateway/'.$failed->reference, ['outcome' => 'fail'])->assertRedirect();
    expect($failed->refresh()->status)->toBe(PaymentAttemptStatus::Failed)
        ->and($order->refresh()->status)->toBe(OrderStatus::Pending);

    $retry = p8Checkout($this, $parent, $order);
    expect($retry->reference)->not->toBe($failed->reference)->and($retry->status)->toBe(PaymentAttemptStatus::Created);

    // 3. The signed fake webhook fulfils the order once; a replay of the same callback changes nothing.
    p8Deliver($this, p8Callback($retry))->assertOk()->assertJsonPath('status', 'ok');
    expect($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and($retry->refresh()->status)->toBe(PaymentAttemptStatus::Succeeded)
        ->and(CouponRedemption::query()->where('order_id', $order->getKey())->value('status'))->toBe(CouponRedemptionStatus::Redeemed);

    p8Deliver($this, p8Callback($retry))->assertOk();
    expect(PaymentEvent::query()->where('order_id', $order->getKey())->where('outcome', PaymentEventOutcome::Fulfilled->value)->count())->toBe(1)
        ->and(Entitlement::query()->where('learner_id', $child->id)->where('course_id', $courseA->id)->count())->toBe(1)
        ->and(p8HasAccess($child, $courseA))->toBeTrue();

    // The parent can download the sale receipt (PDF rendered through the fake PDF builder).
    p8As($this, $parent)->get(route('payments.receipt', $order))->assertOk();

    // 4. Manual proof: a classmate pays course B by bank transfer; finance approves the exact amount.
    $manualOrder = app(CreateOrder::class)($classmate, Market::Egypt, [new OrderLine(ProductType::Course, $courseB->id, $classmate)], 'p8-manual-'.uniqid());
    p8As($this, $classmate)->post(route('checkout.pay', $manualOrder), ['method' => 'manual', 'terms' => '1'])
        ->assertRedirect(route('manual-payments.create', $manualOrder));

    $proof = app(SubmitManualPaymentProof::class)($classmate, $manualOrder, [
        'payer_name' => 'P8 Classmate', 'sender_account' => '01001234567890', 'transfer_reference' => 'P8-TRX-0001',
        'amount_minor' => $manualOrder->total_minor, 'transferred_at' => now()->subHour(),
    ], UploadedFile::fake()->image('proof.jpg', 120, 80));
    expect($proof->status)->toBe(ManualPaymentStatus::Pending)->and($manualOrder->refresh()->status)->toBe(OrderStatus::Pending);

    app(ApproveManualPaymentProof::class)($finance, $proof, 'Matches the bank statement.');
    expect($proof->refresh()->status)->toBe(ManualPaymentStatus::Approved)
        ->and($manualOrder->refresh()->status)->toBe(OrderStatus::Paid)
        ->and(Entitlement::query()->where('learner_id', $classmate->id)->where('course_id', $courseB->id)->where('source_type', EntitlementSource::ManualPayment->value)->count())->toBe(1);

    // 5. Bundle: two courses bought together through the checkout; the signed callback grants each course exactly once.
    $courseC = p8Course('bundle-c', 15000, null, Course::ACCESS_BOTH);
    $courseD = p8Course('bundle-d', 15000, null, Course::ACCESS_BOTH);
    $bundle = app(SaveBundle::class)($admin, [
        'name_ar' => 'باقة P8', 'name_en' => 'P8 Bundle '.uniqid(), 'is_active' => true,
        'prices' => [Market::Egypt->value => 30000],
        'items' => [['course_id' => $courseC->id], ['course_id' => $courseD->id]],
    ]);
    p8As($this, $buyer)->post(route('checkout.start'), [
        'type' => 'bundle', 'product_id' => $bundle->id, 'learner_id' => $buyer->id, 'key' => 'co-'.bin2hex(random_bytes(8)),
    ])->assertRedirect();
    $bundleOrder = Order::query()->where('payer_id', $buyer->id)->latest('id')->firstOrFail();
    $bundleAttempt = p8Checkout($this, $buyer, $bundleOrder);

    p8Deliver($this, p8Callback($bundleAttempt))->assertOk();
    p8Deliver($this, p8Callback($bundleAttempt))->assertOk();
    expect($bundleOrder->refresh()->status)->toBe(OrderStatus::Paid)
        ->and(Entitlement::query()->where('learner_id', $buyer->id)->where('course_id', $courseC->id)->count())->toBe(1)
        ->and(Entitlement::query()->where('learner_id', $buyer->id)->where('course_id', $courseD->id)->count())->toBe(1);

    // 6. Subscription plan: the subscriber starts the monthly plan, then renews it three days before the period ends.
    $plan = app(SavePlan::class)($admin, [
        'name_ar' => 'خطة P8', 'name_en' => 'P8 Plan '.uniqid(), 'term' => 'monthly', 'is_active' => true,
        'prices' => [Market::Egypt->value => 5000], 'course_ids' => [$courseC->id, $courseD->id],
    ]);
    p8As($this, $subscriber)->post(route('checkout.start'), [
        'type' => 'plan', 'product_id' => $plan->id, 'learner_id' => $subscriber->id, 'key' => 'co-'.bin2hex(random_bytes(8)),
    ])->assertRedirect();
    $planOrder = Order::query()->where('payer_id', $subscriber->id)->latest('id')->firstOrFail();
    p8Deliver($this, p8Callback(p8Checkout($this, $subscriber, $planOrder)))->assertOk();

    $firstPeriod = p8PeriodOf($planOrder);
    $subscription = Subscription::query()->findOrFail($firstPeriod->subscription_id);
    expect($subscription->status)->toBe(SubscriptionStatus::Active);

    Carbon::setTestNow($firstPeriod->ends_at->copy()->subDays(3));
    $renewal = app(RenewSubscription::class)($subscriber, $subscription);
    p8Deliver($this, p8Callback(p8Checkout($this, $subscriber, $renewal)))->assertOk();

    $secondPeriod = p8PeriodOf($renewal);
    expect($secondPeriod->starts_at->toIso8601String())->toBe($firstPeriod->ends_at->toIso8601String())
        ->and($subscription->refresh()->status)->toBe(SubscriptionStatus::Active);

    // 7. Refund: finance requests a full refund of the parent's course order; admin approves and executes it.
    Carbon::setTestNow('2026-10-20 10:00:00');
    $refundRequest = app(RequestRefund::class)($finance, $order->refresh(), $order->total_minor, 'Family asked for a full refund');
    app(ApproveRefund::class)($admin, $refundRequest);
    $refund = app(ExecuteRefund::class)($admin, $refundRequest->refresh(), 'P8-BANK-0001');

    expect($refund->status)->toBe(RefundStatus::Executed)
        ->and($order->refresh()->status)->toBe(OrderStatus::Refunded)
        ->and(Entitlement::query()->where('learner_id', $child->id)->where('course_id', $courseA->id)->where('status', 'revoked')->count())->toBe(1)
        ->and(p8HasAccess($child, $courseA))->toBeFalse();

    p8As($this, $parent)->get(route('payments.refund-receipt', ['order' => $order->getKey(), 'refund' => $refund->getKey()]))->assertOk();

    // 8. Ledger: one earning per paid course order (course A and the manual course B), one reversal for the refund.
    $ledger = LedgerEntry::query()->where('instructor_id', $instructor->id)->get();
    expect($ledger->where('type', LedgerEntryType::Earning)->count())->toBe(2)
        ->and($ledger->where('type', LedgerEntryType::Reversal)->count())->toBe(1)
        ->and($ledger->where('order_id', $order->getKey())->count())->toBe(2);

    // The statement for October freezes the ledger and reconciles: closing = balance forward + net - settled.
    // A month can only be frozen once it has ended, so the clock moves to early November.
    Carbon::setTestNow('2026-11-02 09:00:00');
    $statement = app(GenerateInstructorStatement::class)($finance, $instructor->id, '2026-10', 'EGP');
    $totals = app(LedgerTotals::class)->forInstructor($instructor->id)['EGP'];
    expect($statement->earnings_minor)->toBe($totals['earnings'])
        ->and($statement->reversals_minor)->toBe($totals['reversals'])
        ->and($statement->net_minor)->toBe($totals['net'])
        ->and($statement->entry_count)->toBe(3)
        ->and($statement->closing_balance_minor)->toBe($statement->balance_forward_minor + $statement->net_minor - $statement->settled_minor)
        ->and($statement->net_minor)->toBe($statement->earnings_minor + $statement->reversals_minor + $statement->adjustments_minor);

    // 9. Saudi market: checkout and order creation are refused and nothing is written.
    $ordersBefore = Order::query()->count();
    expect(fn () => app(CreateOrder::class)($buyer, Market::SaudiArabia, [new OrderLine(ProductType::Course, $courseA->id, $buyer)], 'p8-sa-'.uniqid()))
        ->toThrow(ValidationException::class);
    expect(Order::query()->count())->toBe($ordersBefore)
        ->and(ManualPaymentProof::query()->count())->toBe(1)
        ->and(Refund::query()->where('status', RefundStatus::Executed)->count())->toBe(1);
})->group('T-P8-MG', 'A1')->group('payments');
