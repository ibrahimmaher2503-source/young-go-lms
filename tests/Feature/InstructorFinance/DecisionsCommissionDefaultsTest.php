<?php

declare(strict_types=1);

// T-DECISIONS-2026-11-18 | O-008 O-010 O-011 | A1 A2

use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Actions\GrantOrderEntitlements;
use App\Domain\Commerce\Actions\TransitionOrder;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\ProductType;
use App\Domain\Commerce\Refund;
use App\Domain\Commerce\RefundMethod;
use App\Domain\Commerce\RefundStatus;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\InstructorFinance\Actions\RecordOrderEarnings;
use App\Domain\InstructorFinance\Actions\ReverseEarningsForRefund;
use App\Domain\InstructorFinance\Actions\SaveCommissionRule;
use App\Domain\InstructorFinance\LedgerEntry;
use App\Domain\InstructorFinance\SaleType;
use App\Domain\Shared\Markets\Market;
use App\Domain\Subscriptions\Actions\SavePlan;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Carbon::setTestNow(Carbon::parse('2026-11-18 10:00:00'));
    config(['commissions.default_rate_bps' => ['individual' => 7000, 'bundle' => 7000, 'subscription' => 7000], 'commissions.default_subscription_fixed_minor' => 0]);
});

afterEach(fn () => Carbon::setTestNow());

function dcdFinance(): User
{
    return User::factory()->create(['status' => 'active'])->assignRole('finance');
}

function dcdInstructor(): User
{
    return User::factory()->create(['status' => 'active'])->assignRole('instructor_independent');
}

function dcdCourse(?User $owner, int $priceMinor = 10000, string $access = 'paid'): Course
{
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة افتراضية', 'en' => 'Default course '.uniqid()],
        'slug_ar' => 'dcd-ar-'.uniqid(), 'slug_en' => 'dcd-en-'.uniqid(),
        'status' => 'published', 'access_mode' => $access,
        'owner_instructor_id' => $owner?->id,
    ]);
    CoursePrice::query()->create(['course_id' => $course->id, 'market' => Market::Egypt->value, 'currency' => 'EGP', 'amount_minor' => $priceMinor]);

    return $course;
}

function dcdPaidOrder(User $payer, array $lines): Order
{
    $order = app(CreateOrder::class)($payer, Market::Egypt, $lines, 'dcd-'.uniqid().'-key');
    app(TransitionOrder::class)($order, OrderStatus::Paid);
    app(GrantOrderEntitlements::class)($order, EntitlementSource::Purchase);
    app(RecordOrderEarnings::class)($order->fresh());

    return $order->fresh();
}

function dcdLearner(): User
{
    return User::factory()->create(['status' => 'active'])->assignRole('student');
}

function dcdEntries(Order $order)
{
    return LedgerEntry::query()->where('order_id', $order->id)->orderBy('id')->get();
}

function dcdRefund(Order $order, int $amount, User $finance): Refund
{
    return Refund::query()->create([
        'reference' => 'REF-'.uniqid(), 'order_id' => $order->id, 'amount_minor' => $amount, 'currency' => 'EGP',
        'reason' => 'Refund for commission defaults test', 'method' => RefundMethod::cases()[0]->value,
        'status' => RefundStatus::Executed->value, 'requested_by' => $finance->id, 'executed_by' => $finance->id, 'executed_at' => now(),
    ]);
}

// ---------------------------------------------------------------- A1: O-008 defaults

it('pays an independent instructor 70 percent of an undiscounted course sale when no rule exists', function (): void {
    $course = dcdCourse(dcdInstructor(), 10000);
    $order = dcdPaidOrder($payer = dcdLearner(), [new OrderLine(ProductType::Course, $course->id, $payer)]);

    $entry = dcdEntries($order)->sole();
    expect($entry->amount_minor)->toBe(7000)
        ->and($entry->rule_source)->toBe('config_default')
        ->and($entry->rate_bps)->toBe(7000);
})->group('T-DECISIONS-2026-11-18', 'O-008', 'A1');

it('writes no ledger entry for an academy-owned course with no owner instructor', function (): void {
    $course = dcdCourse(null, 10000);
    $order = dcdPaidOrder($payer = dcdLearner(), [new OrderLine(ProductType::Course, $course->id, $payer)]);

    expect(dcdEntries($order))->toHaveCount(0);
})->group('T-DECISIONS-2026-11-18', 'O-008', 'A1');

