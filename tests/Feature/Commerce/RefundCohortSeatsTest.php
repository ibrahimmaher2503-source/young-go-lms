<?php

declare(strict_types=1);

use App\Domain\Catalog\CourseStatus;
use App\Domain\Cohorts\Actions\JoinWaitlist;
use App\Domain\Cohorts\Actions\ManageCohorts;
use App\Domain\Cohorts\Actions\PromoteFromWaitlist;
use App\Domain\Cohorts\Actions\ReleaseSeatHolds;
use App\Domain\Cohorts\Actions\ReserveCohortSeat;
use App\Domain\Cohorts\CohortMember;
use App\Domain\Cohorts\CohortSeatHold;
use App\Domain\Commerce\Actions\ApproveRefund;
use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Actions\ExecuteRefund;
use App\Domain\Commerce\Actions\GrantOrderEntitlements;
use App\Domain\Commerce\Actions\RequestRefund;
use App\Domain\Commerce\Actions\SaveBundle;
use App\Domain\Commerce\Actions\TransitionOrder;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\ProductType;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Shared\Markets\Market;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class)->group('T-P8-06', 'T-COHORT-SEATS', 'YG-GAP-COH-002');

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    config(['refunds.revoke_access' => 'full']);
    $this->learner = User::factory()->create(['status' => 'active'])->assignRole('student');
    $this->finance = User::factory()->create(['status' => 'active'])->assignRole('finance');
    $this->admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $this->course = Course::query()->create([
        'title' => ['ar' => 'دورة', 'en' => 'Live cohort'],
        'slug_ar' => 'refund-cohort-ar', 'slug_en' => 'refund-cohort-en',
        'status' => CourseStatus::Published, 'access_mode' => 'paid', 'format' => 'live',
    ]);
    CoursePrice::query()->create(['course_id' => $this->course->id, 'market' => 'EG', 'currency' => 'EGP', 'amount_minor' => 25000]);
    $this->cohort = app(ManageCohorts::class)->create($this->admin, $this->course, ['name' => 'Spring cohort', 'capacity' => 1]);
});

function refundCohortOrder(User $learner, Course $course, string $key): Order
{
    return app(CreateOrder::class)($learner, Market::Egypt, [new OrderLine(ProductType::Course, $course->id, $learner)], $key);
}

function payRefundCohortOrder(Order $order): void
{
    app(TransitionOrder::class)($order, OrderStatus::Paid);
    app(GrantOrderEntitlements::class)($order->refresh(), EntitlementSource::Purchase);
}

function refundCohortPayment(User $finance, Order $order, int $amount): void
{
    $refund = app(RequestRefund::class)($finance, $order, $amount, 'Learner requested a refund');
    app(ApproveRefund::class)($finance, $refund);
    app(ExecuteRefund::class)($finance, $refund->refresh(), 'REFUND-COHORT');
}

it('gives back an order-funded seat after the final refund and leaves promotion to staff', function (): void {
    $order = refundCohortOrder($this->learner, $this->course, 'refund-cohort-1');
    payRefundCohortOrder($order);
    $waiter = User::factory()->create(['status' => 'active'])->assignRole('student');
    $queued = app(JoinWaitlist::class)($this->cohort, $waiter);
    $member = $this->cohort->members()->where('learner_id', $this->learner->id)->firstOrFail();

    refundCohortPayment($this->finance, $order, 10000);
    expect($member->refresh()->status)->toBe(CohortMember::SEATED);

    refundCohortPayment($this->finance, $order, 15000);
    expect($member->refresh()->status)->toBe(CohortMember::REMOVED)
        ->and($member->removed_at)->not->toBeNull()
        ->and($queued->refresh()->status)->toBe(CohortMember::WAITLISTED)
        ->and($this->cohort->seatsLeft())->toBe(1)
        ->and(Activity::query()->where('event', 'cohort.member_removed')->count())->toBe(1);
});

it('retains a commerce seat until the second qualifying paid order is refunded', function (): void {
    $first = refundCohortOrder($this->learner, $this->course, 'refund-cohort-2');
    $second = refundCohortOrder($this->learner, $this->course, 'refund-cohort-3');
    payRefundCohortOrder($first);
    payRefundCohortOrder($second);

    refundCohortPayment($this->finance, $first, 25000);
    expect($this->cohort->hasSeat($this->learner))->toBeTrue();

    refundCohortPayment($this->finance, $second, 25000);
    expect($this->cohort->hasSeat($this->learner))->toBeFalse();
});

it('removes the paid membership while retaining another order hold for the same learner', function (): void {
    $first = refundCohortOrder($this->learner, $this->course, 'refund-cohort-4');
    $second = refundCohortOrder($this->learner, $this->course, 'refund-cohort-5');
    payRefundCohortOrder($first);

    refundCohortPayment($this->finance, $first, 25000);

    $hold = CohortSeatHold::query()->whereIn('order_item_id', $second->items()->select('id'))->firstOrFail();
    expect($this->cohort->hasSeat($this->learner))->toBeFalse()
        ->and($hold->status)->toBe(CohortSeatHold::HELD)
        ->and($this->cohort->seatsLeft())->toBe(0);
});

