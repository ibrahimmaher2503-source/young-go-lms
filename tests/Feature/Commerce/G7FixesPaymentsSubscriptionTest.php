<?php

declare(strict_types=1);

// T-G7-FIXES-E | D14 (/my/payments summary and filters, /my/subscription renewal and benefits) | E9 E10

use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Actions\TransitionOrder;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\ProductType;
use App\Domain\Commerce\Queries\PaymentHistory;
use App\Domain\Shared\Markets\Market;
use App\Domain\Subscriptions\Actions\SavePlan;
use App\Domain\Subscriptions\Subscription;
use App\Domain\Subscriptions\SubscriptionPlan;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Carbon::setTestNow('2026-11-18 09:00:00');
});

afterEach(fn () => Carbon::setTestNow());

// ---------------------------------------------------------------------------
// Helpers (prefix g7e)
// ---------------------------------------------------------------------------

function g7eLearner(string $locale = 'en'): User
{
    return User::factory()->create(['status' => 'active', 'locale' => $locale])->assignRole('student');
}

function g7eCourse(string $status = 'published', int $priceMinor = 25000, string $access = 'paid'): Course
{
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة '.uniqid(), 'en' => 'G7E Course '.uniqid()],
        'slug_ar' => 'g7e-ar-'.uniqid(), 'slug_en' => 'g7e-en-'.uniqid(),
        'status' => $status, 'access_mode' => $access,
    ]);
    CoursePrice::query()->create(['course_id' => $course->id, 'market' => Market::Egypt->value, 'currency' => 'EGP', 'amount_minor' => $priceMinor]);

    return $course;
}

/** A course order for the payer; paid through the state machine when $paid is true. */
function g7ePayOrder(User $payer, bool $paid, ?Course $course = null): Order
{
    $course ??= g7eCourse();
    $order = app(CreateOrder::class)($payer, Market::Egypt, [new OrderLine(ProductType::Course, $course->id, $payer)], 'g7e-'.uniqid());
    if ($paid) {
        app(TransitionOrder::class)($order, OrderStatus::Paid);
    }

    return $order->refresh();
}

function g7ePlan(User $staff, array $courses): SubscriptionPlan
{
    return app(SavePlan::class)($staff, [
        'name_ar' => 'خطة', 'name_en' => 'G7E Plan '.uniqid(), 'term' => 'monthly', 'is_active' => true,
        'prices' => [Market::Egypt->value => 5000], 'course_ids' => array_map(static fn (Course $c): int => $c->id, $courses),
    ]);
}

/** Pays a plan for the learner and returns the subscription. */
function g7eSubscribe(User $learner, SubscriptionPlan $plan): Subscription
{
    $order = app(CreateOrder::class)($learner, Market::Egypt, [new OrderLine(ProductType::Plan, $plan->id, $learner)], 'g7e-plan-'.uniqid());
    app(TransitionOrder::class)($order, OrderStatus::Paid);
    app(\App\Domain\Commerce\Actions\GrantOrderEntitlements::class)($order->refresh(), \App\Domain\Entitlements\EntitlementSource::Purchase);

    return Subscription::query()->where(['plan_id' => $plan->id, 'learner_id' => $learner->id])->firstOrFail();
}

// ---------------------------------------------------------------------------
// E9: /my/payments summary, filters and PaymentHistory::summary
// ---------------------------------------------------------------------------

it('E9 PaymentHistory::summary counts orders per status and sums only paid orders per currency for the payer alone', function (): void {
    $payer = g7eLearner();
    $other = g7eLearner();
    g7ePayOrder($payer, true);
    g7ePayOrder($payer, true);
    g7ePayOrder($payer, false);
    g7ePayOrder($other, true);

    $summary = PaymentHistory::summary($payer);

    expect($summary['orders'])->toBe(3)
        ->and($summary['counts'][OrderStatus::Paid->value] ?? 0)->toBe(2)
        ->and($summary['counts'][OrderStatus::Pending->value] ?? 0)->toBe(1)
        ->and($summary['paid']['EGP'] ?? null)->toBe(50000)
        ->and($summary['last_paid_at'])->not->toBeNull();
})->group('T-G7-FIXES-E', 'E9');

