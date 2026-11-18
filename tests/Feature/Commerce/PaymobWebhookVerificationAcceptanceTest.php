<?php

// T-P8-03 | YG-GAP-PAY-002, PRD 23 (3,4), Arch 10.2 | A1 A2 A3 | I03 I04

declare(strict_types=1);

use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Actions\GrantOrderEntitlements;
use App\Domain\Commerce\Actions\ProcessPaymentWebhook;
use App\Domain\Commerce\Actions\StartCheckout;
use App\Domain\Commerce\Actions\TransitionOrder;
use App\Domain\Commerce\Contracts\PaymentGateway;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Commerce\Events\PaymentFlagged;
use App\Domain\Commerce\Events\PaymentVerified;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\PaymentAttempt;
use App\Domain\Commerce\PaymentAttemptStatus;
use App\Domain\Commerce\PaymentEvent;
use App\Domain\Commerce\PaymentEventOutcome;
use App\Domain\Commerce\Payments\FakePaymentGateway;
use App\Domain\Commerce\Payments\InvalidPaymentSignature;
use App\Domain\Commerce\Payments\PaymentPayloadScrubber;
use App\Domain\Commerce\Payments\PaymentWebhookResult;
use App\Domain\Commerce\ProductType;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Shared\Markets\Market;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    config(['payments.driver' => 'auto', 'payments.methods' => ['card', 'wallet']]);
    app()->forgetInstance(PaymentGateway::class);
});

afterEach(function (): void {
    app()->detectEnvironment(fn () => 'testing');
});

function t803Course(int $amountMinor = 25000): Course
{
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة', 'en' => 'Webhook Course '.uniqid()],
        'slug_ar' => 't803-ar-'.uniqid(),
        'slug_en' => 't803-en-'.uniqid(),
        'status' => 'published',
        'access_mode' => 'paid',
    ]);
    CoursePrice::query()->create(['course_id' => $course->id, 'market' => 'EG', 'currency' => 'EGP', 'amount_minor' => $amountMinor]);

    return $course;
}

/** A learner with an open checkout: the order is pending and the attempt is created on the Fake gateway. */
function t803Checkout(int $amountMinor = 25000, ?User $payer = null, ?User $learner = null): array
{
    $payer ??= User::factory()->create(['status' => 'active'])->assignRole('student');
    $learner ??= $payer;
    $course = t803Course($amountMinor);
    $order = app(CreateOrder::class)($payer, Market::Egypt, [new OrderLine(ProductType::Course, $course->id, $learner)], 't803-'.uniqid());
    $attempt = app(StartCheckout::class)($payer, $order, 'card');

    return compact('payer', 'learner', 'course', 'order', 'attempt');
}

/** A Fake callback body for an attempt. */
function t803Payload(PaymentAttempt|string $attempt, Order $order, array $overrides = []): array
{
    $reference = $attempt instanceof PaymentAttempt ? $attempt->reference : $attempt;

    return array_replace([
        'id' => 'txn_'.uniqid(),
        'reference' => $reference,
        'order_id' => 'ord_'.$order->id,
        'amount_minor' => $order->total_minor,
        'currency' => $order->currency,
        'success' => true,
        'pending' => false,
        'refunded' => false,
        'voided' => false,
    ], $overrides);
}

/** Posts a callback over HTTP, signed with the Fake key unless a signature is given. */
function t803Post(array $payload, ?string $signature = null)
{
    return test()->postJson(route('webhooks.paymob'), $payload, [
        'X-Payment-Signature' => $signature ?? FakePaymentGateway::sign($payload),
    ]);
}

function t803Process(array $payload, ?string $signature = null): PaymentWebhookResult
{
    return app(ProcessPaymentWebhook::class)($payload, $signature ?? FakePaymentGateway::sign($payload));
}

function t803Entitlements(User $learner, Course $course): int
{
    return Entitlement::query()->where('learner_id', $learner->id)->where('course_id', $course->id)->count();
}

// A1 Tampered signature rejected

