<?php

declare(strict_types=1);

// T-COHORT-SEATS | PRD 29.3 (YG-GAP-COH-002), 17.3 | A1 A2 A3 A4 A5 A6 A7 A10

use App\Domain\Catalog\CourseStatus;
use App\Domain\Cohorts\Actions\ManageCohorts;
use App\Domain\Cohorts\Actions\ReserveCohortSeat;
use App\Domain\Cohorts\Cohort;
use App\Domain\Cohorts\CohortFullException;
use App\Domain\Cohorts\CohortMember;
use App\Domain\Cohorts\CohortSeatHold;
use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Actions\TransitionOrder;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\ProductType;
use App\Domain\Shared\Markets\Market;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Carbon::setTestNow(Carbon::parse('2026-11-19 10:00:00', 'Africa/Cairo'));
});

afterEach(fn () => Carbon::setTestNow());

/** A paid live course with an EGP price and an admin who can manage its cohorts. @return array{0: Course, 1: User} */
function tcsCourse(int $amountMinor = 25000): array
{
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة', 'en' => 'Cohort course '.uniqid()],
        'slug_ar' => 'tcs-ar-'.uniqid(),
        'slug_en' => 'tcs-en-'.uniqid(),
        'status' => CourseStatus::Published->value,
        'access_mode' => 'paid',
        'format' => 'live',
    ]);
    CoursePrice::query()->create(['course_id' => $course->id, 'market' => 'EG', 'currency' => 'EGP', 'amount_minor' => $amountMinor]);

    return [$course, $admin];
}

function tcsBuyer(): User
{
    return User::factory()->create(['status' => 'active'])->assignRole('student');
}

/** @return array{0: Order, 1: CohortSeatHold|null} */
function tcsOrder(User $payer, Course $course, string $key): array
{
    $order = app(CreateOrder::class)($payer, Market::Egypt, [new OrderLine(ProductType::Course, $course->id, $payer)], $key);
    $hold = CohortSeatHold::query()->where('order_item_id', $order->items()->firstOrFail()->id)->first();

    return [$order, $hold];
}

function tcsCohort(User $admin, Course $course, ?int $capacity, array $extra = []): Cohort
{
    return app(ManageCohorts::class)->create($admin, $course, ['name' => 'Spring '.uniqid(), 'capacity' => $capacity, ...$extra]);
}

it('holds one seat on create, for 30 minutes, audited, with no seated member yet', function (): void {
    [$course, $admin] = tcsCourse();
    $cohort = tcsCohort($admin, $course, 3);
    $buyer = tcsBuyer();

    [$order, $hold] = tcsOrder($buyer, $course, 'tcs-key-a1-0001');

    expect(CohortSeatHold::query()->count())->toBe(1)
        ->and($hold)->not->toBeNull()
        ->and($hold->status)->toBe(CohortSeatHold::HELD)
        ->and($hold->cohort_id)->toBe($cohort->id)
        ->and($hold->learner_id)->toBe($buyer->id)
        ->and($hold->expires_at->equalTo(now()->addMinutes(30)))->toBeTrue()
        ->and($cohort->refresh()->seatsLeft())->toBe(2)
        ->and(CohortMember::query()->where('cohort_id', $cohort->id)->count())->toBe(0)
        ->and(Activity::query()->where('event', 'cohort.seat_held')->count())->toBe(1)
        ->and($order->status)->toBe(OrderStatus::Pending);
})->group('T-COHORT-SEATS', 'A1');

it('follows commerce.seat_hold_minutes when it is changed', function (): void {
    config(['commerce.seat_hold_minutes' => 45]);
    [$course, $admin] = tcsCourse();
    tcsCohort($admin, $course, 3);

    [, $hold] = tcsOrder(tcsBuyer(), $course, 'tcs-key-a1-0002');

    expect($hold->expires_at->equalTo(now()->addMinutes(45)))->toBeTrue();
})->group('T-COHORT-SEATS', 'A1');

it('returns the same order and keeps one hold for the same idempotency key', function (): void {
    [$course, $admin] = tcsCourse();
    tcsCohort($admin, $course, 3);
    $buyer = tcsBuyer();

    [$first] = tcsOrder($buyer, $course, 'tcs-key-a1-0003');
    [$again] = tcsOrder($buyer, $course, 'tcs-key-a1-0003');

    expect($again->id)->toBe($first->id)
        ->and(CohortSeatHold::query()->count())->toBe(1);
})->group('T-COHORT-SEATS', 'A1');

it('refuses a buyer when the only seat is held, with no order, item, audit or hold left behind', function (): void {
    [$course, $admin] = tcsCourse();
    tcsCohort($admin, $course, 1);
    tcsOrder(tcsBuyer(), $course, 'tcs-key-a2-0001');

    $orders = Order::query()->count();
    $items = Order::query()->first()->items()->count();
    $createdAudits = Activity::query()->where('event', 'order.created')->count();

    $refused = tcsBuyer();
    try {
        tcsOrder($refused, $course, 'tcs-key-a2-0002');
        $this->fail('Expected cohort_full refusal');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('order')
            ->and($e->errors()['order'][0])->toBe(__('commerce.cohort_full'));
    }

    expect(Order::query()->count())->toBe($orders)
        ->and(Order::query()->first()->items()->count())->toBe($items)
        ->and(Activity::query()->where('event', 'order.created')->count())->toBe($createdAudits)
        ->and(CohortSeatHold::query()->where('learner_id', $refused->id)->exists())->toBeFalse();
})->group('T-COHORT-SEATS', 'A2');