it('E9 an account with no orders gets an empty summary and the summary block is not rendered', function (): void {
    $payer = g7eLearner();
    $summary = PaymentHistory::summary($payer);

    expect($summary)->toMatchArray(['orders' => 0, 'paid' => [], 'last_paid_at' => null, 'counts' => []]);

    $this->actingAs($payer)->get(route('payments.history'))->assertOk()
        ->assertDontSee('data-payments-summary', false);
})->group('T-G7-FIXES-E', 'E9');

it('E9 the payments page shows the summary, one chip per existing status and lists only the filtered status', function (): void {
    $payer = g7eLearner();
    g7ePayOrder($payer, true);
    g7ePayOrder($payer, false);

    $this->actingAs($payer)->get(route('payments.history'))->assertOk()
        ->assertSee('data-payments-summary', false)
        ->assertSee('data-payments-filter', false)
        ->assertSee('data-order=', false)
        ->assertSee(__('refunds.order_status_paid', [], 'en'))
        ->assertSee(__('refunds.order_status_pending', [], 'en'));

    $html = $this->actingAs($payer)->get(route('payments.history', ['status' => 'paid']))->assertOk()
        ->assertSee('aria-current="true"', false)
        ->getContent();

    // The paid chip carries aria-current; the pending chip does not.
    expect($html)->toMatch('/href="[^"]*status=paid"[^>]*aria-current="true"/');
})->group('T-G7-FIXES-E', 'E9');

it('E9 an unknown status value is ignored and the full list is shown', function (): void {
    $payer = g7eLearner();
    g7ePayOrder($payer, true);

    $this->actingAs($payer)->get(route('payments.history', ['status' => 'not-a-status']))->assertOk()
        ->assertDontSee('data-empty-filter', false)
        ->assertSee('data-order=', false);
})->group('T-G7-FIXES-E', 'E9');

it('E9 a filter with no matching orders shows the empty-filter state', function (): void {
    $payer = g7eLearner();
    g7ePayOrder($payer, true);

    $this->actingAs($payer)->get(route('payments.history', ['status' => 'cancelled']))->assertOk()
        ->assertSee('data-empty-filter', false)
        ->assertDontSee('data-order=', false);
});

it('E9 the filter chips and pagination keep the status in the query string', function (): void {
    $payer = g7eLearner();
    for ($i = 0; $i < 11; $i++) {
        g7ePayOrder($payer, true);
    }

    $html = $this->actingAs($payer)->get(route('payments.history', ['status' => 'paid']))->assertOk()->getContent();

    expect($html)->toContain('status=paid');
})->group('T-G7-FIXES-E', 'E9');

// ---------------------------------------------------------------------------
// E10: /my/subscription renewal date and plan benefits
// ---------------------------------------------------------------------------

it('E10 an active subscription shows the next renewal date and lists only the published plan courses as catalog links', function (): void {
    $staff = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $published = g7eCourse('published', 25000, 'subscription_only');
    $draft = g7eCourse('published', 25000, 'subscription_only');
    $plan = g7ePlan($staff, [$published, $draft]);
    DB::table('courses')->where('id', $draft->id)->update(['status' => 'draft']);

    $learner = g7eLearner();
    $subscription = g7eSubscribe($learner, $plan);
    $period = $subscription->latestPeriod();

    $html = $this->actingAs($learner)->get(route('subscription.index'))->assertOk()
        ->assertSee('data-next-renewal', false)
        ->assertSee('data-plan-benefits', false)
        ->assertSee($published->title)
        ->assertDontSee($draft->title)
        ->assertSee($period->ends_at->copy()->locale('en')->isoFormat('LL'))
        ->getContent();

    expect($html)->toContain(route('catalog.show', ['course' => \App\Domain\Catalog\Queries\PublicCourseDetail::key($published)]));
})->group('T-G7-FIXES-E', 'E10');

it('E10 a cancelled subscription hides the next renewal line', function (): void {
    $staff = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $plan = g7ePlan($staff, [g7eCourse('published', 25000, 'subscription_only')]);
    $learner = g7eLearner();
    $subscription = g7eSubscribe($learner, $plan);

    $this->actingAs($learner)->post(route('subscription.cancel', $subscription))->assertRedirect(route('subscription.index'));

    $this->actingAs($learner)->get(route('subscription.index'))->assertOk()
        ->assertSee('data-state="cancelled"', false)
        ->assertDontSee('data-next-renewal', false);
})->group('T-G7-FIXES-E', 'E10');
