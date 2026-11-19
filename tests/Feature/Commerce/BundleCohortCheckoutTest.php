<?php

declare(strict_types=1);

use App\Domain\Cohorts\Cohort;
use App\Domain\Cohorts\CohortSeatHold;
use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Actions\StartCheckoutOrder;
use App\Domain\Commerce\Bundle;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderItemAllocation;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\PaymentAttempt;
use App\Domain\Commerce\PaymentAttemptStatus;
use App\Domain\Commerce\ProductType;
use App\Domain\Shared\Markets\Market;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->buyer = User::factory()->create(['status' => 'active']);
    $this->bundle = Bundle::query()->create(['name' => ['en' => 'Creative bundle', 'ar' => 'باقة'], 'is_active' => true]);
    $this->bundle->prices()->create(['market' => 'EG', 'currency' => 'EGP', 'amount_minor' => 1800]);
    $this->courses = [];
    $this->cohorts = [];
    foreach (['live', 'hybrid'] as $format) {
        $course = Course::query()->create([
            'title' => ['en' => $format, 'ar' => $format], 'slug_ar' => $format, 'slug_en' => $format,
            'status' => 'published', 'access_mode' => 'paid', 'format' => $format,
        ]);
        CoursePrice::query()->create(['course_id' => $course->id, 'market' => 'EG', 'currency' => 'EGP', 'amount_minor' => 1000]);
        $this->bundle->items()->create(['course_id' => $course->id, 'sort_order' => count($this->courses)]);
        $this->courses[] = $course;
        $this->cohorts[] = $course->cohorts()->create(['name' => 'First '.$format, 'status' => Cohort::OPEN]);
    }
});

it('holds one explicitly chosen cohort per relevant bundle course and leaves pricing intact', function (): void {
    [$first, $second] = $this->courses;
    $chosen = $first->cohorts()->create(['name' => 'Later cohort', 'status' => Cohort::OPEN]);
    $line = new OrderLine(ProductType::Bundle, $this->bundle->id, $this->buyer, [$first->id => $chosen->id, $second->id => $this->cohorts[1]->id]);

    $order = app(CreateOrder::class)($this->buyer, Market::Egypt, [$line], 'bundle-choice-001');
    expect($order->total_minor)->toBe(1800)
        ->and(OrderItemAllocation::query()->where('order_item_id', $order->items()->sole()->id)->sum('allocated_minor'))->toEqual(1800)
        ->and(CohortSeatHold::query()->pluck('cohort_id')->all())->toEqualCanonicalizing([$chosen->id, $this->cohorts[1]->id]);
})->group('T-P8-07', 'T-P8-12', 'YG-GAP-COH-002');

it('refuses missing or mismatched bundle choices atomically', function (bool $mismatch): void {
    [$first, $second] = $this->courses;
    $selected = [$first->id => $this->cohorts[0]->id];
    if ($mismatch) {
        $selected[$second->id] = $this->cohorts[0]->id;
    }

    $line = new OrderLine(ProductType::Bundle, $this->bundle->id, $this->buyer, $selected);
    expect(fn () => app(CreateOrder::class)($this->buyer, Market::Egypt, [$line], 'bundle-choice-002'))->toThrow(ValidationException::class);
    expect(Order::query()->count())->toBe(0)->and(CohortSeatHold::query()->count())->toBe(0);
})->with([false, true])->group('T-P8-07', 'T-P8-12', 'YG-GAP-COH-002');

it('resumes the same choice and safely replaces an unpaid selection with a new checkout', function (): void {
    [$first, $second] = $this->courses;
    $start = app(StartCheckoutOrder::class);
    $line = new OrderLine(ProductType::Bundle, $this->bundle->id, $this->buyer, [$first->id => $this->cohorts[0]->id, $second->id => $this->cohorts[1]->id]);
    $order = $start($this->buyer, Market::Egypt, $line, 'bundle-choice-003');
    $alternative = $first->cohorts()->create(['name' => 'Alternative', 'status' => Cohort::OPEN]);
    $changed = new OrderLine(ProductType::Bundle, $this->bundle->id, $this->buyer, [$first->id => $alternative->id, $second->id => $this->cohorts[1]->id]);

    expect($start($this->buyer, Market::Egypt, $line, 'bundle-choice-004')->id)->toBe($order->id)
        ->and(fn () => $start($this->buyer, Market::Egypt, $changed, 'bundle-choice-003'))->toThrow(ValidationException::class);
    $replacement = $start($this->buyer, Market::Egypt, $changed, 'bundle-choice-005');
    expect($replacement->id)->not->toBe($order->id)
        ->and($order->refresh()->status)->toBe(OrderStatus::Cancelled)
        ->and(CohortSeatHold::query()->where('status', CohortSeatHold::HELD)->pluck('cohort_id')->all())
        ->toEqualCanonicalizing([$alternative->id, $this->cohorts[1]->id]);
})->group('T-P8-07', 'T-P8-12', 'YG-GAP-COH-002');

it('replaces an unpaid legacy bundle order or resumes one with a payment attempt', function (bool $paymentStarted): void {
    [$first, $second] = $this->courses;
    $first->update(['format' => 'recorded']);
    $second->update(['format' => 'recorded']);
    $start = app(StartCheckoutOrder::class);
    $legacy = $start($this->buyer, Market::Egypt, new OrderLine(ProductType::Bundle, $this->bundle->id, $this->buyer), 'bundle-legacy-001');
    if ($paymentStarted) {
        PaymentAttempt::query()->create([
            'order_id' => $legacy->id, 'active_order_id' => $legacy->id, 'gateway' => 'fake', 'sequence' => 1,
            'reference' => 'bundle-legacy-payment', 'status' => PaymentAttemptStatus::Created,
            'amount_minor' => $legacy->total_minor, 'currency' => $legacy->currency, 'expires_at' => now()->addMinutes(30),
        ]);
    }
    $first->update(['format' => 'live']);
    $second->update(['format' => 'hybrid']);

    $line = new OrderLine(ProductType::Bundle, $this->bundle->id, $this->buyer, [$first->id => $this->cohorts[0]->id, $second->id => $this->cohorts[1]->id]);
    $order = $start($this->buyer, Market::Egypt, $line, 'bundle-legacy-002');

    expect($legacy->refresh()->status)->toBe($paymentStarted ? OrderStatus::Pending : OrderStatus::Cancelled)
        ->and($order->id === $legacy->id)->toBe($paymentStarted)
        ->and(CohortSeatHold::query()->where('order_item_id', $order->items()->sole()->id)->count())->toBe($paymentStarted ? 0 : 2);
})->with([false, true])->group('T-P8-07', 'T-P8-12', 'YG-GAP-COH-002');