it('refuses a buyer when the seat is taken by a seated member', function (): void {
    [$course, $admin] = tcsCourse();
    $cohort = tcsCohort($admin, $course, 1);
    app(ReserveCohortSeat::class)($cohort, tcsBuyer());

    expect(fn () => tcsOrder(tcsBuyer(), $course, 'tcs-key-a2-0004'))
        ->toThrow(ValidationException::class);
})->group('T-COHORT-SEATS', 'A2');

it('never refuses when the cohort has no capacity limit', function (): void {
    [$course, $admin] = tcsCourse();
    tcsCohort($admin, $course, null);

    foreach (range(1, 4) as $i) {
        [, $hold] = tcsOrder(tcsBuyer(), $course, 'tcs-key-a2-unl-'.$i);
        expect($hold)->not->toBeNull();
    }
})->group('T-COHORT-SEATS', 'A2');

it('lets a buyer in when the full cohort is full only because of an expired hold', function (): void {
    [$course, $admin] = tcsCourse();
    $cohort = tcsCohort($admin, $course, 1);
    tcsOrder(tcsBuyer(), $course, 'tcs-key-a2-exp-1');

    Carbon::setTestNow(now()->addMinutes(31));

    [, $hold] = tcsOrder(tcsBuyer(), $course, 'tcs-key-a2-exp-2');
    expect($hold)->not->toBeNull()
        ->and($cohort->refresh()->heldCount())->toBe(1);
})->group('T-COHORT-SEATS', 'A2');

it('counts one seat for a learner with two pending orders for the course', function (): void {
    [$course, $admin] = tcsCourse();
    $cohort = tcsCohort($admin, $course, 2);
    $learner = tcsBuyer();

    tcsOrder($learner, $course, 'tcs-key-a3-0001');
    tcsOrder($learner, $course, 'tcs-key-a3-0002');

    expect($cohort->refresh()->heldCount())->toBe(1)
        ->and($cohort->seatsLeft())->toBe(1);
})->group('T-COHORT-SEATS', 'A3');

it('gives the last seat to the next buyer once the holder cancels', function (): void {
    [$course, $admin] = tcsCourse();
    tcsCohort($admin, $course, 1);
    [$holderOrder] = tcsOrder(tcsBuyer(), $course, 'tcs-key-a3-0003');
    $second = tcsBuyer();

    expect(fn () => tcsOrder($second, $course, 'tcs-key-a3-0004'))->toThrow(ValidationException::class);

    app(TransitionOrder::class)($holderOrder, OrderStatus::Cancelled);
    [, $hold] = tcsOrder($second, $course, 'tcs-key-a3-0005');

    expect($hold)->not->toBeNull();
})->group('T-COHORT-SEATS', 'A3');

it('holds the earliest enrolling cohort with room and refuses when none has room', function (): void {
    [$course, $admin] = tcsCourse();
    $first = tcsCohort($admin, $course, 1, ['starts_on' => '2026-12-01', 'ends_on' => '2027-01-31']);
    $second = tcsCohort($admin, $course, 1, ['starts_on' => '2027-02-01', 'ends_on' => '2027-03-31']);

    [, $hold1] = tcsOrder(tcsBuyer(), $course, 'tcs-key-a4-0001');
    [, $hold2] = tcsOrder(tcsBuyer(), $course, 'tcs-key-a4-0002');

    expect($hold1->cohort_id)->toBe($first->id)
        ->and($hold2->cohort_id)->toBe($second->id);

    expect(fn () => tcsOrder(tcsBuyer(), $course, 'tcs-key-a4-0003'))->toThrow(ValidationException::class);
})->group('T-COHORT-SEATS', 'A4');

it('holds nothing for a course without an enrolling cohort', function (): void {
    [$course, $admin] = tcsCourse();
    tcsCohort($admin, $course, 1, ['enrollment_closes_on' => '2026-11-01']);

    [$order, $hold] = tcsOrder(tcsBuyer(), $course, 'tcs-key-a4-0004');

    expect($hold)->toBeNull()
        ->and($order->status)->toBe(OrderStatus::Pending)
        ->and(CohortSeatHold::query()->count())->toBe(0);
})->group('T-COHORT-SEATS', 'A4');