it('still lets a course rule override the 70 percent default', function (): void {
    $owner = dcdInstructor();
    $course = dcdCourse($owner, 10000);
    app(SaveCommissionRule::class)(dcdFinance(), [
        'sale_type' => 'individual', 'effective_from' => '2026-01-01', 'course_id' => $course->id, 'rate_bps' => 5000,
    ]);
    $order = dcdPaidOrder($payer = dcdLearner(), [new OrderLine(ProductType::Course, $course->id, $payer)]);

    expect(dcdEntries($order)->sole()->amount_minor)->toBe(5000);
})->group('T-DECISIONS-2026-11-18', 'O-008', 'A1');

// ---------------------------------------------------------------- A1: O-010 plan periods

it('splits a 9000 plan period over 3 courses into 3000 bases and 2100 earnings, total 6300', function (): void {
    $courses = [dcdCourse(dcdInstructor(), 10000, 'subscription_only'), dcdCourse(dcdInstructor(), 10000, 'subscription_only'), dcdCourse(dcdInstructor(), 10000, 'subscription_only')];
    $plan = app(SavePlan::class)(dcdFinance(), [
        'name_ar' => 'باقة افتراضية', 'name_en' => 'Default plan', 'term' => 'monthly', 'is_active' => true,
        'prices' => [Market::Egypt->value => 9000], 'course_ids' => array_map(fn (Course $c) => $c->id, $courses),
    ]);
    $learner = dcdLearner();
    $order = dcdPaidOrder($learner, [new OrderLine(ProductType::Plan, $plan->id, $learner)]);

    $entries = dcdEntries($order);
    expect($entries)->toHaveCount(3)
        ->and($entries->every(fn (LedgerEntry $e): bool => $e->sale_type === SaleType::Subscription))->toBeTrue()
        ->and($entries->pluck('base_minor')->all())->toBe([3000, 3000, 3000])
        ->and($entries->pluck('amount_minor')->all())->toBe([2100, 2100, 2100])
        ->and($entries->sum('amount_minor'))->toBe(6300)
        ->and($entries->pluck('source_key')->every(fn (string $k): bool => str_starts_with($k, 'subscription_period:')))->toBeTrue()
        ->and($entries->pluck('source_key')->every(fn (string $k): bool => str_contains($k, ':course:')))->toBeTrue();

    expect(app(RecordOrderEarnings::class)($order->fresh()))->toBe(0)
        ->and(dcdEntries($order))->toHaveCount(3);
})->group('T-DECISIONS-2026-11-18', 'O-010', 'A1');

it('splits a 10000 plan period over 3 courses so the bases sum to exactly 10000 with round half up shares', function (): void {
    $courses = [dcdCourse(dcdInstructor(), 10000, 'subscription_only'), dcdCourse(dcdInstructor(), 10000, 'subscription_only'), dcdCourse(dcdInstructor(), 10000, 'subscription_only')];
    $plan = app(SavePlan::class)(dcdFinance(), [
        'name_ar' => 'باقة افتراضية', 'name_en' => 'Remainder plan', 'term' => 'monthly', 'is_active' => true,
        'prices' => [Market::Egypt->value => 10000], 'course_ids' => array_map(fn (Course $c) => $c->id, $courses),
    ]);
    $learner = dcdLearner();
    $order = dcdPaidOrder($learner, [new OrderLine(ProductType::Plan, $plan->id, $learner)]);

    $entries = dcdEntries($order);
    expect($entries)->toHaveCount(3)
        ->and($entries->sum('base_minor'))->toBe(10000);
    foreach ($entries as $entry) {
        // 70% rounded half up, per share.
        expect($entry->amount_minor)->toBe(intdiv($entry->base_minor * 7000 + 5000, 10000));
    }
})->group('T-DECISIONS-2026-11-18', 'O-010', 'A1');

