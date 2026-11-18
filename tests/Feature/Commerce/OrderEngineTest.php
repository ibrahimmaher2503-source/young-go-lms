<?php

declare(strict_types=1);

use App\Domain\Catalog\CourseStatus;
use App\Domain\Commerce\Actions\AdjustOrderTotal;
use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Actions\TransitionOrder;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\ProductType;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Shared\Markets\Market;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

function ordCourse(int $amountMinor = 25000, string $status = 'published', string $accessMode = 'paid', bool $price = true): Course
{
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة مدفوعة', 'en' => 'Paid Course '.uniqid()],
        'slug_ar' => 'ord-ar-'.uniqid(),
        'slug_en' => 'ord-en-'.uniqid(),
        'status' => $status,
        'access_mode' => $accessMode,
    ]);
    if ($price) {
        CoursePrice::query()->create(['course_id' => $course->id, 'market' => 'EG', 'currency' => 'EGP', 'amount_minor' => $amountMinor]);
    }

    return $course;
}

it('creates server-priced EGP orders idempotently, hides request_hash, and rolls back on audit failure', function (): void {
    $payer = User::factory()->create(['status' => 'active'])->assignRole('student');
    $c1 = ordCourse(15000);
    $c2 = ordCourse(10000);

    $create = app(CreateOrder::class);
    $order = $create($payer, Market::Egypt, [
        new OrderLine(ProductType::Course, $c1->id, $payer),
        new OrderLine(ProductType::Course, $c2->id, $payer),
    ], 'key-order-0001');

    expect($order->subtotal_minor)->toBe(25000)
        ->and($order->discount_minor)->toBe(0)
        ->and($order->total_minor)->toBe(25000)
        ->and($order->currency)->toBe('EGP')
        ->and($order->status)->toBe(OrderStatus::Pending)
        ->and($order->items()->count())->toBe(2)
        ->and($order->toArray())->not->toHaveKey('request_hash')
        ->and(Activity::query()->where('event', 'order.created')->count())->toBe(1);

    // Repeating same payer + idempotency key + lines returns same order
    $same = $create($payer, Market::Egypt, [
        new OrderLine(ProductType::Course, $c2->id, $payer),
        new OrderLine(ProductType::Course, $c1->id, $payer),
    ], 'key-order-0001');
    expect($same->id)->toBe($order->id)
        ->and(Order::query()->count())->toBe(1);

    // Reusing key with different payload refuses
    expect(fn () => $create($payer, Market::Egypt, [new OrderLine(ProductType::Course, $c1->id, $payer)], 'key-order-0001'))
        ->toThrow(ValidationException::class);

    // Audit failure rolls back order + items atomically
    activity()->disableLogging();
    try {
        expect(fn () => $create($payer, Market::Egypt, [new OrderLine(ProductType::Course, $c1->id, $payer)], 'key-rollback-01'))
            ->toThrow(RuntimeException::class);
    } finally {
        activity()->enableLogging();
    }
    expect(Order::query()->count())->toBe(1);
})->group('T-P8-01', 'A1', 'D-008', 'D-012', 'ED-012', 'ED-034')->group('payments');

