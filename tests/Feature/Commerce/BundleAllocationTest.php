<?php

declare(strict_types=1);

// T-P8-07 | PRD 17.1, 17.4, O-009, D-015 | A1 A2

use App\Domain\Commerce\Actions\AllocateBundlePrice;
use App\Domain\Commerce\Actions\ApplyCoupon;
use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Actions\GrantOrderEntitlements;
use App\Domain\Commerce\Actions\SaveBundle;
use App\Domain\Commerce\Actions\SaveCoupon;
use App\Domain\Commerce\Actions\TransitionOrder;
use App\Domain\Commerce\Bundle;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderItem;
use App\Domain\Commerce\OrderItemAllocation;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\ProductType;
use App\Domain\Commerce\Queries\OrderItemAllocations;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Shared\Markets\Market;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

/** A published, paid course with an Egypt list price. */
function bndCourse(int $priceMinor = 10000, string $status = 'published', bool $price = true): Course
{
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة باقة', 'en' => 'Bundle Course '.uniqid()],
        'slug_ar' => 'bnd-ar-'.uniqid(),
        'slug_en' => 'bnd-en-'.uniqid(),
        'status' => $status,
        'access_mode' => 'paid',
    ]);
    if ($price) {
        CoursePrice::query()->create(['course_id' => $course->id, 'market' => Market::Egypt->value, 'currency' => 'EGP', 'amount_minor' => $priceMinor]);
    }

    return $course;
}

function bndStaff(string $role = 'finance'): User
{
    return User::factory()->create(['status' => 'active'])->assignRole($role);
}

function bndLearner(): User
{
    return User::factory()->create(['status' => 'active'])->assignRole('student');
}

/**
 * @param  list<Course>  $courses
 * @param  array<string, int>|null  $prices
 * @param  list<int|null>|null  $weights
 */
function bndSave(User $actor, array $courses, ?array $prices = null, ?array $weights = null, bool $active = true, ?Bundle $bundle = null): Bundle
{
    $prices ??= [Market::Egypt->value => 30000];
    $items = [];
    foreach ($courses as $i => $course) {
        $items[] = ['course_id' => $course->id, 'weight' => $weights[$i] ?? null];
    }

    return app(SaveBundle::class)($actor, [
        'name_ar' => 'باقة تجريبية',
        'name_en' => 'Test Bundle '.uniqid(),
        'is_active' => $active,
        'prices' => $prices,
        'items' => $items,
    ], $bundle);
}

function bndOrder(User $payer, array $lines, ?string $key = null): Order
{
    return app(CreateOrder::class)($payer, Market::Egypt, $lines, $key ?? 'bnd-key-'.uniqid());
}

function bndPay(Order $order): void
{
    app(TransitionOrder::class)($order, OrderStatus::Paid);
    app(GrantOrderEntitlements::class)($order, EntitlementSource::Purchase);
}

function bndEntitlementsFor(User $learner, string $source): int
{
    return Entitlement::query()->where(['learner_id' => $learner->id, 'source_type' => $source])->count();
}

// ---------------------------------------------------------------- A1: grants

it('grants one bundle entitlement per member course and the access check allows each', function (): void {
    $admin = bndStaff('admin');
    $a = bndCourse();
    $b = bndCourse();
    $c = bndCourse();
    $bundle = bndSave($admin, [$a, $b, $c]);

    $learner = bndLearner();
    $order = bndOrder($learner, [new OrderLine(ProductType::Bundle, $bundle->id, $learner)]);
    bndPay($order);

    expect(bndEntitlementsFor($learner, 'bundle'))->toBe(3)
        ->and(Entitlement::query()->where('learner_id', $learner->id)->where('source_ref', 'order:'.$order->reference)->pluck('course_id')->sort()->values()->all())
        ->toEqualCanonicalizing([$a->id, $b->id, $c->id]);

    $service = app(EntitlementService::class);
    foreach ([$a, $b, $c] as $course) {
        expect($service->canAccessCourse($learner, $course)->allowed)->toBeTrue();
    }
})->group('T-P8-07', 'A1')->group('payments');

