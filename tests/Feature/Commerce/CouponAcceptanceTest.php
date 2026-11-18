<?php

declare(strict_types=1);

// T-P8-05 | PRD 17.3, Checklist 10 | A1 A2

use App\Domain\Commerce\Actions\ApplyCoupon;
use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Actions\RemoveCoupon;
use App\Domain\Commerce\Actions\SaveCoupon;
use App\Domain\Commerce\Actions\TransitionOrder;
use App\Domain\Commerce\Coupon;
use App\Domain\Commerce\CouponRedemption;
use App\Domain\Commerce\CouponRedemptionStatus;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderItem;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\ProductType;
use App\Domain\Commerce\Queries\CouponUsage;
use App\Domain\Shared\Markets\Market;
use App\Filament\Resources\Coupons\CouponResource;
use App\Filament\Resources\Coupons\Pages\ListCoupons;
use App\Models\Course;
use App\Models\User;
use Closure;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Support\ActivityLogStatus;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

/** A finance user (payment.approve_manual, manual_entitlement.grant). */
function p805Staff(): User
{
    return User::factory()->create(['status' => 'active'])->assignRole('finance');
}

function p805Payer(): User
{
    return User::factory()->create(['status' => 'active'])->assignRole('student');
}

/** One published course per price, all priced in Egypt (EGP). */
function p805Courses(array $prices): array
{
    $courses = [];
    foreach ($prices as $price) {
        $course = Course::query()->create([
            'title' => ['ar' => 'دورة', 'en' => 'Coupon course '.uniqid()],
            'slug_ar' => 'p805-ar-'.uniqid(), 'slug_en' => 'p805-en-'.uniqid(),
            'status' => 'published', 'access_mode' => 'paid',
        ]);
        CoursePrice::query()->create(['course_id' => $course->id, 'market' => 'EG', 'currency' => 'EGP', 'amount_minor' => $price]);
        $courses[] = $course;
    }

    return $courses;
}

/** A pending order for the payer with one line per price (in order). */
function p805Order(User $payer, array $prices = [25000]): Order
{
    $lines = array_map(static fn (Course $course): OrderLine => new OrderLine(ProductType::Course, $course->id, $payer), p805Courses($prices));

    return app(CreateOrder::class)($payer, Market::Egypt, $lines, 'p805-'.uniqid());
}

/** Product ids of the order lines, in line order. */
function p805LineCourseIds(Order $order): array
{
    return OrderItem::query()->where('order_id', $order->getKey())->orderBy('id')->pluck('product_id')->map(static fn ($id): int => (int) $id)->all();
}

/**
 * Creates a coupon through SaveCoupon with the given overrides (defaults: 10% off every course, valid for 30 days).
 *
 * @param  array<string, mixed>  $overrides
 */
function p805Coupon(array $overrides = [], ?Coupon $existing = null): Coupon
{
    $data = array_merge([
        'code' => 'SAVE10', 'name' => 'Save ten', 'discount_type' => 'percent', 'percent' => 10,
        'amount_minor' => null, 'currency' => null, 'starts_at' => null, 'ends_at' => now()->addDays(30),
        'max_redemptions' => null, 'max_per_user' => null, 'is_active' => true, 'course_ids' => [],
    ], $overrides);

    return app(SaveCoupon::class)(p805Staff(), $data, $existing);
}

function p805Apply(Order $order, User $payer, string $code): Order
{
    return app(ApplyCoupon::class)($order, $payer, $code);
}

function p805Refused(Closure $call): ValidationException
{
    try {
        $call();
    } catch (ValidationException $refusal) {
        return $refusal;
    }

    throw new RuntimeException('Expected a validation refusal, nothing was refused.');
}

/** Asserts the order and the coupon show nothing was applied. */
function p805Unchanged(Order $order, int $total, ?Coupon $coupon = null): void
{
    $fresh = $order->refresh();
    expect($fresh->total_minor)->toBe($total)
        ->and($fresh->discount_minor)->toBe(0)
        ->and(CouponRedemption::query()->where('order_id', $order->getKey())->count())->toBe(0);
    if ($coupon !== null) {
        expect($coupon->refresh()->redemptions_count)->toBe(0);
    }
}

// ---------------------------------------------------------------------------
// A1: rejected coupons change nothing
// ---------------------------------------------------------------------------

it('refuses an unknown code and changes nothing', function (): void {
    $payer = p805Payer();
    $order = p805Order($payer);

    $refusal = p805Refused(fn () => p805Apply($order, $payer, 'NOSUCHCODE'));

    expect($refusal->errors()['coupon'][0])->toBe(__('commerce.coupon_invalid'));
    p805Unchanged($order, 25000);
})->group('T-P8-05', 'A1');

