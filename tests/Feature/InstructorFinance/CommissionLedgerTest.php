<?php

declare(strict_types=1);

// T-P8-09 | PRD 17.4, D-012..D-016, Arch 5.4, O-008 O-010 O-011 | A1 A2 A3

use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Actions\GrantOrderEntitlements;
use App\Domain\Commerce\Actions\SaveBundle;
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
use App\Domain\InstructorFinance\Actions\CloseCommissionRule;
use App\Domain\InstructorFinance\Actions\RecordLedgerAdjustment;
use App\Domain\InstructorFinance\Actions\RecordOrderEarnings;
use App\Domain\InstructorFinance\Actions\ReverseEarningsForRefund;
use App\Domain\InstructorFinance\Actions\SaveCommissionRule;
use App\Domain\InstructorFinance\CommissionRule;
use App\Domain\InstructorFinance\LedgerEntry;
use App\Domain\InstructorFinance\Queries\CommissionRules;
use App\Domain\InstructorFinance\Queries\LedgerTotals;
use App\Domain\InstructorFinance\SaleType;
use App\Domain\Shared\Markets\Market;
use App\Domain\Subscriptions\Actions\RenewSubscription;
use App\Domain\Subscriptions\Actions\SavePlan;
use App\Domain\Subscriptions\Subscription;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use LogicException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));
afterEach(fn () => Carbon::setTestNow());

function ledInstructor(): User
{
    return User::factory()->create(['status' => 'active'])->assignRole('instructor_independent');
}

function ledFinance(): User
{
    return User::factory()->create(['status' => 'active'])->assignRole('finance');
}

function ledCourse(?User $owner, int $priceMinor = 10000, string $access = 'paid'): Course
{
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة عمولة', 'en' => 'Ledger Course '.uniqid()],
        'slug_ar' => 'led-ar-'.uniqid(),
        'slug_en' => 'led-en-'.uniqid(),
        'status' => 'published',
        'access_mode' => $access,
        'owner_instructor_id' => $owner?->id,
    ]);
    CoursePrice::query()->create(['course_id' => $course->id, 'market' => Market::Egypt->value, 'currency' => 'EGP', 'amount_minor' => $priceMinor]);

    return $course;
}

/** Rule saved through the real action. Scope nulls mean "any". */
function ledRule(User $actor, array $data): CommissionRule
{
    return app(SaveCommissionRule::class)($actor, array_merge([
        'sale_type' => 'individual',
        'effective_from' => '2026-01-01',
    ], $data));
}

/** Pays an order: transition, grant, then the real earnings writer (no event hook in tests). */
function ledPaidOrder(User $payer, array $lines, bool $recordEarnings = true): Order
{
    $order = app(CreateOrder::class)($payer, Market::Egypt, $lines, 'led-'.uniqid().'-key');
    app(TransitionOrder::class)($order, OrderStatus::Paid);
    app(GrantOrderEntitlements::class)($order, EntitlementSource::Purchase);
    if ($recordEarnings) {
        app(RecordOrderEarnings::class)($order->fresh());
    }

    return $order->fresh();
}

function ledEntries(?int $orderId = null): Collection
{
    return LedgerEntry::query()->when($orderId, fn ($q) => $q->where('order_id', $orderId))->orderBy('id')->get();
}

// ---------------------------------------------------------------- A1: one entry per source event

it('writes the same earnings rows when RecordOrderEarnings runs twice', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-10 10:00:00'));
    $owner = ledInstructor();
    $course = ledCourse($owner, 10000);
    ledRule(ledFinance(), ['sale_type' => 'individual', 'rate_bps' => 2500]);
    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');

    $order = ledPaidOrder($learner, [new OrderLine(ProductType::Course, $course->id, $learner)]);
    $first = ledEntries($order->id)->pluck('id', 'source_key')->all();

    expect(app(RecordOrderEarnings::class)($order))->toBe(0)
        ->and(ledEntries($order->id)->pluck('id', 'source_key')->all())->toBe($first)
        ->and(count($first))->toBe(1);
})->group('T-P8-09', 'A1')->group('ledger');