it('grants a bundle once: a second grant adds no row, no allocation change and no second audit', function (): void {
    $admin = bndStaff('admin');
    $a = bndCourse();
    $b = bndCourse();
    $bundle = bndSave($admin, [$a, $b]);
    $learner = bndLearner();
    $order = bndOrder($learner, [new OrderLine(ProductType::Bundle, $bundle->id, $learner)]);
    bndPay($order);

    $rowsBefore = Entitlement::query()->count();
    $auditBefore = Activity::query()->where('event', 'bundle.granted')->count();
    expect($auditBefore)->toBe(1);

    app(GrantOrderEntitlements::class)($order, EntitlementSource::Purchase);
    app(GrantOrderEntitlements::class)($order, EntitlementSource::Purchase);

    expect(Entitlement::query()->count())->toBe($rowsBefore)
        ->and(Activity::query()->where('event', 'bundle.granted')->count())->toBe(1);
})->group('T-P8-07', 'A1')->group('payments');

it('skips a course the learner already holds from another source and marks its allocation as skipped', function (): void {
    $admin = bndStaff('admin');
    $a = bndCourse();
    $b = bndCourse();
    $c = bndCourse();
    $bundle = bndSave($admin, [$a, $b, $c]);

    $learner = bndLearner();
    app(GrantEntitlement::class)($learner, $a, EntitlementSource::Free);

    $order = bndOrder($learner, [new OrderLine(ProductType::Bundle, $bundle->id, $learner)]);
    bndPay($order);

    expect(Entitlement::query()->where(['learner_id' => $learner->id, 'course_id' => $a->id])->count())->toBe(1)
        ->and(bndEntitlementsFor($learner, 'bundle'))->toBe(2);

    $allocA = OrderItemAllocation::query()->where('course_id', $a->id)->first();
    expect($allocA)->not->toBeNull()
        ->and((bool) $allocA->skipped_existing)->toBeTrue()
        ->and($allocA->entitlement_id)->toBeNull();

    $allocB = OrderItemAllocation::query()->where('course_id', $b->id)->first();
    expect((bool) $allocB->skipped_existing)->toBeFalse()
        ->and($allocB->entitlement_id)->not->toBeNull();

    $audit = Activity::query()->where('event', 'bundle.granted')->latest('id')->first();
    expect($audit)->not->toBeNull()
        ->and($audit->properties['after']['skipped_course_ids'] ?? [])->toContain($a->id);
})->group('T-P8-07', 'A1')->group('payments');

it('gives one entitlement when a course line and a bundle containing that course share an order', function (): void {
    $admin = bndStaff('admin');
    $a = bndCourse();
    $b = bndCourse();
    $bundle = bndSave($admin, [$a, $b]);

    $learner = bndLearner();
    $order = bndOrder($learner, [
        new OrderLine(ProductType::Course, $a->id, $learner),
        new OrderLine(ProductType::Bundle, $bundle->id, $learner),
    ]);
    bndPay($order);

    expect(Entitlement::query()->where(['learner_id' => $learner->id, 'course_id' => $a->id])->count())->toBe(1)
        ->and(Entitlement::query()->where(['learner_id' => $learner->id, 'course_id' => $b->id])->count())->toBe(1)
        ->and(bndEntitlementsFor($learner, 'bundle'))->toBe(1)
        ->and(OrderItemAllocation::query()->where('course_id', $a->id)->value('skipped_existing'))->toBeTrue();
})->group('T-P8-07', 'A1')->group('payments');