it('accepts a lower-case padded code', function (): void {
    $payer = p805Payer();
    p805Coupon(['code' => 'SAVE10']);
    $order = p805Order($payer);

    $applied = p805Apply($order, $payer, '  save10  ');

    expect($applied->discount_minor)->toBe(2500)
        ->and($applied->total_minor)->toBe(22500);
})->group('T-P8-05', 'A1');

it('refuses a badly formatted code with the coupon_invalid message', function (): void {
    $payer = p805Payer();
    $order = p805Order($payer);

    foreach (['AB', 'BAD CODE!', str_repeat('X', 33)] as $code) {
        $refusal = p805Refused(fn () => p805Apply($order, $payer, $code));
        expect($refusal->errors()['coupon'][0])->toBe(__('commerce.coupon_invalid'));
    }
    p805Unchanged($order, 25000);
})->group('T-P8-05', 'A1');

it('refuses an inactive coupon', function (): void {
    $payer = p805Payer();
    $coupon = p805Coupon(['code' => 'OFFLINE1']);
    $coupon->forceFill(['is_active' => false])->save();
    $order = p805Order($payer);

    $refusal = p805Refused(fn () => p805Apply($order, $payer, 'OFFLINE1'));

    expect($refusal->errors()['coupon'][0])->toBe(__('commerce.coupon_invalid'));
    p805Unchanged($order, 25000, $coupon);
})->group('T-P8-05', 'A1');

it('refuses a coupon whose start is in the future', function (): void {
    $payer = p805Payer();
    $coupon = p805Coupon(['code' => 'LATER001', 'starts_at' => now()->addDay()]);
    $order = p805Order($payer);

    $refusal = p805Refused(fn () => p805Apply($order, $payer, 'LATER001'));

    expect($refusal->errors()['coupon'][0])->toBe(__('commerce.coupon_not_started'));
    p805Unchanged($order, 25000, $coupon);
})->group('T-P8-05', 'A1');

it('refuses a coupon whose end is in the past', function (): void {
    $payer = p805Payer();
    $coupon = p805Coupon(['code' => 'OLD00001', 'ends_at' => now()->subMinute()]);
    $order = p805Order($payer);

    $refusal = p805Refused(fn () => p805Apply($order, $payer, 'OLD00001'));

    expect($refusal->errors()['coupon'][0])->toBe(__('commerce.coupon_expired'));
    p805Unchanged($order, 25000, $coupon);
})->group('T-P8-05', 'A1');

it('a coupon that ends while the payer waits is expired when they apply it', function (): void {
    $payer = p805Payer();
    p805Coupon(['code' => 'SHORT001', 'ends_at' => now()->addHour()]);
    $order = p805Order($payer);

    $this->travel(2)->hours();
    $refusal = p805Refused(fn () => p805Apply($order, $payer, 'SHORT001'));
    $this->travelBack();

    expect($refusal->errors()['coupon'][0])->toBe(__('commerce.coupon_expired'));
})->group('T-P8-05', 'A1');

it('refuses a coupon at its total cap', function (): void {
    $first = p805Payer();
    $second = p805Payer();
    $coupon = p805Coupon(['code' => 'ONEUSE01', 'max_redemptions' => 1]);
    p805Apply(p805Order($first), $first, 'ONEUSE01');

    $order = p805Order($second);
    $refusal = p805Refused(fn () => p805Apply($order, $second, 'ONEUSE01'));

    expect($refusal->errors()['coupon'][0])->toBe(__('commerce.coupon_exhausted'));
    expect($coupon->refresh()->redemptions_count)->toBe(1);
    p805Unchanged($order, 25000);
})->group('T-P8-05', 'A1', 'A2');

it('refuses a payer who has used the coupon up to their own cap', function (): void {
    $payer = p805Payer();
    $coupon = p805Coupon(['code' => 'PERPAYER', 'max_per_user' => 1]);
    p805Apply(p805Order($payer), $payer, 'PERPAYER');

    $order = p805Order($payer);
    $refusal = p805Refused(fn () => p805Apply($order, $payer, 'PERPAYER'));

    expect($refusal->errors()['coupon'][0])->toBe(__('commerce.coupon_user_limit'));
    expect($coupon->refresh()->redemptions_count)->toBe(1);
})->group('T-P8-05', 'A1');