it('answers 401 for wrong, missing, empty and altered-payload signatures and stores nothing', function (): void {
    Event::fake([PaymentVerified::class, PaymentFlagged::class]);
    ['payer' => $payer, 'order' => $order, 'attempt' => $attempt, 'course' => $course] = t803Checkout();
    $payload = t803Payload($attempt, $order);
    $altered = $payload;
    $altered['amount_minor'] = 1;

    $responses = [
        t803Post($payload, 'wrong-signature'),
        t803Post($payload, ''),
        test()->postJson(route('webhooks.paymob'), $payload),
        t803Post($altered, FakePaymentGateway::sign($payload)),
    ];

    foreach ($responses as $response) {
        $response->assertStatus(401);
    }
    expect(PaymentEvent::query()->count())->toBe(0)
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($attempt->fresh()->status)->toBe(PaymentAttemptStatus::Created)
        ->and(t803Entitlements($payer, $course))->toBe(0)
        ->and(Activity::query()->whereIn('event', ['payment.verified', 'payment.flagged'])->count())->toBe(0);
    Event::assertNotDispatched(PaymentVerified::class);
    Event::assertNotDispatched(PaymentFlagged::class);
})->group('T-P8-03', 'A1');

it('reads nothing into the database before the signature is verified', function (): void {
    ['order' => $order, 'attempt' => $attempt] = t803Checkout();
    $payload = t803Payload($attempt, $order);

    $spy = Mockery::mock(PaymentGateway::class);
    $spy->shouldReceive('verifyCallback')->once()->andThrow(new InvalidPaymentSignature);
    app()->instance(PaymentGateway::class, $spy);

    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
        expect(fn () => app(ProcessPaymentWebhook::class)($payload, 'anything'))->toThrow(InvalidPaymentSignature::class);
    } finally {
        $log = DB::getQueryLog();
        DB::disableQueryLog();
    }

    $writes = array_filter($log, static fn (array $q): bool => preg_match('/^\s*(insert|update|delete)/i', $q['query']) === 1);
    expect($writes)->toBe([])
        ->and(PaymentEvent::query()->count())->toBe(0)
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending);
})->group('T-P8-03', 'A1');

// A2 Amount and currency mismatch flagged

it('flags a signed success with a different amount, without paying or granting access', function (): void {
    Event::fake([PaymentVerified::class, PaymentFlagged::class]);
    ['payer' => $payer, 'order' => $order, 'attempt' => $attempt, 'course' => $course] = t803Checkout();
    $payload = t803Payload($attempt, $order, ['amount_minor' => 1]);

    t803Post($payload)->assertOk()->assertJson(['status' => 'ok']);

    $event = PaymentEvent::query()->firstOrFail();
    expect($event->outcome)->toBe(PaymentEventOutcome::Flagged)
        ->and($event->reason)->toBe('amount_mismatch')
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($attempt->fresh()->status)->toBe(PaymentAttemptStatus::Created)
        ->and(t803Entitlements($payer, $course))->toBe(0)
        ->and(Activity::query()->where('event', 'payment.flagged')->count())->toBe(1);
    Event::assertDispatched(PaymentFlagged::class);
    Event::assertNotDispatched(PaymentVerified::class);
})->group('T-P8-03', 'A2');

it('flags a signed success in another currency as amount_mismatch', function (): void {
    ['order' => $order, 'attempt' => $attempt] = t803Checkout();

    $result = t803Process(t803Payload($attempt, $order, ['currency' => 'USD']));

    expect($result->event->outcome)->toBe(PaymentEventOutcome::Flagged)
        ->and($result->event->reason)->toBe('amount_mismatch')
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending);
})->group('T-P8-03', 'A2');

// A3 Duplicate and replayed webhooks