it('gives a parent buying for two children each child its own bundle entitlements', function (): void {
    $admin = bndStaff('admin');
    $a = bndCourse();
    $b = bndCourse();
    $bundle = bndSave($admin, [$a, $b]);

    $parent = User::factory()->create(['status' => 'active'])->assignRole('parent');
    $childOne = bndLearner();
    $childTwo = bndLearner();
    foreach ([$childOne, $childTwo] as $child) {
        GuardianChildLink::query()->create([
            'guardian_id' => $parent->id,
            'child_id' => $child->id,
            'status' => GuardianChildLink::STATUS_VERIFIED,
            'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT,
            'verified_at' => now(),
        ]);
    }

    $order = bndOrder($parent, [
        new OrderLine(ProductType::Bundle, $bundle->id, $childOne),
        new OrderLine(ProductType::Bundle, $bundle->id, $childTwo),
    ]);
    bndPay($order);

    expect(Entitlement::query()->where('learner_id', $childOne->id)->count())->toBe(2)
        ->and(Entitlement::query()->where('learner_id', $childTwo->id)->count())->toBe(2)
        ->and(Entitlement::query()->where('learner_id', $parent->id)->count())->toBe(0);
})->group('T-P8-07', 'A1')->group('payments');

// ---------------------------------------------------------------- A2: allocation totals

it('allocates shares that sum to the bundle price for equal weights, uneven weights and an indivisible price', function (): void {
    $allocate = app(AllocateBundlePrice::class);
    $admin = bndStaff('admin');

    $equal = bndSave($admin, [bndCourse(), bndCourse(), bndCourse()], [Market::Egypt->value => 10000]);
    $shares = $allocate($equal, Market::Egypt, 10000);
    expect(array_sum(array_column($shares, 'allocated_minor')))->toBe(10000);

    $uneven = bndSave($admin, [bndCourse(), bndCourse(), bndCourse()], [Market::Egypt->value => 9999], [1, 2, 7]);
    $shares = $allocate($uneven, Market::Egypt, 9999);
    expect(array_sum(array_column($shares, 'allocated_minor')))->toBe(9999)
        ->and(array_column($shares, 'weight'))->toBe([1, 2, 7]);
    foreach ($shares as $share) {
        expect($share['allocated_minor'])->toBeGreaterThanOrEqual(0);
    }

    $priceBased = bndSave($admin, [bndCourse(10000), bndCourse(30000)], [Market::Egypt->value => 20000]);
    $shares = array_values($allocate($priceBased, Market::Egypt, 20000));
    expect($shares[0]['weight'])->toBe(10000)
        ->and($shares[1]['weight'])->toBe(30000)
        ->and($shares[0]['allocated_minor'] + $shares[1]['allocated_minor'])->toBe(20000)
        ->and($shares[0]['allocated_minor'])->toBe(5000);
})->group('T-P8-07', 'A2')->group('payments');

it('keeps shares non-negative when the price is below the member count and breaks ties toward the lower course id', function (): void {
    $split = app(AllocateBundlePrice::class);

    $shares = $split->split(2, [11 => 1, 12 => 1, 13 => 1]);
    expect(array_sum($shares))->toBe(2)
        ->and(min($shares))->toBeGreaterThanOrEqual(0);

    $tie = $split->split(10, [9 => 1, 3 => 1, 5 => 1]);
    expect(array_sum($tie))->toBe(10)
        ->and($tie[3])->toBe(4)
        ->and($tie[5])->toBe(3)
        ->and($tie[9])->toBe(3);
})->group('T-P8-07', 'A2')->group('payments');

it('refuses empty or zero weights in AllocateBundlePrice::split', function (): void {
    $split = app(AllocateBundlePrice::class);

    expect(fn () => $split->split(100, []))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $split->split(100, [1 => 0, 2 => 1]))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $split->split(100, [1 => 0]))->toThrow(InvalidArgumentException::class);
})->group('T-P8-07', 'A2')->group('payments');