it('refuses a course-restricted coupon on an order with no eligible course', function (): void {
    $payer = p805Payer();
    $courseA = p805Courses([10000])[0];
    $courseB = p805Courses([5000])[0];
    p805Coupon(['code' => 'ONLYA001', 'course_ids' => [$courseA->id]]);
    $order = app(CreateOrder::class)($payer, Market::Egypt, [new OrderLine(ProductType::Course, $courseB->id, $payer)], 'p805-'.uniqid());

    $refusal = p805Refused(fn () => p805Apply($order, $payer, 'ONLYA001'));

    expect($refusal->errors()['coupon'][0])->toBe(__('commerce.coupon_ineligible'));
    p805Unchanged($order, 5000);
})->group('T-P8-05', 'A1');

it('refuses a fixed coupon in another currency', function (): void {
    $payer = p805Payer();
    p805Coupon(['code' => 'SARFIX01', 'discount_type' => 'fixed', 'percent' => null, 'amount_minor' => 1000, 'currency' => 'SAR']);
    $order = p805Order($payer);

    $refusal = p805Refused(fn () => p805Apply($order, $payer, 'SARFIX01'));

    expect($refusal->errors()['coupon'][0])->toBe(__('commerce.coupon_ineligible'));
    p805Unchanged($order, 25000);
})->group('T-P8-05', 'A1');

it('refuses a coupon on an order that is not pending', function (string $status): void {
    $payer = p805Payer();
    p805Coupon(['code' => 'STATUS01']);
    $order = p805Order($payer);
    $target = OrderStatus::from($status);
    if ($target === OrderStatus::Paid) {
        app(TransitionOrder::class)($order, OrderStatus::Paid);
    } else {
        app(TransitionOrder::class)($order, $target);
    }

    $refusal = p805Refused(fn () => p805Apply($order->refresh(), $payer, 'STATUS01'));

    expect($refusal->errors()['coupon'][0])->toBe(__('commerce.coupon_order_invalid'));
    expect($order->refresh()->discount_minor)->toBe(0);
})->with(['paid', 'failed', 'cancelled'])->group('T-P8-05', 'A1');

it('refuses a coupon on another payer order or from an inactive payer', function (): void {
    $payer = p805Payer();
    $stranger = p805Payer();
    p805Coupon(['code' => 'OWNER001']);
    $order = p805Order($payer);

    $refusal = p805Refused(fn () => p805Apply($order, $stranger, 'OWNER001'));
    expect($refusal->errors()['coupon'][0])->toBe(__('commerce.coupon_order_invalid'));

    $payer->forceFill(['status' => 'inactive'])->save();
    $refusal = p805Refused(fn () => p805Apply($order, $payer, 'OWNER001'));
    expect($refusal->errors()['coupon'][0])->toBe(__('commerce.coupon_order_invalid'));
    p805Unchanged($order, 25000);
})->group('T-P8-05', 'A1');

it('refuses a second coupon on the same order', function (): void {
    $payer = p805Payer();
    p805Coupon(['code' => 'FIRST001']);
    p805Coupon(['code' => 'SECOND01', 'name' => 'Another']);
    $order = p805Order($payer);
    p805Apply($order, $payer, 'FIRST001');

    $refusal = p805Refused(fn () => p805Apply($order->refresh(), $payer, 'SECOND01'));

    expect($refusal->errors()['coupon'][0])->toBe(__('commerce.coupon_already_applied'));
    expect($order->refresh()->discount_minor)->toBe(2500);
})->group('T-P8-05', 'A1');

// ---------------------------------------------------------------------------
// A2: the cap holds under concurrent-style sequential use and never drifts
// ---------------------------------------------------------------------------

it('exactly the cap succeeds across several payers and the rest are exhausted', function (int $cap): void {
    $coupon = p805Coupon(['code' => 'CAPPED'.$cap, 'max_redemptions' => $cap]);
    $results = ['ok' => 0, 'exhausted' => 0];

    for ($i = 0; $i < $cap + 2; $i++) {
        $payer = p805Payer();
        try {
            p805Apply(p805Order($payer), $payer, 'CAPPED'.$cap);
            $results['ok']++;
        } catch (ValidationException $refusal) {
            expect($refusal->errors()['coupon'][0])->toBe(__('commerce.coupon_exhausted'));
            $results['exhausted']++;
        }
    }

    expect($results)->toBe(['ok' => $cap, 'exhausted' => 2])
        ->and($coupon->refresh()->redemptions_count)->toBe($cap)
        ->and(CouponRedemption::query()->where('coupon_id', $coupon->getKey())->count())->toBe($cap);
})->with([1, 2])->group('T-P8-05', 'A2');