it('gives a three course bundle three entries with rounded half up shares and bases that sum to the price', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-10 10:00:00'));
    $finance = ledFinance();
    $a = ledCourse(ledInstructor(), 10000);
    $b = ledCourse(ledInstructor(), 10000);
    $c = ledCourse(ledInstructor(), 10000);
    ledRule($finance, ['sale_type' => 'bundle', 'rate_bps' => 2500]);

    $bundle = app(SaveBundle::class)($finance, [
        'name_ar' => 'باقة عمولة',
        'name_en' => 'Commission bundle',
        'is_active' => true,
        'prices' => [Market::Egypt->value => 10000],
        'items' => [['course_id' => $a->id], ['course_id' => $b->id], ['course_id' => $c->id]],
    ]);

    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');
    $order = ledPaidOrder($learner, [new OrderLine(ProductType::Bundle, $bundle->id, $learner)]);

    $entries = ledEntries($order->id);
    expect($entries)->toHaveCount(3)
        ->and($entries->every(fn (LedgerEntry $e): bool => $e->allocation_id !== null))->toBeTrue()
        ->and($entries->sum('base_minor'))->toBe(10000)
        ->and($entries->pluck('amount_minor')->sort()->values()->all())->toBe([833, 833, 834]);
})->group('T-P8-09', 'A1')->group('ledger');

it('returns the same adjustment row when the same key is used twice and a new row without a key', function (): void {
    $finance = ledFinance();
    $instructor = ledInstructor();

    $first = app(RecordLedgerAdjustment::class)($finance, $instructor->id, 5000, 'EGP', 'Bonus for the spring launch', null, 'led-adj-key-1');
    $again = app(RecordLedgerAdjustment::class)($finance, $instructor->id, 5000, 'EGP', 'Bonus for the spring launch', null, 'led-adj-key-1');
    expect($again->id)->toBe($first->id);

    app(RecordLedgerAdjustment::class)($finance, $instructor->id, 5000, 'EGP', 'Bonus for the spring launch');
    app(RecordLedgerAdjustment::class)($finance, $instructor->id, 5000, 'EGP', 'Bonus for the spring launch');

    expect(LedgerEntry::query()->where('instructor_id', $instructor->id)->where('type', 'adjustment')->count())->toBe(3);
})->group('T-P8-09', 'A1')->group('ledger');

it('reverses exactly 100 percent of an earning on a full refund and nothing on a second run', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-10 10:00:00'));
    $finance = ledFinance();
    $course = ledCourse(ledInstructor(), 10001);
    ledRule($finance, ['sale_type' => 'individual', 'rate_bps' => 3333]);
    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');
    $order = ledPaidOrder($learner, [new OrderLine(ProductType::Course, $course->id, $learner)]);

    $refund = Refund::query()->create([
        'reference' => 'REF-'.uniqid(),
        'order_id' => $order->id,
        'amount_minor' => $order->total_minor,
        'currency' => 'EGP',
        'reason' => 'Full refund for the ledger test',
        'method' => RefundMethod::cases()[0]->value,
        'status' => RefundStatus::Executed->value,
        'requested_by' => $finance->id,
        'executed_by' => $finance->id,
        'executed_at' => now(),
    ]);

    app(ReverseEarningsForRefund::class)($refund);
    app(ReverseEarningsForRefund::class)($refund);

    $earning = ledEntries($order->id)->firstWhere('type', 'earning');
    $reversals = ledEntries($order->id)->where('type', 'reversal');
    expect($reversals)->toHaveCount(1)
        ->and($reversals->sum('amount_minor'))->toBe(-$earning->amount_minor)
        ->and($reversals->first()->reverses_entry_id)->toBe($earning->id);
})->group('T-P8-09', 'A1')->group('ledger');