it('preserves a staff seat when an outstanding checkout is paid and later refunded', function (): void {
    $order = refundCohortOrder($this->learner, $this->course, 'refund-cohort-6');
    app(ReserveCohortSeat::class)($this->cohort, $this->learner, $this->admin);
    payRefundCohortOrder($order);

    refundCohortPayment($this->finance, $order, 25000);
    expect($this->cohort->hasSeat($this->learner))->toBeTrue();
});

it('keeps the seat under a refund policy that preserves access', function (): void {
    config(['refunds.revoke_access' => 'none']);
    $order = refundCohortOrder($this->learner, $this->course, 'refund-cohort-7');
    payRefundCohortOrder($order);

    refundCohortPayment($this->finance, $order, 25000);

    expect($this->cohort->hasSeat($this->learner))->toBeTrue();
});

it('releases only after a full refund even when the first partial refund already revoked access', function (): void {
    config(['refunds.revoke_access' => 'any']);
    $order = refundCohortOrder($this->learner, $this->course, 'refund-cohort-8');
    payRefundCohortOrder($order);

    refundCohortPayment($this->finance, $order, 10000);
    expect($this->cohort->hasSeat($this->learner))->toBeTrue();

    refundCohortPayment($this->finance, $order, 15000);
    expect($this->cohort->hasSeat($this->learner))->toBeFalse();
});

it('releases the matching cohorts held on one bundle line and preserves a separately funded seat', function (): void {
    $secondCourse = Course::query()->create([
        'title' => ['ar' => 'دورة ثانية', 'en' => 'Second live cohort course'],
        'slug_ar' => 'refund-bundle-second-ar', 'slug_en' => 'refund-bundle-second-en',
        'status' => CourseStatus::Published, 'access_mode' => 'paid', 'format' => 'live',
    ]);
    $secondCohort = app(ManageCohorts::class)->create($this->admin, $secondCourse, ['name' => 'Second cohort', 'capacity' => 1]);
    $bundle = app(SaveBundle::class)($this->finance, [
        'name_ar' => 'مجموعة', 'name_en' => 'Live cohort bundle', 'is_active' => true,
        'prices' => ['EG' => 30000],
        'items' => [['course_id' => $this->course->id], ['course_id' => $secondCourse->id]],
    ]);
    $selections = [$this->course->id => $this->cohort->id, $secondCourse->id => $secondCohort->id];
    $order = app(CreateOrder::class)($this->learner, Market::Egypt, [
        new OrderLine(ProductType::Bundle, $bundle->id, $this->learner, $selections),
    ], 'refund-cohort-bundle');
    $item = $order->items()->sole();
    $separate = refundCohortOrder($this->learner, $this->course, 'refund-cohort-bundle-separate');
    payRefundCohortOrder($order);
    payRefundCohortOrder($separate);

    expect($this->cohort->members()->sole()->seat_order_item_id)->toBe($item->id)
        ->and($secondCohort->members()->sole()->seat_order_item_id)->toBe($item->id);

    refundCohortPayment($this->finance, $order, 30000);
    expect($this->cohort->hasSeat($this->learner))->toBeTrue()
        ->and($secondCohort->hasSeat($this->learner))->toBeFalse();

    refundCohortPayment($this->finance, $separate, 25000);
    expect($this->cohort->hasSeat($this->learner))->toBeFalse();
});

it('preserves historical seating when the original provenance was never recorded', function (): void {
    $order = refundCohortOrder($this->learner, $this->course, 'refund-cohort-legacy');
    payRefundCohortOrder($order);
    $this->cohort->members()->update(['seat_order_item_id' => null]);

    refundCohortPayment($this->finance, $order, 25000);

    expect($this->cohort->hasSeat($this->learner))->toBeTrue();
});

it('removes a commerce-funded late-payment waitlist entry before staff can promote it', function (): void {
    $order = refundCohortOrder($this->learner, $this->course, 'refund-waitlist-late');
    app(ReleaseSeatHolds::class)([$order->items()->sole()->id], ReleaseSeatHolds::EXPIRED);
    $occupant = User::factory()->create(['status' => 'active']);
    $seat = app(ReserveCohortSeat::class)($this->cohort, $occupant, $this->admin);
    $volunteer = User::factory()->create(['status' => 'active']);
    $ordinary = app(JoinWaitlist::class)($this->cohort, $volunteer);
    payRefundCohortOrder($order);
    $paid = $this->cohort->members()->where('learner_id', $this->learner->id)->sole();
    expect($paid->status)->toBe(CohortMember::WAITLISTED);

    refundCohortPayment($this->finance, $order, 25000);
    expect($paid->refresh()->status)->toBe(CohortMember::REMOVED)
        ->and($ordinary->refresh()->status)->toBe(CohortMember::WAITLISTED);

    $seat->update(['status' => CohortMember::REMOVED, 'removed_at' => now()]);
    expect(app(PromoteFromWaitlist::class)($this->admin, $this->cohort)->id)->toBe($ordinary->id);
});