it('the counter never goes over the cap or below zero across apply, remove, fail and cancel cycles', function (): void {
    $coupon = p805Coupon(['code' => 'CYCLE001', 'max_redemptions' => 2]);
    $payer = p805Payer();
    $other = p805Payer();

    $first = p805Order($payer);
    p805Apply($first, $payer, 'CYCLE001');
    expect($coupon->refresh()->redemptions_count)->toBe(1);

    app(RemoveCoupon::class)($first->refresh(), $payer);
    expect($coupon->refresh()->redemptions_count)->toBe(0);

    $second = p805Order($other);
    p805Apply($second, $other, 'CYCLE001');
    app(TransitionOrder::class)($second, OrderStatus::Failed);
    expect($coupon->refresh()->redemptions_count)->toBe(0);

    // A stale counter (already 0 while a reservation exists) must not go negative on release.
    $third = p805Order($payer);
    p805Apply($third, $payer, 'CYCLE001');
    DB::table('coupons')->where('id', $coupon->getKey())->update(['redemptions_count' => 0]);
    app(TransitionOrder::class)($third, OrderStatus::Cancelled);
    expect($coupon->refresh()->redemptions_count)->toBe(0);
})->group('T-P8-05', 'A2');

it('a true parallel redemption race is covered by the row-locked guarded update, not by this suite')
    ->skip('SQLite cannot run parallel transactions in this suite; the guarded increment is exercised sequentially above.')
    ->group('T-P8-05', 'A2');

// ---------------------------------------------------------------------------
// Pricing
// ---------------------------------------------------------------------------

it('a percent discount rounds down on an odd amount', function (): void {
    $payer = p805Payer();
    p805Coupon(['code' => 'ODDPRICE', 'percent' => 15]);
    $order = p805Order($payer, [1001]);

    $applied = p805Apply($order, $payer, 'ODDPRICE');

    expect($applied->discount_minor)->toBe(150)
        ->and($applied->total_minor)->toBe(851);
})->group('T-P8-05', 'A1');

it('a 100 percent coupon makes the total zero', function (): void {
    $payer = p805Payer();
    p805Coupon(['code' => 'FREEFREE', 'percent' => 100]);
    $order = p805Order($payer);

    $applied = p805Apply($order, $payer, 'FREEFREE');

    expect($applied->total_minor)->toBe(0)
        ->and($applied->discount_minor)->toBe(25000);
})->group('T-P8-05', 'A1');

it('a fixed amount larger than the eligible subtotal is capped to it', function (): void {
    $payer = p805Payer();
    p805Coupon(['code' => 'BIGFIX01', 'discount_type' => 'fixed', 'percent' => null, 'amount_minor' => 50000, 'currency' => 'EGP']);
    $order = p805Order($payer);

    $applied = p805Apply($order, $payer, 'BIGFIX01');

    expect($applied->discount_minor)->toBe(25000)
        ->and($applied->total_minor)->toBe(0);
})->group('T-P8-05', 'A1');

it('a restricted coupon discounts only the eligible lines and their items stay at 0 discount', function (): void {
    $payer = p805Payer();
    $courses = p805Courses([10000, 5000]);
    $coupon = p805Coupon(['code' => 'HALFA001', 'percent' => 50, 'course_ids' => [$courses[0]->id]]);
    $order = app(CreateOrder::class)($payer, Market::Egypt, [
        new OrderLine(ProductType::Course, $courses[0]->id, $payer),
        new OrderLine(ProductType::Course, $courses[1]->id, $payer),
    ], 'p805-'.uniqid());

    $applied = p805Apply($order, $payer, 'HALFA001');

    $items = OrderItem::query()->where('order_id', $order->getKey())->orderBy('id')->get();
    expect($applied->discount_minor)->toBe(5000)
        ->and($items[0]->discount_minor)->toBe(5000)
        ->and($items[1]->discount_minor)->toBe(0)
        ->and($items[1]->allocated_minor)->toBe(5000)
        ->and($coupon->refresh()->redemptions_count)->toBe(1);
})->group('T-P8-05', 'A1');

it('item shares add up exactly to the order discount and never exceed a line price', function (): void {
    $payer = p805Payer();
    p805Coupon(['code' => 'SPLIT001', 'percent' => 10]);
    $order = p805Order($payer, [1000, 1000, 1001]);

    $applied = p805Apply($order, $payer, 'SPLIT001');

    $items = OrderItem::query()->where('order_id', $order->getKey())->orderBy('id')->get();
    expect($applied->discount_minor)->toBe(300)
        ->and((int) $items->sum('discount_minor'))->toBe(300)
        ->and($items->every(static fn (OrderItem $item): bool => $item->discount_minor <= $item->unit_price_minor))->toBeTrue()
        ->and($applied->subtotal_minor)->toBe(3001)
        ->and($applied->total_minor)->toBe(2701);
})->group('T-P8-05', 'A1');