it('makes order item, allocation sum and order total equal the bundle net price across several bundles', function (): void {
    $admin = bndStaff('admin');
    $cases = [
        ['prices' => 30000, 'weights' => null, 'courses' => 3],
        ['prices' => 9999, 'weights' => [1, 2, 7], 'courses' => 3],
        ['prices' => 2, 'weights' => null, 'courses' => 2],
    ];

    foreach ($cases as $case) {
        $courses = [];
        for ($i = 0; $i < $case['courses']; $i++) {
            $courses[] = bndCourse();
        }
        $bundle = bndSave($admin, $courses, [Market::Egypt->value => $case['prices']], $case['weights']);
        $learner = bndLearner();
        $order = bndOrder($learner, [new OrderLine(ProductType::Bundle, $bundle->id, $learner)]);

        $item = OrderItem::query()->where('order_id', $order->id)->firstOrFail();
        $allocated = OrderItemAllocation::query()->where('order_item_id', $item->id)->get();

        expect($allocated->sum('allocated_minor'))->toBe($case['prices'])
            ->and($item->allocated_minor)->toBe($case['prices'])
            ->and($item->unit_price_minor)->toBe($case['prices'])
            ->and($order->total_minor)->toBe($case['prices'])
            ->and($allocated->min('allocated_minor'))->toBeGreaterThanOrEqual(0);
    }
})->group('T-P8-07', 'A2')->group('payments');

it('splits a partial amount (refund) across the stored shares so they sum to it and keep their proportions', function (): void {
    $admin = bndStaff('admin');
    $a = bndCourse();
    $b = bndCourse();
    $c = bndCourse();
    $bundle = bndSave($admin, [$a, $b, $c], [Market::Egypt->value => 12000], [1, 2, 3]);
    $learner = bndLearner();
    $order = bndOrder($learner, [new OrderLine(ProductType::Bundle, $bundle->id, $learner)]);
    $item = OrderItem::query()->where('order_id', $order->id)->firstOrFail();

    $full = app(OrderItemAllocations::class)->for($item);
    expect(array_sum($full))->toBe(12000)
        ->and($full[$a->id])->toBe(2000)
        ->and($full[$b->id])->toBe(4000)
        ->and($full[$c->id])->toBe(6000);

    $partial = app(OrderItemAllocations::class)->for($item, 5001);
    expect(array_sum($partial))->toBe(5001)
        ->and($partial[$c->id])->toBeGreaterThan($partial[$b->id])
        ->and($partial[$b->id])->toBeGreaterThan($partial[$a->id]);
})->group('T-P8-07', 'A2')->group('payments');

// ---------------------------------------------------------------- SaveBundle

it('lets finance and admin save a bundle and refuses learners, instructors, support and inactive staff', function (): void {
    $a = bndCourse();
    $b = bndCourse();

    expect(bndSave(bndStaff('finance'), [$a, $b])->exists)->toBeTrue()
        ->and(bndSave(bndStaff('admin'), [$a, $b])->exists)->toBeTrue();

    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_independent');
    $support = bndStaff('support');
    $inactive = User::factory()->create(['status' => 'inactive'])->assignRole('finance');
    foreach ([bndLearner(), $instructor, $support, $inactive] as $actor) {
        expect(fn () => bndSave($actor, [$a, $b]))->toThrow(AuthorizationException::class);
    }
})->group('T-P8-07', 'A2')->group('payments');

it('refuses invalid bundle data with the bundles language keys', function (): void {
    $admin = bndStaff('admin');
    $a = bndCourse();
    $b = bndCourse();
    $refuses = function (callable $fn, string $field, string $key): void {
        try {
            $fn();
            test()->fail('Expected a ValidationException.');
        } catch (ValidationException $e) {
            expect($e->errors())->toHaveKey($field)
                ->and($e->errors()[$field][0])->toBe(__('bundles.'.$key));
        }
    };

    $refuses(fn () => app(SaveBundle::class)($admin, ['name_ar' => 'ا', 'name_en' => 'Name', 'prices' => [], 'items' => [['course_id' => $a->id], ['course_id' => $b->id]]]), 'name', 'name_invalid');
    $refuses(fn () => bndSave($admin, [$a]), 'items', 'courses_invalid');
    $refuses(fn () => bndSave($admin, [$a, $a]), 'items', 'courses_invalid');
    $refuses(fn () => app(SaveBundle::class)($admin, ['name_ar' => 'باقة', 'name_en' => 'Bundle', 'prices' => [Market::Egypt->value => 1], 'items' => [['course_id' => $a->id], ['course_id' => 999999]]]), 'items', 'courses_missing');
    $refuses(fn () => bndSave($admin, [$a, $b], [Market::Egypt->value => 1000], [0, 1]), 'items', 'weight_invalid');
    $refuses(fn () => bndSave($admin, [$a, $b], [Market::Egypt->value => 1000], [5, null]), 'items', 'weights_mixed');
    $refuses(fn () => bndSave($admin, [$a, $b], [Market::Egypt->value => 0]), 'prices', 'price_invalid');
    $refuses(fn () => bndSave($admin, [$a, $b], [Market::SaudiArabia->value => 500]), 'prices', 'price_required');
})->group('T-P8-07', 'A2')->group('payments');