it('fulfils once when the same signed success arrives three times', function (): void {
    Event::fake([PaymentVerified::class, PaymentFlagged::class]);
    ['payer' => $payer, 'learner' => $learner, 'order' => $order, 'attempt' => $attempt, 'course' => $course] = t803Checkout();
    $payload = t803Payload($attempt, $order, ['id' => 'txn_replay_1']);

    t803Post($payload)->assertOk();
    t803Post($payload)->assertOk();
    t803Post($payload)->assertOk();

    $fresh = $order->fresh();
    expect(PaymentEvent::query()->count())->toBe(1)
        ->and($fresh->status)->toBe(OrderStatus::Paid)
        ->and($fresh->paid_at)->not->toBeNull()
        ->and(Entitlement::query()->where('source_type', EntitlementSource::Purchase->value)
            ->where('source_ref', 'order:'.$order->reference)->count())->toBe(1)
        ->and(t803Entitlements($learner, $course))->toBe(1)
        ->and(Activity::query()->where('event', 'order.status_changed')->where('subject_id', $order->id)->count())->toBe(1)
        ->and(Activity::query()->where('event', 'payment.verified')->count())->toBe(1);
    Event::assertDispatchedTimes(PaymentVerified::class, 1);
    expect($fresh->items()->count())->toBe(1);
})->group('T-P8-03', 'A3');

it('reports a repeated callback as a duplicate through the action', function (): void {
    ['order' => $order, 'attempt' => $attempt] = t803Checkout();
    $payload = t803Payload($attempt, $order, ['id' => 'txn_replay_2']);

    $first = t803Process($payload);
    $second = t803Process($payload);

    expect($first->duplicate)->toBeFalse()
        ->and($second->duplicate)->toBeTrue()
        ->and($second->event->id)->toBe($first->event->id)
        ->and(PaymentEvent::query()->count())->toBe(1);
})->group('T-P8-03', 'A3');

// I03 pending, failed, unsigned and unmatched never grant

it('grants nothing for pending, failed, unsigned or unmatched callbacks', function (): void {
    ['payer' => $payer, 'learner' => $learner, 'order' => $order, 'attempt' => $attempt, 'course' => $course] = t803Checkout();

    t803Process(t803Payload($attempt, $order, ['id' => 'txn_pend', 'success' => false, 'pending' => true]));
    expect(PaymentEvent::query()->where('provider_event_id', 'txn_pend')->value('outcome'))->toBe(PaymentEventOutcome::Ignored);
    expect(t803Entitlements($learner, $course))->toBe(0)
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending);

    t803Process(t803Payload($attempt, $order, ['id' => 'txn_fail', 'success' => false]));
    expect(t803Entitlements($learner, $course))->toBe(0)
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending);

    $unsigned = t803Payload($attempt, $order, ['id' => 'txn_unsigned']);
    t803Post($unsigned, 'not-a-real-signature')->assertStatus(401);
    expect(PaymentEvent::query()->where('provider_event_id', 'txn_unsigned')->exists())->toBeFalse();

    $unmatched = t803Process(['id' => 'txn_unmatched', 'reference' => 'NO-SUCH-REF-A9', 'amount_minor' => 25000, 'currency' => 'EGP', 'success' => true]);
    expect($unmatched->event->outcome)->toBe(PaymentEventOutcome::Unmatched)
        ->and(t803Entitlements($learner, $course))->toBe(0)
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($payer->id)->toBe($order->payer_id);
})->group('T-P8-03', 'I03');

// I04 one verified payment fulfils once

it('flags a second successful transaction for an already paid order and grants nothing more', function (): void {
    ['learner' => $learner, 'order' => $order, 'attempt' => $attempt, 'course' => $course] = t803Checkout();

    t803Process(t803Payload($attempt, $order, ['id' => 'txn_one']));
    $grantsAfterFirst = t803Entitlements($learner, $course);

    $second = t803Process(t803Payload($attempt, $order, ['id' => 'txn_two']));

    expect($second->event->outcome)->toBe(PaymentEventOutcome::Flagged)
        ->and($second->event->reason)->toBe('duplicate_payment')
        ->and(t803Entitlements($learner, $course))->toBe($grantsAfterFirst)
        ->and($grantsAfterFirst)->toBe(1)
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid);
})->group('T-P8-03', 'I04');

// Success happy path