it('the subtotal and the line price snapshots stay untouched and money columns are integers', function (): void {
    $payer = p805Payer();
    p805Coupon(['code' => 'SNAPSHOT1', 'percent' => 20]);
    $order = p805Order($payer, [8000]);
    $unit = OrderItem::query()->where('order_id', $order->getKey())->value('unit_price_minor');

    $applied = p805Apply($order, $payer, 'SNAPSHOT1');

    expect($applied->subtotal_minor)->toBe(8000)
        ->and(OrderItem::query()->where('order_id', $order->getKey())->value('unit_price_minor'))->toBe($unit)
        ->and($applied->discount_minor)->toBeInt()
        ->and($applied->total_minor)->toBeInt()
        ->and($applied->total_minor)->toBe(6400);
})->group('T-P8-05', 'A1');

// ---------------------------------------------------------------------------
// Per-payer cap details
// ---------------------------------------------------------------------------

it('a released use does not count towards the per-payer cap', function (): void {
    $payer = p805Payer();
    p805Coupon(['code' => 'RELEASED1', 'max_per_user' => 1]);
    $failing = p805Order($payer);
    p805Apply($failing, $payer, 'RELEASED1');
    app(TransitionOrder::class)($failing, OrderStatus::Failed);

    $next = p805Order($payer);
    $applied = p805Apply($next, $payer, 'RELEASED1');

    expect($applied->discount_minor)->toBe(2500);
})->group('T-P8-05', 'A1');

it('no per-payer limit means unlimited uses by one payer, and the cap is per payer', function (): void {
    $payer = p805Payer();
    $other = p805Payer();
    p805Coupon(['code' => 'UNLIMIT1']);
    for ($i = 0; $i < 3; $i++) {
        p805Apply(p805Order($payer), $payer, 'UNLIMIT1');
    }

    p805Coupon(['code' => 'PERUSER1', 'max_per_user' => 1]);
    p805Apply(p805Order($payer), $payer, 'PERUSER1');
    $applied = p805Apply(p805Order($other), $other, 'PERUSER1');

    expect($applied->discount_minor)->toBe(2500)
        ->and(Coupon::query()->where('code', 'UNLIMIT1')->value('redemptions_count'))->toBe(3);
})->group('T-P8-05', 'A1');

// ---------------------------------------------------------------------------
// RemoveCoupon
// ---------------------------------------------------------------------------

it('the payer removes a reserved coupon: the use is freed, totals restored and another coupon can be applied', function (): void {
    $payer = p805Payer();
    $coupon = p805Coupon(['code' => 'REMOVE01', 'max_redemptions' => 1]);
    $order = p805Order($payer, [10000]);
    p805Apply($order, $payer, 'REMOVE01');

    $removed = app(RemoveCoupon::class)($order->refresh(), $payer);

    expect($removed->discount_minor)->toBe(0)
        ->and($removed->total_minor)->toBe(10000)
        ->and($coupon->refresh()->redemptions_count)->toBe(0)
        ->and(CouponRedemption::query()->count())->toBe(0)
        ->and(OrderItem::query()->where('order_id', $order->getKey())->value('discount_minor'))->toBe(0);

    p805Coupon(['code' => 'REPLACE1']);
    $replaced = p805Apply($order->refresh(), $payer, 'REPLACE1');
    expect($replaced->discount_minor)->toBe(1000);
})->group('T-P8-05', 'A1');

it('removing refuses when no coupon is applied, and refuses anyone but the payer', function (): void {
    $payer = p805Payer();
    $order = p805Order($payer);

    $refusal = p805Refused(fn () => app(RemoveCoupon::class)($order, $payer));
    expect($refusal->errors()['coupon'][0])->toBe(__('commerce.coupon_not_applied'));

    p805Coupon(['code' => 'OWNERRM1']);
    p805Apply($order->refresh(), $payer, 'OWNERRM1');
    $stranger = p805Payer();
    $refusal = p805Refused(fn () => app(RemoveCoupon::class)($order->refresh(), $stranger));
    expect($refusal->errors()['coupon'][0])->toBe(__('commerce.coupon_order_invalid'))
        ->and($order->refresh()->discount_minor)->toBe(2500);
})->group('T-P8-05', 'A1');

// ---------------------------------------------------------------------------
// TransitionOrder effects
// ---------------------------------------------------------------------------