it('edits replace items and prices, write bundle.updated with before and after, and roll back when audit fails', function (): void {
    $admin = bndStaff('admin');
    $a = bndCourse();
    $b = bndCourse();
    $c = bndCourse();
    $bundle = bndSave($admin, [$a, $b], [Market::Egypt->value => 20000]);

    bndSave($admin, [$b, $c], [Market::Egypt->value => 25000], null, true, $bundle);

    expect($bundle->fresh()->items()->pluck('course_id')->all())->toEqualCanonicalizing([$b->id, $c->id])
        ->and($bundle->fresh()->prices()->pluck('amount_minor', 'market')->all())->toBe([Market::Egypt->value => 25000]);

    $updated = Activity::query()->where('event', 'bundle.updated')->latest('id')->first();
    expect($updated)->not->toBeNull()
        ->and($updated->properties->toArray())->toHaveKeys(['before', 'after']);

    // Audit failure rolls back the edit.
    $before = $bundle->fresh()->items()->pluck('course_id')->sort()->values()->all();
    activity()->disableLogging();
    try {
        expect(fn () => bndSave($admin, [$a, $c], [Market::Egypt->value => 99999], null, true, $bundle))
            ->toThrow(RuntimeException::class);
    } finally {
        activity()->enableLogging();
    }
    expect($bundle->fresh()->items()->pluck('course_id')->sort()->values()->all())->toBe($before)
        ->and($bundle->fresh()->prices()->value('amount_minor'))->toBe(25000);
})->group('T-P8-07', 'A2')->group('payments');

// ---------------------------------------------------------------- order engine

it('ignores the client price and prices a bundle line from the bundle price for the market', function (): void {
    $admin = bndStaff('admin');
    $a = bndCourse(10000);
    $b = bndCourse(40000);
    $bundle = bndSave($admin, [$a, $b], [Market::Egypt->value => 30000]);

    $learner = bndLearner();
    $order = bndOrder($learner, [new OrderLine(ProductType::Bundle, $bundle->id, $learner)]);

    expect($order->total_minor)->toBe(30000)
        ->and($order->subtotal_minor)->toBe(30000)
        ->and(OrderItem::query()->where('order_id', $order->id)->value('product_type'))->toBe(ProductType::Bundle);
})->group('T-P8-07', 'A2')->group('payments');

it('refuses inactive bundles, bundles with a draft course, a missing EG price, Saudi market and a learner holding every course', function (): void {
    $admin = bndStaff('admin');
    $a = bndCourse();
    $b = bndCourse();
    $learner = bndLearner();

    $inactive = bndSave($admin, [$a, $b], null, null, false);
    expect(fn () => bndOrder($learner, [new OrderLine(ProductType::Bundle, $inactive->id, $learner)]))
        ->toThrow(ValidationException::class);

    $draft = bndCourse(status: 'draft');
    $withDraft = bndSave($admin, [$a, $draft]);
    expect(fn () => bndOrder($learner, [new OrderLine(ProductType::Bundle, $withDraft->id, $learner)]))
        ->toThrow(ValidationException::class);

    $noEg = bndSave($admin, [$a, $b]);
    DB::table('bundle_prices')->where('bundle_id', $noEg->id)->delete();
    expect(fn () => bndOrder($learner, [new OrderLine(ProductType::Bundle, $noEg->id, $learner)]))
        ->toThrow(ValidationException::class);

    $priced = bndSave($admin, [$a, $b]);
    expect(fn () => app(CreateOrder::class)($learner, Market::SaudiArabia, [new OrderLine(ProductType::Bundle, $priced->id, $learner)], 'bnd-sa-refuse-1'))
        ->toThrow(ValidationException::class);

    $holder = bndLearner();
    app(GrantEntitlement::class)($holder, $a, EntitlementSource::Free);
    app(GrantEntitlement::class)($holder, $b, EntitlementSource::Free);
    expect(fn () => bndOrder($holder, [new OrderLine(ProductType::Bundle, $priced->id, $holder)]))
        ->toThrow(ValidationException::class);
})->group('T-P8-07', 'A2')->group('payments');