it('reverses the right share of an earning on a partial refund, rounded half up', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-10 10:00:00'));
    $finance = ledFinance();
    $course = ledCourse(ledInstructor(), 10000);
    ledRule($finance, ['sale_type' => 'individual', 'rate_bps' => 2500]);
    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');
    $order = ledPaidOrder($learner, [new OrderLine(ProductType::Course, $course->id, $learner)]);

    $refund = Refund::query()->create([
        'reference' => 'REF-'.uniqid(), 'order_id' => $order->id, 'amount_minor' => 2500, 'currency' => 'EGP',
        'reason' => 'Partial refund for the ledger test', 'method' => RefundMethod::cases()[0]->value,
        'status' => RefundStatus::Executed->value, 'requested_by' => $finance->id, 'executed_at' => now(),
    ]);
    app(ReverseEarningsForRefund::class)($refund);

    $earning = ledEntries($order->id)->firstWhere('type', 'earning');
    expect($earning->amount_minor)->toBe(2500)
        ->and(ledEntries($order->id)->where('type', 'reversal')->sum('amount_minor'))->toBe(-625);
})->group('T-P8-09', 'A1')->group('ledger');

it('writes no ledger rows for a refund that is not executed', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-10 10:00:00'));
    $finance = ledFinance();
    $course = ledCourse(ledInstructor(), 10000);
    ledRule($finance, ['sale_type' => 'individual', 'rate_bps' => 2500]);
    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');
    $order = ledPaidOrder($learner, [new OrderLine(ProductType::Course, $course->id, $learner)]);

    $requested = Refund::query()->create([
        'reference' => 'REF-'.uniqid(), 'order_id' => $order->id, 'amount_minor' => $order->total_minor, 'currency' => 'EGP',
        'reason' => 'Requested refund for the ledger test', 'method' => RefundMethod::cases()[0]->value,
        'status' => RefundStatus::Requested->value, 'requested_by' => $finance->id,
    ]);

    expect(app(ReverseEarningsForRefund::class)($requested))->toBe(0)
        ->and(ledEntries($order->id)->where('type', 'reversal')->count())->toBe(0);
})->group('T-P8-09', 'A1')->group('ledger');

// ---------------------------------------------------------------- A2: subscriptions earn once per period

it('gives one fixed entry per plan course per period and none on a replay, with the matching fixed rule', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-10 10:00:00'));
    $finance = ledFinance();
    $ownerOne = ledInstructor();
    $ownerTwo = ledInstructor();
    $courseOne = ledCourse($ownerOne, 10000, 'subscription_only');
    $courseTwo = ledCourse($ownerTwo, 10000, 'subscription_only');

    ledRule($finance, ['sale_type' => 'subscription', 'fixed_amount_minor' => 1500, 'currency' => 'EGP']);
    ledRule($finance, ['sale_type' => 'subscription', 'fixed_amount_minor' => 900, 'currency' => 'SAR']);

    $plan = app(SavePlan::class)($finance, [
        'name_ar' => 'باقة', 'name_en' => 'Ledger plan', 'term' => 'monthly', 'is_active' => true,
        'prices' => [Market::Egypt->value => 5000], 'course_ids' => [$courseOne->id, $courseTwo->id],
    ]);

    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');
    $order = ledPaidOrder($learner, [new OrderLine(ProductType::Plan, $plan->id, $learner)]);

    $entries = ledEntries($order->id);
    expect($entries)->toHaveCount(2)
        ->and($entries->pluck('amount_minor')->unique()->all())->toBe([1500])
        ->and($entries->every(fn (LedgerEntry $e): bool => $e->sale_type === SaleType::Subscription && $e->student_id === $learner->id && $e->subscription_period_id !== null))->toBeTrue();

    expect(app(RecordOrderEarnings::class)($order))->toBe(0)
        ->and(ledEntries($order->id))->toHaveCount(2);
})->group('T-P8-09', 'A2')->group('ledger');