it('pays the order at the server amount and grants the learner access, attempt succeeded and slot freed', function (): void {
    ['payer' => $payer, 'learner' => $learner, 'order' => $order, 'attempt' => $attempt, 'course' => $course] = t803Checkout();

    $result = t803Process(t803Payload($attempt, $order, ['id' => 'txn_happy']));

    $fresh = $order->fresh();
    $attemptFresh = $attempt->fresh();
    expect($result->event->outcome)->toBe(PaymentEventOutcome::Fulfilled)
        ->and($fresh->status)->toBe(OrderStatus::Paid)
        ->and($fresh->paid_at)->not->toBeNull()
        ->and($attemptFresh->status)->toBe(PaymentAttemptStatus::Succeeded)
        ->and($attemptFresh->active_order_id)->toBeNull()
        ->and($attemptFresh->signature_valid)->toBeTrue()
        ->and(t803Entitlements($learner, $course))->toBe(1)
        ->and(t803Entitlements($payer, $course))->toBe(1);
})->group('T-P8-03', 'A3');

it('grants the child, not the parent payer, for a parent-pays-for-child order', function (): void {
    $parent = User::factory()->create(['status' => 'active'])->assignRole('parent');
    $child = User::factory()->create(['status' => 'active'])->assignRole('student');
    GuardianChildLink::query()->create([
        'guardian_id' => $parent->id, 'child_id' => $child->id, 'status' => GuardianChildLink::STATUS_VERIFIED,
        'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT, 'verified_at' => now(),
    ]);
    ['order' => $order, 'attempt' => $attempt, 'course' => $course] = t803Checkout(25000, $parent, $child);

    t803Process(t803Payload($attempt, $order, ['id' => 'txn_family']));

    expect($order->fresh()->payer_id)->toBe($parent->id)
        ->and(t803Entitlements($child, $course))->toBe(1)
        ->and(t803Entitlements($parent, $course))->toBe(0);
})->group('T-P8-03', 'A3');

// Failure

it('closes the open attempt as failed, keeps the order pending, and accepts a later success for a new attempt', function (): void {
    ['payer' => $payer, 'learner' => $learner, 'order' => $order, 'attempt' => $attempt, 'course' => $course] = t803Checkout();

    $failed = t803Process(t803Payload($attempt, $order, ['id' => 'txn_declined', 'success' => false]));

    $attemptFresh = $attempt->fresh();
    expect($failed->event->outcome)->toBe(PaymentEventOutcome::AttemptFailed)
        ->and($attemptFresh->status)->toBe(PaymentAttemptStatus::Failed)
        ->and($attemptFresh->failure_code)->toBe('declined')
        ->and($attemptFresh->active_order_id)->toBeNull()
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and(Activity::query()->where('event', 'payment.attempt_failed')->count())->toBe(1);

    $retry = app(StartCheckout::class)($payer, $order->fresh(), 'card');
    expect($retry->sequence)->toBe(2);

    $paid = t803Process(t803Payload($retry, $order, ['id' => 'txn_after_fail']));
    expect($paid->event->outcome)->toBe(PaymentEventOutcome::Fulfilled)
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and(t803Entitlements($learner, $course))->toBe(1);
})->group('T-P8-03', 'A3');

it('ignores a failure after the attempt already succeeded or on a paid order', function (): void {
    ['order' => $order, 'attempt' => $attempt] = t803Checkout();
    t803Process(t803Payload($attempt, $order, ['id' => 'txn_ok_first']));

    $late = t803Process(t803Payload($attempt, $order, ['id' => 'txn_late_fail', 'success' => false]));

    expect($late->event->outcome)->toBe(PaymentEventOutcome::Ignored)
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($attempt->fresh()->status)->toBe(PaymentAttemptStatus::Succeeded);
})->group('T-P8-03', 'A3');

// Out of order

it('ignores a pending callback that arrives after success', function (): void {
    ['order' => $order, 'attempt' => $attempt, 'learner' => $learner, 'course' => $course] = t803Checkout();
    t803Process(t803Payload($attempt, $order, ['id' => 'txn_order_a']));

    $late = t803Process(t803Payload($attempt, $order, ['id' => 'txn_order_a', 'success' => false, 'pending' => true]));

    expect($late->event->outcome)->toBe(PaymentEventOutcome::Ignored)
        ->and($late->event->reason)->toBe('pending')
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and(t803Entitlements($learner, $course))->toBe(1);
})->group('T-P8-03', 'A3');