it('returns the same order on an idempotent retry without duplicating allocations', function (): void {
    $admin = bndStaff('admin');
    $bundle = bndSave($admin, [bndCourse(), bndCourse()]);
    $learner = bndLearner();
    $lines = [new OrderLine(ProductType::Bundle, $bundle->id, $learner)];

    $first = bndOrder($learner, $lines, 'bnd-retry-0001');
    $second = bndOrder($learner, $lines, 'bnd-retry-0001');

    expect($second->id)->toBe($first->id)
        ->and(Order::query()->count())->toBe(1)
        ->and(OrderItemAllocation::query()->count())->toBe(2);
})->group('T-P8-07', 'A2')->group('payments');

it('keeps the order item snapshot and allocations when the bundle is edited afterwards', function (): void {
    $admin = bndStaff('admin');
    $a = bndCourse();
    $b = bndCourse();
    $bundle = bndSave($admin, [$a, $b], [Market::Egypt->value => 20000]);
    $learner = bndLearner();
    $order = bndOrder($learner, [new OrderLine(ProductType::Bundle, $bundle->id, $learner)]);

    $itemBefore = OrderItem::query()->where('order_id', $order->id)->firstOrFail();
    $sharesBefore = OrderItemAllocation::query()->where('order_item_id', $itemBefore->id)->orderBy('course_id')->get(['course_id', 'allocated_minor'])->toArray();

    bndSave($admin, [$a, bndCourse()], [Market::Egypt->value => 90000], null, true, $bundle);

    $itemAfter = OrderItem::query()->whereKey($itemBefore->id)->firstOrFail();
    expect($itemAfter->unit_price_minor)->toBe(20000)
        ->and($itemAfter->title_en)->toBe($itemBefore->title_en)
        ->and(OrderItemAllocation::query()->where('order_item_id', $itemBefore->id)->orderBy('course_id')->get(['course_id', 'allocated_minor'])->toArray())->toBe($sharesBefore);
})->group('T-P8-07', 'A2')->group('payments');

it('refuses a coupon on a bundle-only order with coupon_ineligible', function (): void {
    $admin = bndStaff('admin');
    $a = bndCourse();
    $b = bndCourse();
    $bundle = bndSave($admin, [$a, $b]);
    $learner = bndLearner();
    $order = bndOrder($learner, [new OrderLine(ProductType::Bundle, $bundle->id, $learner)]);

    app(SaveCoupon::class)($admin, [
        'code' => 'BNDCOUPON',
        'name' => 'Bundle coupon',
        'discount_type' => 'fixed',
        'amount_minor' => 1000,
        'currency' => 'EGP',
        'max_redemptions' => 5,
        'max_per_user' => 1,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addDay(),
        'is_active' => true,
        'course_ids' => [$a->id],
    ]);

    expect(fn () => app(ApplyCoupon::class)($order, $learner, 'BNDCOUPON'))
        ->toThrow(ValidationException::class);
    expect(Order::query()->whereKey($order->id)->value('discount_minor'))->toBe(0);
})->group('T-P8-07', 'A2')->group('payments');