it('adds a new entry per course on a renewal period and keeps students separate', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-10 10:00:00'));
    $finance = ledFinance();
    $course = ledCourse(ledInstructor(), 10000, 'subscription_only');
    ledRule($finance, ['sale_type' => 'subscription', 'fixed_amount_minor' => 1500, 'currency' => 'EGP']);
    $plan = app(SavePlan::class)($finance, [
        'name_ar' => 'باقة', 'name_en' => 'Renewal plan', 'term' => 'monthly', 'is_active' => true,
        'prices' => [Market::Egypt->value => 5000], 'course_ids' => [$course->id],
    ]);

    $one = User::factory()->create(['status' => 'active'])->assignRole('student');
    $two = User::factory()->create(['status' => 'active'])->assignRole('student');
    $orderOne = ledPaidOrder($one, [new OrderLine(ProductType::Plan, $plan->id, $one)]);
    ledPaidOrder($two, [new OrderLine(ProductType::Plan, $plan->id, $two)]);

    expect(LedgerEntry::query()->where('student_id', $one->id)->count())->toBe(1)
        ->and(LedgerEntry::query()->where('student_id', $two->id)->count())->toBe(1);

    Carbon::setTestNow(Carbon::parse('2026-04-05 10:00:00'));
    $renewal = app(RenewSubscription::class)($one, Subscription::query()->where('learner_id', $one->id)->firstOrFail());
    app(TransitionOrder::class)($renewal, OrderStatus::Paid);
    app(GrantOrderEntitlements::class)($renewal, EntitlementSource::Purchase);
    app(RecordOrderEarnings::class)($renewal->fresh());

    expect(LedgerEntry::query()->where('student_id', $one->id)->count())->toBe(2)
        ->and(LedgerEntry::query()->where('student_id', $two->id)->count())->toBe(1)
        ->and($orderOne->id)->not->toBe($renewal->id);
})->group('T-P8-09', 'A2')->group('ledger');

it('writes a zero config default entry when a fixed rule is only in another currency', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-10 10:00:00'));
    $finance = ledFinance();
    $course = ledCourse(ledInstructor(), 10000, 'subscription_only');
    ledRule($finance, ['sale_type' => 'subscription', 'fixed_amount_minor' => 900, 'currency' => 'SAR']);
    $plan = app(SavePlan::class)($finance, [
        'name_ar' => 'باقة', 'name_en' => 'Currency plan', 'term' => 'monthly', 'is_active' => true,
        'prices' => [Market::Egypt->value => 5000], 'course_ids' => [$course->id],
    ]);
    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');
    $order = ledPaidOrder($learner, [new OrderLine(ProductType::Plan, $plan->id, $learner)]);

    $entry = ledEntries($order->id)->first();
    expect($entry->amount_minor)->toBe(0)
        ->and($entry->rule_source)->toBe('config_default');
})->group('T-P8-09', 'A2')->group('ledger');

it('writes nothing for an unpaid order and for a course with no owner', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-10 10:00:00'));
    $academy = ledCourse(null, 10000);
    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');

    $pending = app(CreateOrder::class)($learner, Market::Egypt, [new OrderLine(ProductType::Course, $academy->id, $learner)], 'led-pending-key-1');
    expect(app(RecordOrderEarnings::class)($pending))->toBe(0);

    $paid = ledPaidOrder($learner, [new OrderLine(ProductType::Course, $academy->id, $learner)]);
    expect(ledEntries($paid->id))->toHaveCount(0);
})->group('T-P8-09', 'A1')->group('ledger');

// ---------------------------------------------------------------- A3: no engagement inputs

it('keeps App\Domain\InstructorFinance free of progress, engagement, completion and AI imports', function (): void {
    $root = dirname(__DIR__, 3).'/app/Domain/InstructorFinance';
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($files as $info) {
        if (! $info->isFile() || $info->getExtension() !== 'php') {
            continue;
        }
        $source = file_get_contents($info->getPathname());
        foreach (['App\\Domain\\Progress', 'App\\Domain\\Engagement', 'App\\Domain\\Completion', 'App\\Domain\\AI'] as $forbidden) {
            expect($source)->not->toContain($forbidden);
        }
        expect($source)->not->toContain('Filament\\');
    }
})->group('T-P8-09', 'A3')->group('arch');