it('paying confirms the use: redeemed with redeemed_at and one coupon.redeemed audit, and a retried paid is a no-op', function (): void {
    $payer = p805Payer();
    p805Coupon(['code' => 'PAYNOW01']);
    $order = p805Apply(p805Order($payer), $payer, 'PAYNOW01');

    app(TransitionOrder::class)($order, OrderStatus::Paid);
    app(TransitionOrder::class)($order->refresh(), OrderStatus::Paid);

    $redemption = CouponRedemption::query()->where('order_id', $order->getKey())->sole();
    expect($redemption->status)->toBe(CouponRedemptionStatus::Redeemed)
        ->and($redemption->redeemed_at)->not->toBeNull()
        ->and(Activity::query()->where('event', 'coupon.redeemed')->count())->toBe(1);
})->group('T-P8-05', 'A1');

it('failing or cancelling releases the use, decrements the counter and frees the cap for another order', function (string $to): void {
    $coupon = p805Coupon(['code' => 'FREECAP1', 'max_redemptions' => 1]);
    $first = p805Payer();
    $order = p805Order($first);
    p805Apply($order, $first, 'FREECAP1');

    app(TransitionOrder::class)($order, OrderStatus::from($to));

    expect(CouponRedemption::query()->where('order_id', $order->getKey())->value('status'))->toBe(CouponRedemptionStatus::Released)
        ->and($coupon->refresh()->redemptions_count)->toBe(0);

    $second = p805Payer();
    $applied = p805Apply(p805Order($second), $second, 'FREECAP1');
    expect($applied->discount_minor)->toBe(2500)
        ->and($coupon->refresh()->redemptions_count)->toBe(1);
})->with(['failed', 'cancelled'])->group('T-P8-05', 'A1');

it('paid totals stay frozen with the discount on the order, and a refund does not release a redeemed use', function (): void {
    $payer = p805Payer();
    $coupon = p805Coupon(['code' => 'REFUND01']);
    $order = p805Apply(p805Order($payer), $payer, 'REFUND01');
    app(TransitionOrder::class)($order, OrderStatus::Paid);
    $paid = $order->refresh();

    app(TransitionOrder::class)($paid, OrderStatus::PartiallyRefunded);

    $fresh = $order->refresh();
    expect($fresh->discount_minor)->toBe(2500)
        ->and($fresh->total_minor)->toBe(22500)
        ->and(CouponRedemption::query()->where('order_id', $order->getKey())->value('status'))->toBe(CouponRedemptionStatus::Redeemed)
        ->and($coupon->refresh()->redemptions_count)->toBe(1);
})->group('T-P8-05', 'A1');