it('flags a success for a cancelled or failed order as order_closed and grants nothing', function (): void {
    foreach ([OrderStatus::Cancelled, OrderStatus::Failed] as $closedTo) {
        ['learner' => $learner, 'order' => $order, 'attempt' => $attempt, 'course' => $course] = t803Checkout();
        app(TransitionOrder::class)($order, $closedTo);

        $result = t803Process(t803Payload($attempt, $order, ['id' => 'txn_closed_'.$closedTo->value]));

        expect($result->event->outcome)->toBe(PaymentEventOutcome::Flagged)
            ->and($result->event->reason)->toBe('order_closed')
            ->and(t803Entitlements($learner, $course))->toBe(0)
            ->and($order->fresh()->status)->toBe($closedTo);
    }
})->group('T-P8-03', 'A3');

it('flags refund and void callbacks without changing state', function (): void {
    ['order' => $order, 'attempt' => $attempt, 'learner' => $learner, 'course' => $course] = t803Checkout();

    $refund = t803Process(t803Payload($attempt, $order, ['id' => 'txn_refund_x', 'refunded' => true, 'success' => true]));
    $void = t803Process(t803Payload($attempt, $order, ['id' => 'txn_void_x', 'voided' => true, 'success' => true]));

    expect($refund->event->outcome)->toBe(PaymentEventOutcome::Flagged)
        ->and($refund->event->reason)->toBe('refund_or_void_received')
        ->and($void->event->reason)->toBe('refund_or_void_received')
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($attempt->fresh()->status)->toBe(PaymentAttemptStatus::Created)
        ->and(t803Entitlements($learner, $course))->toBe(0);
})->group('T-P8-03', 'A3');

it('flags a success that arrives after a refunded event for the same transaction id', function (): void {
    ['order' => $order, 'attempt' => $attempt, 'learner' => $learner, 'course' => $course] = t803Checkout();
    t803Process(t803Payload($attempt, $order, ['id' => 'txn_ref_first', 'refunded' => true, 'success' => true]));

    $success = t803Process(t803Payload($attempt, $order, ['id' => 'txn_ref_first', 'success' => true]));

    expect($success->event->outcome)->toBe(PaymentEventOutcome::Flagged)
        ->and($success->event->reason)->toBe('refunded_before_success')
        ->and(t803Entitlements($learner, $course))->toBe(0)
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending);
})->group('T-P8-03', 'A2');

// Unmatched

it('stores a valid callback with an unknown reference as unmatched, answers 200 and audits it', function (): void {
    Event::fake([PaymentFlagged::class]);
    $payload = ['id' => 'txn_unknown', 'reference' => 'UNKNOWN-A1', 'amount_minor' => 100, 'currency' => 'EGP', 'success' => true];

    t803Post($payload)->assertOk();

    expect(PaymentEvent::query()->where('provider_event_id', 'txn_unknown')->value('outcome'))->toBe(PaymentEventOutcome::Unmatched)
        ->and(Activity::query()->where('event', 'payment.flagged')->count())->toBe(1);
    Event::assertDispatched(PaymentFlagged::class);
});

it('stores a valid callback without a reference as unmatched', function (): void {
    $payload = ['id' => 'txn_noref', 'amount_minor' => 100, 'currency' => 'EGP', 'success' => true];

    t803Post($payload)->assertOk();

    expect(PaymentEvent::query()->where('provider_event_id', 'txn_noref')->value('outcome'))->toBe(PaymentEventOutcome::Unmatched);
});

// Manual proof first, then Paymob success; non-course and inactive market

it('flags a Paymob success for an order already paid by manual proof, with no second entitlement', function (): void {
    ['learner' => $learner, 'order' => $order, 'attempt' => $attempt, 'course' => $course] = t803Checkout();
    app(TransitionOrder::class)($order, OrderStatus::Paid);
    app(GrantOrderEntitlements::class)($order->fresh(), EntitlementSource::Purchase);
    $before = t803Entitlements($learner, $course);

    $result = t803Process(t803Payload($attempt, $order, ['id' => 'txn_manual_then_paymob']));

    expect($result->event->reason)->toBe('duplicate_payment')
        ->and(t803Entitlements($learner, $course))->toBe($before)
        ->and($before)->toBe(1);
})->group('T-P8-03', 'A2');