it('keeps the instructor finance actions final', function (): void {
    foreach ([SaveCommissionRule::class, CloseCommissionRule::class, RecordOrderEarnings::class, ReverseEarningsForRefund::class, RecordLedgerAdjustment::class] as $class) {
        expect((new ReflectionClass($class))->isFinal())->toBeTrue();
    }
})->group('T-P8-09', 'A3')->group('arch');

it('is not covered yet: changing learner progress or completion rows leaves every ledger amount unchanged')
    ->todo()
    ->group('T-P8-09', 'A3')->group('ledger');

// ---------------------------------------------------------------- rule resolution and dating

it('picks course plus instructor over course over instructor over global over the config default', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-10 10:00:00'));
    $finance = ledFinance();
    $instructor = ledInstructor();
    $course = ledCourse($instructor, 10000);
    $other = ledCourse($instructor, 10000);
    $resolve = app(CommissionRules::class);
    $at = Carbon::parse('2026-03-10 10:00:00');

    expect($resolve->resolve($instructor->id, $course->id, SaleType::Individual, 'EGP', $at)->amountFor(10000))->toBe(0);

    ledRule($finance, ['sale_type' => 'individual', 'rate_bps' => 1000]);
    expect($resolve->resolve($instructor->id, $course->id, SaleType::Individual, 'EGP', $at)->amountFor(10000))->toBe(1000);

    ledRule($finance, ['sale_type' => 'individual', 'rate_bps' => 2000, 'instructor_id' => $instructor->id]);
    expect($resolve->resolve($instructor->id, $other->id, SaleType::Individual, 'EGP', $at)->amountFor(10000))->toBe(2000);

    ledRule($finance, ['sale_type' => 'individual', 'rate_bps' => 3000, 'course_id' => $course->id]);
    expect($resolve->resolve($instructor->id, $course->id, SaleType::Individual, 'EGP', $at)->amountFor(10000))->toBe(3000);

    ledRule($finance, ['sale_type' => 'individual', 'rate_bps' => 4000, 'course_id' => $course->id, 'instructor_id' => $instructor->id]);
    expect($resolve->resolve($instructor->id, $course->id, SaleType::Individual, 'EGP', $at)->amountFor(10000))->toBe(4000);
})->group('T-P8-09', 'A1')->group('ledger');

it('applies effective dates inclusively at both ends so the last day keeps the old rule and the next day takes the new one', function (): void {
    $finance = ledFinance();
    $instructor = ledInstructor();
    ledRule($finance, ['sale_type' => 'individual', 'instructor_id' => $instructor->id, 'rate_bps' => 1000, 'effective_from' => '2026-01-01', 'effective_to' => '2026-06-30']);
    ledRule($finance, ['sale_type' => 'individual', 'instructor_id' => $instructor->id, 'rate_bps' => 2000, 'effective_from' => '2026-07-01']);

    $resolve = app(CommissionRules::class);
    expect($resolve->resolve($instructor->id, 0, SaleType::Individual, 'EGP', Carbon::parse('2026-06-30 23:59:00'))->amountFor(10000))->toBe(1000)
        ->and($resolve->resolve($instructor->id, 0, SaleType::Individual, 'EGP', Carbon::parse('2026-07-01 00:00:00'))->amountFor(10000))->toBe(2000);
})->group('T-P8-09', 'A1')->group('ledger');

// ---------------------------------------------------------------- SaveCommissionRule

it('lets finance save a rule and refuses admin, instructor, learner and inactive finance staff', function (): void {
    $data = ['sale_type' => 'individual', 'rate_bps' => 2000, 'effective_from' => '2026-01-01'];
    expect(app(SaveCommissionRule::class)(ledFinance(), $data)->exists)->toBeTrue();

    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $inactive = User::factory()->create(['status' => 'inactive'])->assignRole('finance');
    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');
    foreach ([$admin, ledInstructor(), $learner, $inactive] as $actor) {
        expect(fn () => app(SaveCommissionRule::class)($actor, $data))->toThrow(AuthorizationException::class);
    }
})->group('T-P8-09', 'A1')->group('ledger');