it('rejects disabled Saudi market, non-sellable/draft/unpriced/already-enrolled lines, and invalid idempotency keys', function (): void {
    $payer = User::factory()->create(['status' => 'active'])->assignRole('student');
    $pubPaid = ordCourse(12000);
    $draft = ordCourse(12000, status: CourseStatus::Draft->value);
    $free = ordCourse(0, accessMode: Course::ACCESS_FREE, price: false);
    $unpriced = ordCourse(12000, price: false);

    $create = app(CreateOrder::class);

    expect(fn () => $create($payer, Market::SaudiArabia, [new OrderLine(ProductType::Course, $pubPaid->id, $payer)], 'sa-rejected-01'))
        ->toThrow(ValidationException::class);
    expect(fn () => $create($payer, Market::Egypt, [new OrderLine(ProductType::Bundle, $pubPaid->id, $payer)], 'bundle-refuse-01'))
        ->toThrow(ValidationException::class);
    expect(fn () => $create($payer, Market::Egypt, [new OrderLine(ProductType::Course, $draft->id, $payer)], 'draft-refuse-01'))
        ->toThrow(ValidationException::class);
    expect(fn () => $create($payer, Market::Egypt, [new OrderLine(ProductType::Course, $free->id, $payer)], 'free-refuse-01'))
        ->toThrow(ValidationException::class);
    expect(fn () => $create($payer, Market::Egypt, [new OrderLine(ProductType::Course, $unpriced->id, $payer)], 'noprice-refuse-01'))
        ->toThrow(ValidationException::class);
    expect(fn () => $create($payer, Market::Egypt, [
        new OrderLine(ProductType::Course, $pubPaid->id, $payer),
        new OrderLine(ProductType::Course, $pubPaid->id, $payer),
    ], 'dup-line-refuse-01'))->toThrow(ValidationException::class);
    expect(fn () => $create($payer, Market::Egypt, [new OrderLine(ProductType::Course, $pubPaid->id, $payer)], 'short'))
        ->toThrow(ValidationException::class);

    app(GrantEntitlement::class)($payer, $pubPaid, EntitlementSource::Free);
    expect(fn () => $create($payer, Market::Egypt, [new OrderLine(ProductType::Course, $pubPaid->id, $payer)], 'enrolled-refuse-01'))
        ->toThrow(ValidationException::class);
})->group('T-P8-01', 'A2', 'YG-REV-PAY-001', 'ED-012')->group('payments');

it('supports verified parent buying for a child learner and refuses unlinked or pending learners', function (): void {
    $parent = User::factory()->create(['status' => 'active'])->assignRole('parent');
    $child = User::factory()->create(['status' => 'active'])->assignRole('student');
    $pendingChild = User::factory()->create(['status' => 'active'])->assignRole('student');
    $course = ordCourse(20000);

    GuardianChildLink::query()->create([
        'guardian_id' => $parent->id,
        'child_id' => $child->id,
        'status' => GuardianChildLink::STATUS_VERIFIED,
        'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT,
        'verified_at' => now(),
    ]);
    GuardianChildLink::query()->create([
        'guardian_id' => $parent->id,
        'child_id' => $pendingChild->id,
        'status' => GuardianChildLink::STATUS_PENDING,
        'method' => GuardianChildLink::METHOD_LINK_CODE,
    ]);

    $create = app(CreateOrder::class);
    $order = $create($parent, Market::Egypt, [new OrderLine(ProductType::Course, $course->id, $child)], 'parent-child-01');
    expect($order->payer_id)->toBe($parent->id)
        ->and($order->items()->sole()->learner_id)->toBe($child->id);

    expect(fn () => $create($parent, Market::Egypt, [new OrderLine(ProductType::Course, $course->id, $pendingChild)], 'pending-child-01'))
        ->toThrow(ValidationException::class);
})->group('T-P8-01', 'A3', 'YG-GAP-FAM-005')->group('payments');

it('locks totals on paid orders through Order::updating without casting OrderStatus enum directly to string', function (): void {
    $payer = User::factory()->create(['status' => 'active'])->assignRole('student');
    $finance = User::factory()->create(['status' => 'active'])->assignRole('finance');
    $course = ordCourse(20000);
    $order = app(CreateOrder::class)($payer, Market::Egypt, [new OrderLine(ProductType::Course, $course->id, $payer)], 'lock-paid-01');

    app(TransitionOrder::class)($order, OrderStatus::Paid);
    $paid = $order->fresh();
    expect($paid->status)->toBe(OrderStatus::Paid)
        ->and($paid->paid_at)->not->toBeNull();

    // Direct money column mutation on a paid order is refused by Order::updating
    expect(function () use ($paid): void {
        $paid->total_minor = 100;
        $paid->save();
    })->toThrow(LogicException::class);

    expect($paid->fresh()->total_minor)->toBe(20000);

    // AdjustOrderTotal opens the totals guard safely and records audit log
    $adjusted = app(AdjustOrderTotal::class)($paid->fresh(), $finance, 5000, 'Approved partial credit after payment');
    expect($adjusted->discount_minor)->toBe(5000)
        ->and($adjusted->total_minor)->toBe(15000);
})->group('T-P8-01', 'A1', 'A2', 'YG-GAP-PAY-001')->group('payments');