it('replaces the percentage with a fixed subscription rule and with the config fixed amount', function (): void {
    $course = dcdCourse(dcdInstructor(), 10000, 'subscription_only');
    $plan = app(SavePlan::class)(dcdFinance(), [
        'name_ar' => 'باقة ثابتة', 'name_en' => 'Fixed plan', 'term' => 'monthly', 'is_active' => true,
        'prices' => [Market::Egypt->value => 5000], 'course_ids' => [$course->id],
    ]);
    app(SaveCommissionRule::class)(dcdFinance(), ['sale_type' => 'subscription', 'effective_from' => '2026-01-01', 'fixed_amount_minor' => 1500, 'currency' => 'EGP']);
    $learner = dcdLearner();
    $fixedOrder = dcdPaidOrder($learner, [new OrderLine(ProductType::Plan, $plan->id, $learner)]);
    expect(dcdEntries($fixedOrder)->sole()->amount_minor)->toBe(1500);
})->group('T-DECISIONS-2026-11-18', 'O-010', 'A1');

it('replaces the percentage with the config fixed amount when no subscription rule exists', function (): void {
    config(['commissions.default_subscription_fixed_minor' => 1200]);
    $course = dcdCourse(dcdInstructor(), 10000, 'subscription_only');
    $plan = app(SavePlan::class)(dcdFinance(), [
        'name_ar' => 'باقة ثابتة 2', 'name_en' => 'Fixed plan two', 'term' => 'monthly', 'is_active' => true,
        'prices' => [Market::Egypt->value => 5000], 'course_ids' => [$course->id],
    ]);
    $payer = dcdLearner();
    $order = dcdPaidOrder($payer, [new OrderLine(ProductType::Plan, $plan->id, $payer)]);

    expect(dcdEntries($order)->sole()->amount_minor)->toBe(1200);
})->group('T-DECISIONS-2026-11-18', 'O-010', 'A1');

// ---------------------------------------------------------------- A2: O-011 refunds

it('reverses every plan earning to zero on a full refund of a plan order', function (): void {
    $courses = [dcdCourse(dcdInstructor(), 10000, 'subscription_only'), dcdCourse(dcdInstructor(), 10000, 'subscription_only'), dcdCourse(dcdInstructor(), 10000, 'subscription_only')];
    $plan = app(SavePlan::class)(dcdFinance(), [
        'name_ar' => 'باقة استرداد', 'name_en' => 'Refund plan', 'term' => 'monthly', 'is_active' => true,
        'prices' => [Market::Egypt->value => 9000], 'course_ids' => array_map(fn (Course $c) => $c->id, $courses),
    ]);
    $finance = dcdFinance();
    $learner = dcdLearner();
    $order = dcdPaidOrder($learner, [new OrderLine(ProductType::Plan, $plan->id, $learner)]);

    app(ReverseEarningsForRefund::class)(dcdRefund($order, $order->total_minor, $finance));

    $entries = LedgerEntry::query()->where('order_id', $order->id)->get();
    $reversals = $entries->where('type', 'reversal');
    expect($reversals)->toHaveCount(3)
        ->and($entries->sum('amount_minor'))->toBe(0)
        ->and($reversals->pluck('source_key')->every(fn (string $k): bool => str_starts_with($k, 'refund:')))->toBeTrue();
})->group('T-DECISIONS-2026-11-18', 'O-011', 'A2');

it('stores gross, discount and base on an undiscounted earning and reverses a partial refund proportionally', function (): void {
    // Discounted-line coverage (coupon net base) belongs to the coupon acceptance tests; this checks the stored columns at list price.
    $owner = dcdInstructor();
    $course = dcdCourse($owner, 10000);
    $finance = dcdFinance();
    $learner = dcdLearner();
    $order = dcdPaidOrder($learner, [new OrderLine(ProductType::Course, $course->id, $learner)]);
    $earning = dcdEntries($order)->sole();
    expect($earning->gross_minor)->toBe(10000)
        ->and($earning->discount_minor)->toBe(0)
        ->and($earning->base_minor)->toBe(10000)
        ->and($earning->amount_minor)->toBe(7000);

    app(ReverseEarningsForRefund::class)(dcdRefund($order, 2500, $finance));

    expect(LedgerEntry::query()->where('order_id', $order->id)->where('type', 'reversal')->sum('amount_minor'))->toBe(-1750);
})->group('T-DECISIONS-2026-11-18', 'O-011', 'A2');