it('refuses invalid rule data with the ledger keys and allows adjacent dates and fixed rules in two currencies', function (): void {
    $finance = ledFinance();
    $instructor = ledInstructor();

    $refuses = function (array $data, string $field, string $key) use ($finance): void {
        try {
            app(SaveCommissionRule::class)($finance, array_merge(['sale_type' => 'individual', 'effective_from' => '2026-01-01'], $data));
            test()->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey($field)
                ->and($e->errors()[$field][0])->toBe(__('ledger.'.$key));
        }
    };

    $refuses(['sale_type' => 'nope', 'rate_bps' => 100], 'sale_type', 'sale_type_invalid');
    $refuses(['rate_bps' => 100, 'fixed_amount_minor' => 100, 'currency' => 'EGP'], 'rate_bps', 'rate_required');
    $refuses(['rate_bps' => 10001], 'rate_bps', 'rate_invalid');
    $refuses(['sale_type' => 'subscription', 'rate_bps' => 100], 'rate_bps', 'subscription_fixed_only');
    $refuses(['fixed_amount_minor' => 100, 'currency' => 'USD'], 'currency', 'currency_invalid');
    $refuses(['rate_bps' => 100, 'instructor_id' => User::factory()->create(['status' => 'active'])->assignRole('student')->id], 'instructor_id', 'instructor_invalid');
    $refuses(['rate_bps' => 100, 'effective_from' => '2026-05-01', 'effective_to' => '2026-04-01'], 'effective_to', 'dates_order');

    ledRule($finance, ['sale_type' => 'individual', 'instructor_id' => $instructor->id, 'rate_bps' => 1000, 'effective_from' => '2026-01-01', 'effective_to' => '2026-06-30']);
    $refuses(['instructor_id' => $instructor->id, 'rate_bps' => 500, 'effective_from' => '2026-06-15', 'effective_to' => '2026-07-15'], 'effective_from', 'overlap');

    // Adjacent: next day starts after the end date. Allowed.
    ledRule($finance, ['sale_type' => 'individual', 'instructor_id' => $instructor->id, 'rate_bps' => 500, 'effective_from' => '2026-07-01']);

    // Fixed EGP and fixed SAR for the same scope may coexist.
    ledRule($finance, ['sale_type' => 'subscription', 'fixed_amount_minor' => 1500, 'currency' => 'EGP']);
    ledRule($finance, ['sale_type' => 'subscription', 'fixed_amount_minor' => 900, 'currency' => 'SAR']);

    expect(CommissionRule::query()->where('sale_type', 'subscription')->count())->toBe(2);
})->group('T-P8-09', 'A1')->group('ledger');

it('refuses to change a saved rule or delete it', function (): void {
    $rule = ledRule(ledFinance(), ['sale_type' => 'individual', 'rate_bps' => 2000]);

    expect(function () use ($rule): void {
        $rule->rate_bps = 3000;
        $rule->save();
    })->toThrow(LogicException::class)
        ->and(fn () => $rule->delete())->toThrow(LogicException::class);
})->group('T-P8-09', 'A1')->group('ledger');

it('closes a rule on a date, refuses dates before its start and treats the same date as a no-op', function (): void {
    $finance = ledFinance();
    $rule = ledRule($finance, ['sale_type' => 'individual', 'rate_bps' => 2000, 'effective_from' => '2026-01-01']);

    expect(fn () => app(CloseCommissionRule::class)($finance, $rule, '2025-12-31'))->toThrow(ValidationException::class);

    $closed = app(CloseCommissionRule::class)($finance, $rule, '2026-06-30');
    expect($closed->effective_to->toDateString())->toBe('2026-06-30');
    app(CloseCommissionRule::class)($finance, $rule, '2026-06-30');
    expect(Activity::query()->where('event', 'commission_rule.closed')->count())->toBe(1);
})->group('T-P8-09', 'A1')->group('ledger');