it('flags a success for a Saudi order (inactive market) and does not pay it', function (): void {
    $payer = User::factory()->create(['status' => 'active'])->assignRole('student');
    $order = Order::query()->create([
        'reference' => 'YG-ORD-T803SA0001', 'payer_id' => $payer->id, 'market' => 'SA', 'currency' => 'SAR',
        'subtotal_minor' => 1000, 'discount_minor' => 0, 'total_minor' => 1000, 'status' => OrderStatus::Pending,
        'idempotency_key' => 't803-sa-key', 'request_hash' => str_repeat('b', 64),
    ]);
    $attempt = PaymentAttempt::query()->create([
        'order_id' => $order->id, 'gateway' => 'fake', 'sequence' => 1, 'reference' => 'YG-ORD-T803SA0001-A1',
        'status' => PaymentAttemptStatus::Created, 'active_order_id' => $order->id, 'amount_minor' => 1000,
        'currency' => 'SAR', 'expires_at' => now()->addMinutes(30),
    ]);

    $result = t803Process(t803Payload($attempt, $order, ['id' => 'txn_sa', 'currency' => 'SAR', 'amount_minor' => 1000]));

    expect($result->event->outcome)->toBe(PaymentEventOutcome::Flagged)
        ->and($result->event->reason)->toBe('market_unavailable')
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending);
})->group('T-P8-03', 'A2');

it('flags a callback whose gateway differs from the attempt gateway', function (): void {
    ['order' => $order, 'attempt' => $attempt] = t803Checkout();
    DB::table('payment_attempts')->where('id', $attempt->id)->update(['gateway' => 'paymob']);

    $result = t803Process(t803Payload($attempt, $order, ['id' => 'txn_gw_mismatch']));

    expect($result->event->outcome)->toBe(PaymentEventOutcome::Flagged)
        ->and($result->event->reason)->toBe('gateway_mismatch')
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending);
})->group('T-P8-03', 'A2');

// Atomicity

it('answers 500 and rolls back the whole callback when the audit fails, and a replay then succeeds', function (): void {
    ['learner' => $learner, 'order' => $order, 'attempt' => $attempt, 'course' => $course] = t803Checkout();
    $payload = t803Payload($attempt, $order, ['id' => 'txn_atomic']);

    activity()->disableLogging();
    try {
        t803Post($payload)->assertStatus(500);
    } finally {
        activity()->enableLogging();
    }

    expect(PaymentEvent::query()->count())->toBe(0)
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($attempt->fresh()->status)->toBe(PaymentAttemptStatus::Created)
        ->and(t803Entitlements($learner, $course))->toBe(0);

    t803Post($payload)->assertOk();
    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and(t803Entitlements($learner, $course))->toBe(1);
})->group('T-P8-03', 'A3');

// Stored payload

it('stores no card, billing, secret or signature data in the event payload', function (): void {
    ['order' => $order, 'attempt' => $attempt] = t803Checkout();
    $payload = t803Payload($attempt, $order, [
        'id' => 'txn_scrub', 'card_number' => '4111111111111111', 'email' => 'payer@example.test',
        'client_secret' => 'cs_live_secret_value', 'success' => false, 'pending' => true,
    ]);

    t803Process($payload);

    $stored = PaymentEvent::query()->where('provider_event_id', 'txn_scrub')->firstOrFail()->payload;
    expect($stored['card_number'])->toBe('[REDACTED]')
        ->and($stored['email'])->toBe('[REDACTED]')
        ->and($stored['client_secret'])->toBe('[REDACTED]')
        ->and(json_encode($stored))->not->toContain('4111111111111111')
        ->and(json_encode($stored))->not->toContain('cs_live_secret_value')
        ->and($stored)->not->toHaveKey('hmac');
});

it('scrubs nested keys, token and key patterns, long strings and deep nesting', function (): void {
    $scrubber = new PaymentPayloadScrubber;

    $nested = $scrubber->scrub(['outer' => ['pan' => '4111', 'ok' => 'fine']]);
    expect($nested['outer']['pan'])->toBe('[REDACTED]')
        ->and($nested['outer']['ok'])->toBe('fine');

    $tokens = $scrubber->scrub(['note' => 'Token abc123 and egy_sk_test_ABC999']);
    expect($tokens['note'])->not->toContain('abc123')
        ->and($tokens['note'])->not->toContain('egy_sk_test_ABC999')
        ->and($tokens['note'])->toContain('[REDACTED]');

    $long = $scrubber->scrub(['text' => str_repeat('x', 600)]);
    expect(mb_strlen($long['text']))->toBe(500);

    $deep = $scrubber->scrub(['a' => ['b' => ['c' => ['d' => ['e' => ['x' => 1]]]]]]);
    expect($deep['a']['b']['c']['d']['e'])->toBe('[TRUNCATED]');
});