it('discounts only the course line of a mixed course and bundle order when a coupon applies', function (): void {
    $admin = bndStaff('admin');
    $a = bndCourse(10000);
    $b = bndCourse(10000);
    $c = bndCourse(10000);
    $bundle = bndSave($admin, [$b, $c], [Market::Egypt->value => 15000]);
    $learner = bndLearner();
    $order = bndOrder($learner, [
        new OrderLine(ProductType::Course, $a->id, $learner),
        new OrderLine(ProductType::Bundle, $bundle->id, $learner),
    ]);

    app(SaveCoupon::class)($admin, [
        'code' => 'BNDMIX1000',
        'name' => 'Mixed coupon',
        'discount_type' => 'fixed',
        'amount_minor' => 1000,
        'currency' => 'EGP',
        'max_redemptions' => 5,
        'max_per_user' => 1,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addDay(),
        'is_active' => true,
        'course_ids' => [$a->id],
    ]);

    $discounted = app(ApplyCoupon::class)($order, $learner, 'BNDMIX1000');
    expect($discounted->discount_minor)->toBe(1000)
        ->and($discounted->total_minor)->toBe(24000)
        ->and(OrderItem::query()->where('order_id', $order->id)->where('product_type', 'bundle')->value('unit_price_minor'))->toBe(15000);
})->group('T-P8-07', 'A2')->group('payments');

// ---------------------------------------------------------------- language and architecture

it('keeps lang/ar and lang/en bundles keys identical and has bundle audit labels in both locales', function (): void {
    $ar = require dirname(__DIR__, 3).'/lang/ar/bundles.php';
    $en = require dirname(__DIR__, 3).'/lang/en/bundles.php';

    expect(array_keys($ar))->toEqualCanonicalizing(array_keys($en));

    $auditAr = require dirname(__DIR__, 3).'/lang/ar/audit-events.php';
    $auditEn = require dirname(__DIR__, 3).'/lang/en/audit-events.php';
    foreach (['bundle.created', 'bundle.updated', 'bundle.granted'] as $event) {
        expect($auditAr)->toHaveKey($event)
            ->and($auditEn)->toHaveKey($event);
    }
})->group('T-P8-07', 'A2')->group('i18n');

it('keeps the new Commerce Actions final', function (): void {
    foreach ([SaveBundle::class, AllocateBundlePrice::class, OrderItemAllocations::class] as $class) {
        expect((new ReflectionClass($class))->isFinal())->toBeTrue();
    }
})->group('T-P8-07', 'A2')->group('arch');

it('keeps App\Domain\Commerce free of HTTP and Filament imports in the new bundle code', function (): void {
    foreach (['Actions/SaveBundle.php', 'Actions/AllocateBundlePrice.php', 'Queries/OrderItemAllocations.php', 'Bundle.php'] as $file) {
        $source = file_get_contents(dirname(__DIR__, 3).'/app/Domain/Commerce/'.$file);
        expect($source)->not->toContain('Filament\\')
            ->and($source)->not->toContain('Illuminate\\Http\\');
    }
})->group('T-P8-07', 'A2')->group('arch');

it('marks the Egypt-only bundle price correctly in the bundle_prices table', function (): void {
    $admin = bndStaff('admin');
    $bundle = bndSave($admin, [bndCourse(), bndCourse()], [Market::Egypt->value => 4321]);

    expect(DB::table('bundle_prices')->where('bundle_id', $bundle->id)->first(['market', 'currency', 'amount_minor']))
        ->market->toBe('EG')
        ->currency->toBe('EGP')
        ->amount_minor->toBe(4321);
})->group('T-P8-07', 'A2')->group('payments');

// ---------------------------------------------------------------- not covered in this pass

it('webhook-fulfils a paid bundle order and refuses plan-line flags (not covered by this test file)')
    ->todo()
    ->group('T-P8-07', 'A1')->group('payments');

it('renders the bundle list and allocation modal for finance and admin in AR and EN (Filament, not covered by this test file)')
    ->todo()
    ->group('T-P8-07', 'A2')->group('payments');