// ---------------------------------------------------------------- ledger immutability

it('refuses to change or delete a ledger entry except its status', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-10 10:00:00'));
    $finance = ledFinance();
    $course = ledCourse(ledInstructor(), 10000);
    ledRule($finance, ['sale_type' => 'individual', 'rate_bps' => 2500]);
    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');
    $order = ledPaidOrder($learner, [new OrderLine(ProductType::Course, $course->id, $learner)]);
    $entry = ledEntries($order->id)->first();

    expect(function () use ($entry): void {
        $entry->amount_minor = 999999;
        $entry->save();
    })->toThrow(LogicException::class)
        ->and(fn () => $entry->delete())->toThrow(LogicException::class);

    $entry->fresh()->forceFill(['status' => 'paid'])->save();
    expect($entry->fresh()->status->value)->toBe('paid');
})->group('T-P8-09', 'A1')->group('ledger');

// ---------------------------------------------------------------- adjustments

it('lets finance add positive and negative adjustments with a reason, and refuses admin', function (): void {
    $finance = ledFinance();
    $instructor = ledInstructor();

    $plus = app(RecordLedgerAdjustment::class)($finance, $instructor->id, 2500, 'EGP', 'Launch bonus payment');
    $minus = app(RecordLedgerAdjustment::class)($finance, $instructor->id, -700, 'EGP', 'Correction of a duplicate', null);
    expect($plus->amount_minor)->toBe(2500)
        ->and($minus->amount_minor)->toBe(-700)
        ->and(Activity::query()->where('event', 'ledger.adjustment_recorded')->count())->toBe(2);

    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    expect(fn () => app(RecordLedgerAdjustment::class)($admin, $instructor->id, 100, 'EGP', 'Admin should not add this'))
        ->toThrow(AuthorizationException::class);

    expect(fn () => app(RecordLedgerAdjustment::class)($finance, $instructor->id, 0, 'EGP', 'Zero is not allowed here'))
        ->toThrow(ValidationException::class)
        ->and(fn () => app(RecordLedgerAdjustment::class)($finance, $instructor->id, 100, 'EGP', 'bad'))
        ->toThrow(ValidationException::class);
})->group('T-P8-09', 'A2')->group('ledger');

// ---------------------------------------------------------------- totals

it('sums earnings, reversals and adjustments per currency and ignores other instructors in the scoped totals', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-03-10 10:00:00'));
    $finance = ledFinance();
    $mine = ledInstructor();
    $theirs = ledInstructor();
    $courseMine = ledCourse($mine, 10000);
    $courseTheirs = ledCourse($theirs, 10000);
    ledRule($finance, ['sale_type' => 'individual', 'rate_bps' => 2500]);
    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');

    ledPaidOrder($learner, [new OrderLine(ProductType::Course, $courseMine->id, $learner)]);
    ledPaidOrder($learner, [new OrderLine(ProductType::Course, $courseTheirs->id, $learner)]);
    app(RecordLedgerAdjustment::class)($finance, $mine->id, 500, 'EGP', 'Mine adjustment');

    $scoped = app(LedgerTotals::class)->forInstructor($mine->id);
    expect($scoped['EGP'])->toBe(['earnings' => 2500, 'reversals' => 0, 'adjustments' => 500, 'net' => 3000]);

    $everyone = app(LedgerTotals::class)->forInstructor(null);
    expect($everyone['EGP']['earnings'])->toBe(5000)
        ->and($everyone['EGP']['net'])->toBe(5500);
})->group('T-P8-09', 'A2')->group('ledger');

it('is not covered yet: Filament rule, ledger and my-earnings pages with their actions and role access')
    ->todo()
    ->group('T-P8-09', 'A2')->group('ledger');

it('is not covered yet: the listener never fails the payment and ledger:backfill repairs a failed earning')
    ->todo()
    ->group('T-P8-09', 'A1')->group('ledger');