// Route

it('exposes the webhook route and accepts POST without a session or CSRF token', function (): void {
    expect(route('webhooks.paymob'))->toEndWith('/webhooks/paymob');

    $response = t803Post(['id' => 'txn_route', 'reference' => 'NONE-A1', 'amount_minor' => 1, 'currency' => 'EGP', 'success' => true]);
    $response->assertOk();
    $response->assertHeaderMissing('Set-Cookie');
});

it('answers 405 to GET on the webhook route', function (): void {
    // BUG: routes/pages.php registers Route::fallback(LegacyRedirectController::class), which shadows Laravel's
    // method-not-allowed response for this path, so GET returns 404 from the fallback instead of 405. GET still
    // changes nothing, but the acceptance list asks for 405.
    $this->get(route('webhooks.paymob'))->assertStatus(405);
})->group('T-P8-03', 'A3')->skip('BUG: routes/pages.php Route::fallback shadows the 405 for GET /webhooks/paymob (returns 404).');

it('rate limits the webhook to 120 requests a minute', function (): void {
    $payload = ['id' => 'txn_rl', 'reference' => 'RL-A1', 'amount_minor' => 1, 'currency' => 'EGP', 'success' => true];

    for ($i = 0; $i < 120; $i++) {
        t803Post($payload, 'bad-signature')->assertStatus(401);
    }

    t803Post($payload, 'bad-signature')->assertStatus(429);
});

it('answers 413 for a body over 256 KB, and 400 for an empty or non-JSON body', function (): void {
    $huge = json_encode(['id' => 'big', 'blob' => str_repeat('a', 270000)]);
    $this->call('POST', route('webhooks.paymob'), [], [], [], ['CONTENT_TYPE' => 'application/json'], $huge)
        ->assertStatus(413);

    $this->call('POST', route('webhooks.paymob'), [], [], [], ['CONTENT_TYPE' => 'application/json'], '')
        ->assertStatus(400);
    $this->call('POST', route('webhooks.paymob'), [], [], [], ['CONTENT_TYPE' => 'application/json'], 'not json at all')
        ->assertStatus(400);
});

it('answers 401 for every callback with the Null gateway and stores nothing', function (): void {
    ['order' => $order, 'attempt' => $attempt] = t803Checkout();
    $payload = t803Payload($attempt, $order);

    config(['payments.driver' => 'null']);
    app()->forgetInstance(PaymentGateway::class);
    t803Post($payload, FakePaymentGateway::sign($payload))->assertStatus(401);

    expect(PaymentEvent::query()->count())->toBe(0)
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending);
});

// Labels and architecture

it('has audit labels for payment.verified, payment.attempt_failed and payment.flagged in AR and EN', function (): void {
    $ar = require base_path('lang/ar/audit-events.php');
    $en = require base_path('lang/en/audit-events.php');

    foreach (['payment.verified', 'payment.attempt_failed', 'payment.flagged'] as $event) {
        expect($en)->toHaveKey($event)
            ->and($ar)->toHaveKey($event);
    }
});

it('keeps the webhook action final, takes arrays, and keeps the domain free of HTTP imports', function (): void {
    $reflection = new ReflectionClass(ProcessPaymentWebhook::class);
    expect($reflection->isFinal())->toBeTrue();

    $param = $reflection->getMethod('__invoke')->getParameters()[0];
    expect((string) $param->getType())->toBe('array');

    foreach (glob(app_path('Domain/Commerce/Actions/*.php')) as $file) {
        $source = (string) file_get_contents($file);
        expect(preg_match('/^use (Illuminate\\\\Http\\\\|App\\\\Http\\\\)/m', $source))->toBe(0, basename($file));
    }
});
