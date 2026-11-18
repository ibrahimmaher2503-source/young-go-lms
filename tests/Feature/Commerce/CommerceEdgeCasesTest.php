<?php

declare(strict_types=1);

use App\Domain\Commerce\Actions\ApplyCoupon;
use App\Domain\Commerce\Actions\ApproveManualPaymentProof;
use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Actions\RejectManualPaymentProof;
use App\Domain\Commerce\Actions\RemoveCoupon;
use App\Domain\Commerce\Actions\SaveCoupon;
use App\Domain\Commerce\Actions\SubmitManualPaymentProof;
use App\Domain\Commerce\Actions\TransitionOrder;
use App\Domain\Commerce\Actions\ValidateCoupon;
use App\Domain\Commerce\Coupon;
use App\Domain\Commerce\CouponRedemption;
use App\Domain\Commerce\CouponRedemptionStatus;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Commerce\ManualPaymentProof;
use App\Domain\Commerce\ManualPaymentStatus;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\ProductType;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Shared\Markets\Market;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function t8CommerceUser(string $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function t8CommerceCourse(string $suffix, int $amountMinor = 12550): Course
{
    $course = Course::query()->create([
        'title' => ['en' => 'Commerce '.$suffix, 'ar' => 'Commerce '.$suffix],
        'slug_ar' => 'commerce-ar-'.$suffix.'-'.uniqid(),
        'slug_en' => 'commerce-en-'.$suffix.'-'.uniqid(),
        'status' => 'published',
        'access_mode' => 'paid',
    ]);
    CoursePrice::query()->create(['course_id' => $course->id, 'market' => 'EG', 'currency' => 'EGP', 'amount_minor' => $amountMinor]);

    return $course;
}

function t8CommerceOrder(User $payer, array $courses): Order
{
    $lines = array_map(static fn (Course $course): OrderLine => new OrderLine(ProductType::Course, (int) $course->id, $payer), $courses);

    return app(CreateOrder::class)($payer, Market::Egypt, $lines, 't8-'.uniqid());
}

function t8CommerceCoupon(User $admin, array $overrides = []): Coupon
{
    return app(SaveCoupon::class)($admin, [
        'code' => 'C'.strtoupper(substr(uniqid(), -7)),
        'name' => 'Launch promo',
        'discount_type' => 'percent',
        'percent' => 10,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->addDay(),
        'is_active' => true,
        'course_ids' => [],
        ...$overrides,
    ]);
}

function t8CommerceProof(User $payer, Order $order, array $overrides = [], ?string $reference = null): ManualPaymentProof
{
    $data = [
        'payer_name' => 'Payer Name',
        'sender_account' => '01001234567890',
        'transfer_reference' => $reference ?? 'TRX '.strtoupper(substr(uniqid(), -8)),
        'amount_minor' => $order->total_minor,
        'transferred_at' => now()->subHour(),
        ...$overrides,
    ];

    return app(SubmitManualPaymentProof::class)($payer, $order, $data, UploadedFile::fake()->image('proof.jpg', 120, 80));
}

function t8CommerceReserve(Coupon $coupon, Order $order, User $payer): CouponRedemption
{
    $redemption = CouponRedemption::query()->create([
        'coupon_id' => $coupon->id,
        'order_id' => $order->id,
        'user_id' => $payer->id,
        'discount_minor' => 100,
        'currency' => $order->currency,
        'status' => CouponRedemptionStatus::Reserved,
        'reserved_at' => now(),
    ]);
    $coupon->increment('redemptions_count');

    return $redemption;
}

it('normalises, audits and course-restricts a coupon', function (): void {
    $admin = t8CommerceUser('admin');
    $course = t8CommerceCourse('restricted');
    $coupon = app(SaveCoupon::class)($admin, [
        'code' => '  promo15 ', 'name' => '  Launch promo ', 'discount_type' => 'percent', 'percent' => 15,
        'max_redemptions' => 100, 'max_per_user' => 1, 'course_ids' => [$course->id, $course->id],
    ]);
    $coupon->refresh();

    expect($coupon->code)->toBe('PROMO15')
        ->and($coupon->name)->toBe('Launch promo')
        ->and($coupon->percent)->toBe(15)
        ->and($coupon->created_by)->toBe($admin->id)
        ->and($coupon->courses()->pluck('courses.id')->all())->toBe([$course->id])
        ->and($coupon->redemptions_count)->toBe(0)
        ->and(Activity::query()->where('event', 'coupon.created')->where('subject_id', $coupon->id)->exists())->toBeTrue();

    expect(fn () => app(SaveCoupon::class)($admin, ['code' => 'promo15', 'name' => 'Duplicate', 'discount_type' => 'percent', 'percent' => 5]))
        ->toThrow(ValidationException::class);
})->group('T-P8-05', 'A1')->group('payments');

it('validates coupon values, dates, caps, courses and permissions', function (): void {
    $admin = t8CommerceUser('admin');
    $course = t8CommerceCourse('validation');
    $save = app(SaveCoupon::class);
    $base = ['code' => 'VALID'.strtoupper(substr(uniqid(), -5)), 'name' => 'Valid', 'discount_type' => 'percent', 'percent' => 10];

    foreach ([
        ['percent' => 0],
        ['percent' => 101],
        ['discount_type' => 'fixed', 'amount_minor' => 0, 'currency' => 'EGP'],
        ['discount_type' => 'fixed', 'amount_minor' => 100, 'currency' => 'USD'],
        ['max_redemptions' => 0],
        ['max_per_user' => 0],
        ['course_ids' => [999999]],
    ] as $invalid) {
        expect(fn () => $save($admin, [...$base, ...$invalid]))->toThrow(ValidationException::class);
    }

    $moment = now();
    expect(fn () => $save($admin, [...$base, 'starts_at' => $moment, 'ends_at' => $moment]))
        ->toThrow(ValidationException::class);
    expect(fn () => $save(t8CommerceUser('student'), $base))->toThrow(AuthorizationException::class);

    $fixed = $save($admin, [...$base, 'discount_type' => 'fixed', 'amount_minor' => 1999, 'currency' => 'egp', 'course_ids' => [$course->id]]);
    expect($fixed->discount_type->value)->toBe('fixed')
        ->and($fixed->amount_minor)->toBe(1999)
        ->and($fixed->currency)->toBe('EGP')
        ->and($fixed->percent)->toBeNull();
})->group('T-P8-05', 'A2')->group('payments');

it('freezes the value after a use but still allows caps, dates and the switch', function (): void {
    $admin = t8CommerceUser('admin');
    $payer = t8CommerceUser('student');
    $course = t8CommerceCourse('frozen');
    $coupon = t8CommerceCoupon($admin);
    $order = t8CommerceOrder($payer, [$course]);
    t8CommerceReserve($coupon, $order, $payer);
    $coupon->refresh();

    expect(fn () => app(SaveCoupon::class)($admin, [
        'code' => 'NEWCODE', 'name' => $coupon->name, 'discount_type' => 'percent', 'percent' => 10,
        'max_redemptions' => 10, 'is_active' => true, 'course_ids' => [],
    ], $coupon))->toThrow(ValidationException::class);
    expect(fn () => app(SaveCoupon::class)($admin, [
        'code' => $coupon->code, 'name' => $coupon->name, 'discount_type' => 'percent', 'percent' => 20,
        'max_redemptions' => 10, 'is_active' => true, 'course_ids' => [],
    ], $coupon))->toThrow(ValidationException::class);
    expect(fn () => app(SaveCoupon::class)($admin, [
        'code' => $coupon->code, 'name' => $coupon->name, 'discount_type' => 'percent', 'percent' => 10,
        'max_redemptions' => 0, 'is_active' => true, 'course_ids' => [],
    ], $coupon))->toThrow(ValidationException::class);

    $updated = app(SaveCoupon::class)($admin, [
        'code' => $coupon->code, 'name' => $coupon->name, 'discount_type' => 'percent', 'percent' => 10,
        'starts_at' => now()->subDays(2), 'ends_at' => now()->addDays(2),
        'max_redemptions' => 1, 'max_per_user' => 2, 'is_active' => false, 'course_ids' => [$course->id],
    ], $coupon);

    expect($updated->max_redemptions)->toBe(1)
        ->and($updated->max_per_user)->toBe(2)
        ->and($updated->is_active)->toBeFalse()
        ->and($updated->courses()->pluck('courses.id')->all())->toBe([$course->id]);
})->group('T-P8-05', 'A2')->group('payments');

it('enforces the total and per-user caps and refuses closed date windows', function (): void {
    $admin = t8CommerceUser('admin');
    $payer = t8CommerceUser('student');
    $other = t8CommerceUser('student');
    $course = t8CommerceCourse('caps');
    $coupon = t8CommerceCoupon($admin, ['max_per_user' => 1]);
    $first = t8CommerceOrder($payer, [$course]);
    t8CommerceReserve($coupon, $first, $payer);

    expect(fn () => app(ValidateCoupon::class)($coupon->code, $payer, $first))->toThrow(ValidationException::class);

    $otherOrder = t8CommerceOrder($other, [$course]);
    $quote = app(ValidateCoupon::class)($coupon->code, $other, $otherOrder);
    expect($quote->discountMinor)->toBe(1255);

    $exhausted = t8CommerceCoupon($admin, ['max_redemptions' => 1]);
    $exhaustedOrder = t8CommerceOrder($other, [$course]);
    t8CommerceReserve($exhausted, $exhaustedOrder, $other);
    $exhausted->refresh();
    expect(fn () => app(ValidateCoupon::class)($exhausted->code, $other, $otherOrder))->toThrow(ValidationException::class);

    foreach ([
        ['is_active' => false],
        ['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(2)],
        ['starts_at' => now()->subDays(2), 'ends_at' => now()->subDay()],
    ] as $window) {
        $closed = t8CommerceCoupon($admin, $window);
        expect(fn () => app(ValidateCoupon::class)($closed->code, $other, $otherOrder))->toThrow(ValidationException::class);
    }
})->group('T-P8-05', 'A2')->group('payments');

it('allocates a discount by eligible line without exceeding any line price', function (): void {
    $admin = t8CommerceUser('admin');
    $payer = t8CommerceUser('student');
    $cheap = t8CommerceCourse('cheap', 333);
    $expensive = t8CommerceCourse('expensive', 667);
    $order = t8CommerceOrder($payer, [$cheap, $expensive]);
    $coupon = t8CommerceCoupon($admin, ['percent' => 10]);
    $items = $order->items()->orderBy('id')->get();

    $quote = app(ValidateCoupon::class)($coupon->code, $payer, $order);

    expect($quote->discountMinor)->toBe(100)
        ->and($quote->allocations[$items[0]->id])->toBe(34)
        ->and($quote->allocations[$items[1]->id])->toBe(66);

    $restricted = t8CommerceCoupon($admin, ['discount_type' => 'fixed', 'amount_minor' => 5000, 'currency' => 'EGP', 'course_ids' => [$cheap->id]]);
    $restrictedOrder = t8CommerceOrder($payer, [$cheap, $expensive]);
    $restrictedItems = $restrictedOrder->items()->orderBy('id')->get();
    $restrictedQuote = app(ValidateCoupon::class)($restricted->code, $payer, $restrictedOrder);

    expect($restrictedQuote->discountMinor)->toBe(333)
        ->and($restrictedQuote->allocations[$restrictedItems[0]->id])->toBe(333)
        ->and($restrictedQuote->allocations)->not->toHaveKey($restrictedItems[1]->id);

    $sar = t8CommerceCoupon($admin, ['discount_type' => 'fixed', 'amount_minor' => 100, 'currency' => 'SAR']);
    expect(fn () => app(ValidateCoupon::class)($sar->code, $payer, $restrictedOrder))->toThrow(ValidationException::class);
})->group('T-P8-05', 'A1')->group('payments');

it('restores the order and frees the use when a coupon is removed or its order is cancelled', function (): void {
    $admin = t8CommerceUser('admin');
    $payer = t8CommerceUser('student');
    $course = t8CommerceCourse('release');
    $coupon = t8CommerceCoupon($admin);

    $removedOrder = t8CommerceOrder($payer, [$course]);
    app(ApplyCoupon::class)($removedOrder, $payer, $coupon->code);
    $removed = app(RemoveCoupon::class)($removedOrder, $payer);

    expect($removed->discount_minor)->toBe(0)
        ->and($removed->total_minor)->toBe($removed->subtotal_minor)
        ->and($removed->items()->where('discount_minor', '!=', 0)->exists())->toBeFalse()
        ->and($coupon->refresh()->redemptions_count)->toBe(0)
        ->and(CouponRedemption::query()->count())->toBe(0);
})->group('T-P8-05', 'A1')->group('payments');

it('stores a transfer proof privately with a normalized reference and an SLA', function (): void {
    Storage::fake('local');
    $payer = t8CommerceUser('student');
    $order = t8CommerceOrder($payer, [t8CommerceCourse('proof')]);

    $proof = t8CommerceProof($payer, $order, [], ' trx 12345 ');

    expect($proof->transfer_reference)->toBe('TRX12345')
        ->and($proof->reference_key)->toBe('TRX12345')
        ->and($proof->active_order_id)->toBe($order->id)
        ->and($proof->status)->toBe(ManualPaymentStatus::Pending)
        ->and($proof->amount_minor)->toBe($order->total_minor)
        ->and($proof->currency)->toBe('EGP')
        ->and($proof->file_path)->toStartWith('manual-payments/')
        ->and(Storage::disk('local')->exists($proof->file_path))->toBeTrue()
        ->and($proof->toArray())->not->toHaveKey('file_path')
        ->and($proof->toArray())->not->toHaveKey('file_sha256')
        ->and(Activity::query()->where('event', 'manual_payment.submitted')->where('subject_id', $proof->id)->exists())->toBeTrue()
        ->and($proof->sla_due_at->between(now()->addHours(24)->subSeconds(5), now()->addHours(24)->addSeconds(5)))->toBeTrue();
})->group('T-P8-04', 'A1', 'YG-GAP-PAY-004')->group('payments');

it('refuses a proof with bad values, one pending proof per order and a reused reference', function (): void {
    Storage::fake('local');
    $payer = t8CommerceUser('student');
    $order = t8CommerceOrder($payer, [t8CommerceCourse('proof-invalid')]);
    $submit = app(SubmitManualPaymentProof::class);
    $file = UploadedFile::fake()->image('proof.jpg', 100, 100);
    $base = ['payer_name' => 'Payer Name', 'sender_account' => '01001234567890', 'transfer_reference' => 'TRX12345', 'amount_minor' => $order->total_minor, 'transferred_at' => now()->subHour()];
    $accepted = [];

    foreach ([
        'bad reference' => ['transfer_reference' => '!!'],
        'zero amount' => ['amount_minor' => 0],
        'short name' => ['payer_name' => 'x'],
        'short sender' => ['sender_account' => '123'],
        'control sender' => ['sender_account' => "123\n45"],
        'future transfer' => ['transferred_at' => now()->addMinutes(10)],
        'old transfer' => ['transferred_at' => now()->subDays(31)],
    ] as $label => $invalid) {
        try {
            $submit($payer, $order, [...$base, ...$invalid], $file);
            $accepted[] = $label;
        } catch (ValidationException) {
            // Expected.
        }
    }
    expect($accepted)->toBe([]);

    expect(fn () => $submit($payer, $order, $base, UploadedFile::fake()->createWithContent('proof.txt', 'not a proof')))
        ->toThrow(ValidationException::class);
    expect(Storage::disk('local')->allFiles())->toBe([]);

    $proof = t8CommerceProof($payer, $order, [], 'FIRST1234');
    expect(fn () => $submit($payer, $order, [...$base, 'transfer_reference' => 'SECOND1234'], $file))->toThrow(ValidationException::class);

    $otherOrder = t8CommerceOrder($payer, [t8CommerceCourse('proof-other')]);
    expect(fn () => $submit($payer, $otherOrder, [...$base, 'transfer_reference' => 'first 1234'], $file))->toThrow(ValidationException::class);

    app(RejectManualPaymentProof::class)(t8CommerceUser('admin'), $proof, 'Reference is not clear.');
    $reused = t8CommerceProof($payer, $otherOrder, [], 'FIRST1234');

    expect($reused->transfer_reference)->toBe('FIRST1234')
        ->and($proof->refresh()->status)->toBe(ManualPaymentStatus::Rejected);
})->group('T-P8-04', 'A2', 'YG-GAP-PAY-004')->group('payments');

it('approves an exact payment once, grants access and redeems the coupon', function (): void {
    Storage::fake('local');
    $admin = t8CommerceUser('admin');
    $payer = t8CommerceUser('student');
    $course = t8CommerceCourse('approved');
    $coupon = t8CommerceCoupon($admin);
    $order = t8CommerceOrder($payer, [$course]);
    app(ApplyCoupon::class)($order, $payer, $coupon->code);
    $order->refresh();
    $proof = t8CommerceProof($payer, $order);

    $approved = app(ApproveManualPaymentProof::class)($admin, $proof);

    expect($approved->status)->toBe(ManualPaymentStatus::Approved)
        ->and($approved->reviewed_by)->toBe($admin->id)
        ->and($approved->active_order_id)->toBeNull()
        ->and($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->paid_at)->not->toBeNull()
        ->and(CouponRedemption::query()->where('order_id', $order->id)->sole()->status)->toBe(CouponRedemptionStatus::Redeemed)
        ->and(Entitlement::query()->where('learner_id', $payer->id)->where('course_id', $course->id)->where('source_type', EntitlementSource::ManualPayment->value)->exists())->toBeTrue()
        ->and(Activity::query()->where('event', 'manual_payment.approved')->count())->toBe(1)
        ->and(Activity::query()->where('event', 'entitlement.granted')->count())->toBe(1);

    app(ApproveManualPaymentProof::class)($admin, $approved);

    expect(Activity::query()->where('event', 'manual_payment.approved')->count())->toBe(1)
        ->and(Entitlement::query()->count())->toBe(1);
})->group('T-P8-04', 'A1', 'YG-GAP-PAY-004')->group('payments');

it('requires a note when the transferred amount does not match and rejects the wrong actor', function (): void {
    Storage::fake('local');
    $admin = t8CommerceUser('admin');
    $payer = t8CommerceUser('student');
    $order = t8CommerceOrder($payer, [t8CommerceCourse('mismatch')]);
    $proof = t8CommerceProof($payer, $order, ['amount_minor' => $order->total_minor - 1]);

    expect(fn () => app(ApproveManualPaymentProof::class)(t8CommerceUser('student'), $proof))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(ApproveManualPaymentProof::class)($admin, $proof))
        ->toThrow(ValidationException::class);
    expect($order->refresh()->status)->toBe(OrderStatus::Pending)
        ->and($proof->refresh()->status)->toBe(ManualPaymentStatus::Pending);

    $approved = app(ApproveManualPaymentProof::class)($admin, $proof, 'Bank fees deducted.');

    expect($approved->status)->toBe(ManualPaymentStatus::Approved)
        ->and($approved->review_note)->toBe('Bank fees deducted.')
        ->and($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and(Activity::query()->where('event', 'manual_payment.approved')->sole()->properties['after']['amount_mismatch'])->toBeTrue();
})->group('T-P8-04', 'A2', 'YG-GAP-PAY-004')->group('payments');

it('rejects a pending proof with a reason and never rejects an approved one', function (): void {
    Storage::fake('local');
    $admin = t8CommerceUser('admin');
    $payer = t8CommerceUser('student');
    $order = t8CommerceOrder($payer, [t8CommerceCourse('rejected')]);
    $proof = t8CommerceProof($payer, $order);

    expect(fn () => app(RejectManualPaymentProof::class)($admin, $proof, 'no'))
        ->toThrow(ValidationException::class);
    expect(fn () => app(RejectManualPaymentProof::class)(t8CommerceUser('student'), $proof, 'Not my proof.'))
        ->toThrow(AuthorizationException::class);

    $rejected = app(RejectManualPaymentProof::class)($admin, $proof, 'The sender name does not match.');

    expect($rejected->status)->toBe(ManualPaymentStatus::Rejected)
        ->and($rejected->reference_key)->toBeNull()
        ->and($rejected->active_order_id)->toBeNull()
        ->and($order->refresh()->status)->toBe(OrderStatus::Pending)
        ->and(Storage::disk('local')->exists($rejected->file_path))->toBeTrue()
        ->and(CouponRedemption::query()->count())->toBe(0);

    $again = app(RejectManualPaymentProof::class)($admin, $rejected, 'Repeating the same decision.');
    expect($again->status)->toBe(ManualPaymentStatus::Rejected)
        ->and(Activity::query()->where('event', 'manual_payment.rejected')->count())->toBe(1);

    $approved = t8CommerceProof($payer, $order, [], 'THIRD1234');
    $approved->forceFill(['status' => ManualPaymentStatus::Approved])->save();
    expect(fn () => app(RejectManualPaymentProof::class)($admin, $approved, 'Trying to reject an approved proof.'))
        ->toThrow(ValidationException::class);
})->group('T-P8-04', 'A2', 'YG-GAP-PAY-004')->group('payments');

it('moves an order through its status machine without a framework error', function (): void {
    $payer = t8CommerceUser('student');
    $order = t8CommerceOrder($payer, [t8CommerceCourse('transition')]);

    app(TransitionOrder::class)($order, OrderStatus::Cancelled);

    expect($order->refresh()->status)->toBe(OrderStatus::Cancelled);
})->group('T-P8-03', 'A1')->group('payments');