it('converts the hold to a seated member when the order is paid, once', function (): void {
    [$course, $admin] = tcsCourse();
    $cohort = tcsCohort($admin, $course, 2);
    $buyer = tcsBuyer();
    [$order] = tcsOrder($buyer, $course, 'tcs-key-a5-0001');

    app(TransitionOrder::class)($order, OrderStatus::Paid, $admin);
    app(TransitionOrder::class)($order, OrderStatus::Paid, $admin);

    $hold = CohortSeatHold::query()->where('learner_id', $buyer->id)->firstOrFail();
    $member = CohortMember::query()->where('cohort_id', $cohort->id)->where('learner_id', $buyer->id)->first();

    expect($hold->status)->toBe(CohortSeatHold::CONVERTED)
        ->and($hold->converted_at)->not->toBeNull()
        ->and($member)->not->toBeNull()
        ->and($member->seated_at)->not->toBeNull()
        ->and($cohort->refresh()->seatedCount())->toBe(1)
        ->and($cohort->heldCount())->toBe(0)
        ->and($cohort->hasSeat($buyer))->toBeTrue()
        ->and(Activity::query()->where('event', 'cohort.seat_converted')->count())->toBe(1);
})->group('T-COHORT-SEATS', 'A5');

it('releases the hold with reason failed when the order fails and frees the seat at once', function (): void {
    [$course, $admin] = tcsCourse();
    $cohort = tcsCohort($admin, $course, 1);
    $buyer = tcsBuyer();
    [$order] = tcsOrder($buyer, $course, 'tcs-key-a6-0001');

    app(TransitionOrder::class)($order, OrderStatus::Failed, $admin);

    $hold = CohortSeatHold::query()->where('learner_id', $buyer->id)->firstOrFail();

    expect($hold->status)->toBe(CohortSeatHold::RELEASED)
        ->and($hold->release_reason)->toBe('failed')
        ->and($cohort->refresh()->seatsLeft())->toBe(1)
        ->and(Activity::query()->where('event', 'cohort.seat_released')->count())->toBe(1);
})->group('T-COHORT-SEATS', 'A6');

it('releases the hold with reason cancelled when the order is cancelled', function (): void {
    [$course, $admin] = tcsCourse();
    tcsCohort($admin, $course, 1);
    $buyer = tcsBuyer();
    [$order] = tcsOrder($buyer, $course, 'tcs-key-a6-0002');

    app(TransitionOrder::class)($order, OrderStatus::Cancelled, $admin);

    expect(CohortSeatHold::query()->where('learner_id', $buyer->id)->value('release_reason'))->toBe('cancelled');
})->group('T-COHORT-SEATS', 'A6');

it('releases an expired hold through the command once, and leaves a converted hold alone', function (): void {
    [$course, $admin] = tcsCourse();
    $cohort = tcsCohort($admin, $course, 2);
    $pendingBuyer = tcsBuyer();
    $paidBuyer = tcsBuyer();
    tcsOrder($pendingBuyer, $course, 'tcs-key-a7-0001');
    [$paidOrder] = tcsOrder($paidBuyer, $course, 'tcs-key-a7-0002');
    app(TransitionOrder::class)($paidOrder, OrderStatus::Paid, $admin);

    Carbon::setTestNow(now()->addMinutes(31));

    $this->artisan('orders:release-expired-seat-holds')
        ->expectsOutputToContain('Released 1 expired seat holds')
        ->assertSuccessful();

    $released = CohortSeatHold::query()->where('learner_id', $pendingBuyer->id)->firstOrFail();
    expect($released->status)->toBe(CohortSeatHold::RELEASED)
        ->and($released->release_reason)->toBe('expired')
        ->and(CohortSeatHold::query()->where('learner_id', $paidBuyer->id)->value('status'))->toBe(CohortSeatHold::CONVERTED)
        ->and(Activity::query()->where('event', 'cohort.seat_released')->count())->toBe(1);

    $this->artisan('orders:release-expired-seat-holds')
        ->expectsOutputToContain('Released 0 expired seat holds')
        ->assertSuccessful();

    expect(Activity::query()->where('event', 'cohort.seat_released')->count())->toBe(1)
        ->and($cohort->refresh()->seatedCount())->toBe(1);
})->group('T-COHORT-SEATS', 'A7');

it('is scheduled every minute without overlap', function (): void {
    $events = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())
        ->filter(fn ($event) => str_contains((string) $event->command, 'orders:release-expired-seat-holds'));

    expect($events)->toHaveCount(1);
    $event = $events->first();
    expect($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
})->group('T-COHORT-SEATS', 'A7');

it('refuses a manual seat on the last seat held by a live hold, and lets the holder take it', function (): void {
    [$course, $admin] = tcsCourse();
    $cohort = tcsCohort($admin, $course, 1);
    $holder = tcsBuyer();
    tcsOrder($holder, $course, 'tcs-key-a10-0001');

    expect(fn () => app(ReserveCohortSeat::class)($cohort, tcsBuyer()))->toThrow(CohortFullException::class);

    $seat = app(ReserveCohortSeat::class)($cohort->refresh(), $holder);
    expect($seat)->not->toBeNull()
        ->and($cohort->refresh()->seatedCount())->toBe(1)
        ->and($cohort->heldCount())->toBe(0);
})->group('T-COHORT-SEATS', 'A10');
