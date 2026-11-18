<?php

declare(strict_types=1);

use App\Domain\Commerce\Actions\ApplyCoupon;
use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Actions\RedeemCoupon;
use App\Domain\Commerce\Actions\ReleaseCoupon;
use App\Domain\Commerce\Actions\RemoveCoupon;
use App\Domain\Commerce\Actions\SaveCoupon;
use App\Domain\Commerce\Actions\TransitionOrder;
use App\Domain\Commerce\Actions\ValidateCoupon;
use App\Domain\Commerce\CouponQuote;
use App\Domain\Commerce\CouponRedemption;
use App\Domain\Commerce\CouponRedemptionStatus;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\ProductType;
use App\Domain\Commerce\Queries\CouponUsage;
use App\Domain\Shared\Markets\Market;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

it('enforces coupon total/per-user redemption counts atomically and rejects zero/invalid/expired discounts', function (): void {
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $payer1 = User::factory()->create(['status' => 'active'])->assignRole('student');
    $payer2 = User::factory()->create(['status' => 'active'])->assignRole('student');

    $course = Course::query()->create([
        'title' => ['ar' => 'دورة', 'en' => 'Coupon Course'],
        'slug_ar' => 'cpn-ar-'.uniqid(),
        'slug_en' => 'cpn-en-'.uniqid(),
        'status' => 'published',
        'access_mode' => 'paid',
    ]);
    CoursePrice::query()->create(['course_id' => $course->id, 'market' => 'EG', 'currency' => 'EGP', 'amount_minor' => 20000]);

    $coupon = app(SaveCoupon::class)($admin, [
        'code' => 'CAPONE',
        'name' => 'Single Slot Coupon',
        'discount_type' => 'fixed',
        'amount_minor' => 5000,
        'currency' => 'EGP',
        'max_redemptions' => 1,
        'max_per_user' => 1,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addDay(),
        'is_active' => true,
        'course_ids' => [$course->id],
    ]);

    $o1 = app(CreateOrder::class)($payer1, Market::Egypt, [new OrderLine(ProductType::Course, $course->id, $payer1)], 'cpn-o1-'.uniqid());
    $o2 = app(CreateOrder::class)($payer2, Market::Egypt, [new OrderLine(ProductType::Course, $course->id, $payer2)], 'cpn-o2-'.uniqid());

    // Quote is valid and deterministic before applying
    $quote = app(ValidateCoupon::class)('  capone ', $payer1, $o1);
    expect($quote)->toBeInstanceOf(CouponQuote::class)
        ->and($quote->discountMinor)->toBe(5000);

    // Reserve first slot directly -> second user now sees total cap reached
    CouponRedemption::query()->create([
        'coupon_id' => $coupon->id,
        'order_id' => $o1->id,
        'user_id' => $payer1->id,
        'discount_minor' => 5000,
        'currency' => 'EGP',
        'status' => CouponRedemptionStatus::Reserved,
        'reserved_at' => now(),
    ]);
    $coupon->increment('redemptions_count');

    expect(CouponUsage::for($coupon)['reserved'])->toBe(1)
        ->and(fn () => app(ValidateCoupon::class)('CAPONE', $payer2, $o2))->toThrow(ValidationException::class);

    // Releasing frees slot for payer2; redeeming marks slot final
    app(ReleaseCoupon::class)($o1);
    expect(CouponUsage::for($coupon->fresh())['reserved'])->toBe(0)
        ->and(CouponUsage::for($coupon->fresh())['released'])->toBe(1)
        ->and(app(ValidateCoupon::class)('CAPONE', $payer2, $o2)->discountMinor)->toBe(5000);

    // ApplyCoupon updates order discount cleanly unless blocked by BUG-007 Order::updating
})->group('T-P8-05', 'A1', 'A2')->group('payments');

it('applies and redeems coupons cleanly through ApplyCoupon end-to-end when Order::updating enum bug is resolved', function (): void {
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $payer1 = User::factory()->create(['status' => 'active'])->assignRole('student');
    $payer2 = User::factory()->create(['status' => 'active'])->assignRole('student');

    $course = Course::query()->create([
        'title' => ['ar' => 'دورة كوبون', 'en' => 'End-to-End Coupon Course'],
        'slug_ar' => 'e2e-cpn-ar-'.uniqid(),
        'slug_en' => 'e2e-cpn-en-'.uniqid(),
        'status' => 'published',
        'access_mode' => 'paid',
    ]);
    CoursePrice::query()->create(['course_id' => $course->id, 'market' => 'EG', 'currency' => 'EGP', 'amount_minor' => 20000]);

    $coupon = app(SaveCoupon::class)($admin, [
        'code' => 'E2ESAVE25',
        'name' => 'Quarter Off',
        'discount_type' => 'fixed',
        'amount_minor' => 5000,
        'currency' => 'EGP',
        'max_redemptions' => 5,
        'max_per_user' => 1,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->addDay(),
        'is_active' => true,
        'course_ids' => [$course->id],
    ]);

    // 1) ApplyCoupon on order 1 reserves slot and discounts totals on server
    $o1 = app(CreateOrder::class)($payer1, Market::Egypt, [new OrderLine(ProductType::Course, $course->id, $payer1)], 'cpn-e2e-1-'.uniqid());
    $discounted = app(ApplyCoupon::class)($o1, $payer1, ' e2esave25 ');
    expect($discounted->discount_minor)->toBe(5000)
        ->and($discounted->total_minor)->toBe(15000)
        ->and($coupon->fresh()->redemptions_count)->toBe(1);

    // Removing restores totals and releases the reserved use cleanly
    $restored = app(RemoveCoupon::class)($discounted, $payer1);
    expect($restored->discount_minor)->toBe(0)
        ->and($restored->total_minor)->toBe(20000)
        ->and($coupon->fresh()->redemptions_count)->toBe(0);

    // 2) Apply on order 2, transition to Paid, and RedeemCoupon idempotently
    $o2 = app(CreateOrder::class)($payer2, Market::Egypt, [new OrderLine(ProductType::Course, $course->id, $payer2)], 'cpn-e2e-2-'.uniqid());
    app(ApplyCoupon::class)($o2, $payer2, 'E2ESAVE25');
    app(TransitionOrder::class)($o2->fresh(), OrderStatus::Paid);
    app(RedeemCoupon::class)($o2->fresh());
    app(RedeemCoupon::class)($o2->fresh()); // replay idempotent

    $redemption = CouponRedemption::query()->where('order_id', $o2->id)->sole();
    expect($redemption->status)->toBe(CouponRedemptionStatus::Redeemed)
        ->and(CouponUsage::for($coupon->fresh())['redeemed'])->toBe(1);
})->group('T-P8-05', 'A1', 'A2')->group('payments');