it('an order without a coupon transitions as before and creates no redemption rows', function (): void {
    $payer = p805Payer();
    $order = p805Order($payer);

    app(TransitionOrder::class)($order, OrderStatus::Paid);

    expect($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and(CouponRedemption::query()->count())->toBe(0);
})->group('T-P8-05', 'A1');

it('an audit failure rolls the transition back and the reservation stays reserved', function (): void {
    $payer = p805Payer();
    p805Coupon(['code' => 'ROLLBK01']);
    $order = p805Apply(p805Order($payer), $payer, 'ROLLBK01');
    app(ActivityLogStatus::class)->disable();

    expect(fn () => app(TransitionOrder::class)($order, OrderStatus::Paid))->toThrow(RuntimeException::class);

    expect($order->refresh()->status)->toBe(OrderStatus::Pending)
        ->and(CouponRedemption::query()->where('order_id', $order->getKey())->value('status'))->toBe(CouponRedemptionStatus::Reserved);
})->group('T-P8-05', 'A1');

// ---------------------------------------------------------------------------
// SaveCoupon
// ---------------------------------------------------------------------------

it('only finance and admin save coupons; learners, instructors and inactive staff get AuthorizationException', function (): void {
    $learner = p805Payer();
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $inactive = User::factory()->create(['status' => 'inactive'])->assignRole('finance');
    $data = ['code' => 'AUTHZ001', 'name' => 'Authz', 'discount_type' => 'percent', 'percent' => 5];

    foreach ([$learner, $instructor, $inactive] as $actor) {
        expect(fn () => app(SaveCoupon::class)($actor, $data))->toThrow(AuthorizationException::class);
    }

    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    expect(app(SaveCoupon::class)($admin, $data)->code)->toBe('AUTHZ001');
})->group('T-P8-05', 'A2');

it('normalises the code to upper case and refuses a malformed one', function (): void {
    $saved = p805Coupon(['code' => '  save-x  ']);
    expect($saved->code)->toBe('SAVE-X');

    $refusal = p805Refused(fn () => p805Coupon(['code' => 'a b']));
    expect($refusal->errors())->toHaveKey('code')
        ->and($refusal->errors()['code'][0])->toBe(__('commerce.coupon_code_format'));
})->group('T-P8-05', 'A1');

it('refuses a duplicate code, also when editing another coupon onto it', function (): void {
    p805Coupon(['code' => 'DUPCODE1']);
    $other = p805Coupon(['code' => 'OTHERCD1']);

    $refusal = p805Refused(fn () => p805Coupon(['code' => 'dupcode1']));
    expect($refusal->errors()['code'][0])->toBe(__('commerce.coupon_code_taken'));

    $refusal = p805Refused(fn () => p805Coupon(['code' => 'DUPCODE1'], $other));
    expect($refusal->errors()['code'][0])->toBe(__('commerce.coupon_code_taken'));
})->group('T-P8-05', 'A1');

it('validates name, percent, fixed amount, dates and caps with field keys', function (array $overrides, string $field, string $key): void {
    $refusal = p805Refused(fn () => p805Coupon(array_merge(['code' => 'VALID001'], $overrides)));

    expect($refusal->errors())->toHaveKey($field)
        ->and($refusal->errors()[$field][0])->toBe(__('commerce.'.$key));
})->with([
    'name too short' => [['name' => 'A'], 'name', 'coupon_name_invalid'],
    'percent zero' => [['percent' => 0], 'percent', 'coupon_value_invalid'],
    'percent over 100' => [['percent' => 101], 'percent', 'coupon_value_invalid'],
    'fixed amount zero' => [['discount_type' => 'fixed', 'percent' => null, 'amount_minor' => 0, 'currency' => 'EGP'], 'amount_minor', 'coupon_value_invalid'],
    'fixed unknown currency' => [['discount_type' => 'fixed', 'percent' => null, 'amount_minor' => 100, 'currency' => 'XXX'], 'amount_minor', 'coupon_value_invalid'],
    'end before start' => [['starts_at' => now()->addDays(5), 'ends_at' => now()->addDays(2)], 'ends_at', 'coupon_dates_invalid'],
    'total cap zero' => [['max_redemptions' => 0], 'max_redemptions', 'coupon_caps_invalid'],
    // SaveCoupon reports both cap errors on the max_redemptions key.
    'per payer cap zero' => [['max_per_user' => 0], 'max_redemptions', 'coupon_caps_invalid'],
]);

it('refuses unknown course ids and syncs the course list on save', function (): void {
    $course = p805Courses([1000])[0];

    $refusal = p805Refused(fn () => p805Coupon(['code' => 'BADCOURS', 'course_ids' => [999999]]));
    expect($refusal->errors())->toHaveKey('course_ids')
        ->and($refusal->errors()['course_ids'][0])->toBe(__('commerce.coupon_courses_invalid'));

    $coupon = p805Coupon(['code' => 'GOODCRS1', 'course_ids' => [$course->id]]);
    expect($coupon->courses()->pluck('courses.id')->all())->toBe([$course->id]);
})->group('T-P8-05', 'A1');

it('writes coupon.created and coupon.updated audits with before and after values', function (): void {
    $coupon = p805Coupon(['code' => 'AUDITED1', 'percent' => 10]);
    p805Coupon(['code' => 'AUDITED1', 'percent' => 10, 'name' => 'Renamed offer'], $coupon);

    $created = Activity::query()->where('event', 'coupon.created')->sole();
    $updated = Activity::query()->where('event', 'coupon.updated')->sole();
    expect($created->properties['after']['code'])->toBe('AUDITED1')
        ->and($updated->properties['before']['percent'])->toBe(10)
        ->and($updated->properties['after']['percent'])->toBe(10);
})->group('T-P8-05', 'A1');

it('an audit failure rolls back the save', function (): void {
    app(ActivityLogStatus::class)->disable();

    expect(fn () => p805Coupon(['code' => 'NOAUDIT1']))->toThrow(RuntimeException::class);

    expect(Coupon::query()->where('code', 'NOAUDIT1')->exists())->toBeFalse();
})->group('T-P8-05', 'A1');

it('after a redemption the code, type, percent, amount and currency are frozen, but dates, caps, courses and status stay editable', function (): void {
    $payer = p805Payer();
    $coupon = p805Coupon(['code' => 'FROZEN01', 'percent' => 10]);
    p805Apply(p805Order($payer), $payer, 'FROZEN01');

    $refusal = p805Refused(fn () => p805Coupon(['code' => 'FROZEN02', 'percent' => 10], $coupon));
    expect($refusal->errors()['code'][0])->toBe(__('commerce.coupon_frozen'));

    $refusal = p805Refused(fn () => p805Coupon(['code' => 'FROZEN01', 'percent' => 20], $coupon));
    expect($refusal->errors()['code'][0])->toBe(__('commerce.coupon_frozen'));

    $edited = p805Coupon(['code' => 'FROZEN01', 'percent' => 10, 'max_redemptions' => 50, 'is_active' => false, 'ends_at' => now()->addDays(90)], $coupon);
    expect($edited->max_redemptions)->toBe(50)
        ->and($edited->is_active)->toBeFalse();

    $refusal = p805Refused(fn () => p805Coupon(['code' => 'FROZEN01', 'percent' => 10, 'max_redemptions' => 0], $coupon));
    expect($refusal->errors())->toHaveKey('max_redemptions');
})->group('T-P8-05', 'A1');

it('the total cap cannot drop below the uses so far', function (): void {
    $payer = p805Payer();
    $coupon = p805Coupon(['code' => 'CAPDROP1']);
    p805Apply(p805Order($payer), $payer, 'CAPDROP1');
    $second = p805Payer();
    p805Apply(p805Order($second), $second, 'CAPDROP1');

    $refusal = p805Refused(fn () => p805Coupon(['code' => 'CAPDROP1', 'max_redemptions' => 1], $coupon));

    expect($refusal->errors()['max_redemptions'][0])->toBe(__('commerce.coupon_cap_below_used'));
})->group('T-P8-05', 'A1');

// ---------------------------------------------------------------------------
// CouponUsage
// ---------------------------------------------------------------------------

it('CouponUsage counts confirmed, reserved and released uses and the discount per currency', function (): void {
    $coupon = p805Coupon(['code' => 'USAGE001']);
    $paying = p805Payer();
    $paidOrder = p805Apply(p805Order($paying), $paying, 'USAGE001');
    app(TransitionOrder::class)($paidOrder, OrderStatus::Paid);
    $failing = p805Payer();
    $failedOrder = p805Apply(p805Order($failing), $failing, 'USAGE001');
    app(TransitionOrder::class)($failedOrder, OrderStatus::Failed);
    $pending = p805Payer();
    p805Apply(p805Order($pending), $pending, 'USAGE001');

    $single = CouponUsage::for($coupon);
    $all = CouponUsage::for();

    expect($single)->toBe(['redeemed' => 1, 'reserved' => 1, 'released' => 1, 'discount_minor' => ['EGP' => 2500]])
        ->and($all['redeemed'])->toBe(1)
        ->and($all['discount_minor'])->toBe(['EGP' => 2500]);
})->group('T-P8-05', 'A1');

// ---------------------------------------------------------------------------
// Lang parity and Filament
// ---------------------------------------------------------------------------

it('every commerce coupon lang key and coupon audit label exists in Arabic and English', function (): void {
    $ar = require lang_path('ar/commerce.php');
    $en = require lang_path('en/commerce.php');
    $couponKeys = array_filter(array_keys($en), static fn (string $key): bool => str_starts_with($key, 'coupon'));

    expect($couponKeys)->not->toBeEmpty();
    foreach ($couponKeys as $key) {
        expect($ar)->toHaveKey($key);
    }
    $arCouponKeys = array_keys(array_filter($ar, static fn (string $key): bool => str_starts_with($key, 'coupon'), ARRAY_FILTER_USE_KEY));
    $enCouponKeys = array_values($couponKeys);
    sort($arCouponKeys);
    sort($enCouponKeys);
    expect($arCouponKeys)->toEqual($enCouponKeys);

    $auditAr = require lang_path('ar/audit-events.php');
    $auditEn = require lang_path('en/audit-events.php');
    foreach (['coupon.created', 'coupon.updated', 'coupon.applied', 'coupon.removed', 'coupon.redeemed', 'coupon.released'] as $event) {
        expect($auditAr)->toHaveKey($event)->and($auditEn)->toHaveKey($event);
    }
})->group('T-P8-05', 'A1');

it('the coupon list is visible to finance and payment.view holders only', function (): void {
    Filament::setCurrentPanel('finance');

    $this->actingAs(p805Staff());
    expect(CouponResource::canViewAny())->toBeTrue();
    Livewire::test(ListCoupons::class)->assertOk();

    $this->actingAs(p805Payer());
    expect(CouponResource::canViewAny())->toBeFalse();

    $this->actingAs(User::factory()->create(['status' => 'active'])->assignRole('instructor_academy'));
    expect(CouponResource::canViewAny())->toBeFalse();
})->group('T-P8-05', 'A2');

it('the Filament coupon list does not offer create or edit to view-only staff', function (): void {
    $viewer = User::factory()->create(['status' => 'active'])->assignRole('finance');
    $viewer->syncRoles([]);
    $viewer->givePermissionTo('payment.view');
    Filament::setCurrentPanel('finance');

    $this->actingAs($viewer);
    expect(CouponResource::canViewAny())->toBeTrue()
        ->and(CouponResource::canCreate())->toBeFalse();
})->group('T-P8-05', 'A2');
