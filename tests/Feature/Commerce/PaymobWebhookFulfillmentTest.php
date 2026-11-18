<?php

declare(strict_types=1);

use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Actions\ProcessPaymentWebhook;
use App\Domain\Commerce\Actions\StartCheckout;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\PaymentAttempt;
use App\Domain\Commerce\PaymentAttemptStatus;
use App\Domain\Commerce\PaymentEvent;
use App\Domain\Commerce\PaymentEventOutcome;
use App\Domain\Commerce\Payments\FakePaymentGateway;
use App\Domain\Commerce\Payments\InvalidPaymentSignature;
use App\Domain\Commerce\Payments\PaymobCredentials;
use App\Domain\Commerce\Payments\PaymobGateway;
use App\Domain\Commerce\ProductType;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Shared\Markets\Market;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

/** @return array{payer: User, course: Course, order: Order, attempt: PaymentAttempt} */
function whkCheckout(int $amountMinor = 25000): array
{
    $payer = User::factory()->create(['status' => 'active'])->assignRole('student');
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة', 'en' => 'Webhook Course'],
        'slug_ar' => 'whk-ar-'.uniqid(),
        'slug_en' => 'whk-en-'.uniqid(),
        'status' => 'published',
        'access_mode' => 'paid',
    ]);
    CoursePrice::query()->create(['course_id' => $course->id, 'market' => 'EG', 'currency' => 'EGP', 'amount_minor' => $amountMinor]);
    $order = app(CreateOrder::class)($payer, Market::Egypt, [new OrderLine(ProductType::Course, $course->id, $payer)], 'whk-ord-'.uniqid());
    $attempt = app(StartCheckout::class)($payer, $order, 'card');

    return compact('payer', 'course', 'order', 'attempt');
}

it('rejects oversized, malformed, and invalid-signature webhooks without writing events and verifies Paymob SHA-512 HMAC', function (): void {
    // Webhook route checks body shape and tampered Fake signature -> 401
    $payload = [
        'id' => 'txn_100',
        'reference' => 'ATT-UNKNOWN',
        'amount_minor' => 25000,
        'currency' => 'EGP',
        'success' => true,
    ];
    $this->postJson(route('webhooks.paymob'), $payload, ['X-Payment-Signature' => 'tampered-hmac'])
        ->assertStatus(401)
        ->assertJsonPath('status', 'invalid_signature');
    expect(PaymentEvent::query()->count())->toBe(0);

    // Oversized body -> 413; empty body -> 400
    $this->call('POST', route('webhooks.paymob'), [], [], [], ['CONTENT_TYPE' => 'application/json'], str_repeat('a', 262145))
        ->assertStatus(413);
    $this->postJson(route('webhooks.paymob'), [])->assertStatus(400);

    // PaymobGateway SHA-512 verification across flattened nested keys
    $creds = new PaymobCredentials('https://accept.paymob.com', 'egy_sk_test_1', 'egy_pk_test_1', null, 'my_hmac_secret', ['card' => '10'], 10);
    $paymob = new PaymobGateway($creds);
    $obj = [
        'id' => 777111,
        'amount_cents' => 25000,
        'created_at' => '2026-11-18T10:00:00',
        'currency' => 'EGP',
        'error_occured' => false,
        'has_parent_transaction' => false,
        'integration_id' => 10,
        'is_3d_secure' => true,
        'is_auth' => false,
        'is_capture' => false,
        'is_refunded' => false,
        'is_standalone_payment' => true,
        'is_voided' => false,
        'order' => ['id' => 999222, 'merchant_order_id' => 'ATT-PAYMOB-1'],
        'owner' => 123,
        'pending' => false,
        'source_data' => ['pan' => '0008', 'sub_type' => 'MasterCard', 'type' => 'card'],
        'success' => true,
    ];
    $concat = '25000'.'2026-11-18T10:00:00'.'EGP'.'false'.'false'.'777111'.'10'.'true'.'false'.'false'.'false'.'true'.'false'.'999222'.'123'.'false'.'0008'.'MasterCard'.'card'.'true';
    $validSig = hash_hmac('sha512', $concat, 'my_hmac_secret');

    $verified = $paymob->verifyCallback(['obj' => $obj], $validSig);
    expect($verified->eventId)->toBe('777111')
        ->and($verified->amountMinor)->toBe(25000)
        ->and($verified->attemptReference)->toBe('ATT-PAYMOB-1')
        ->and($verified->success)->toBeTrue();

    expect(fn () => $paymob->verifyCallback(['obj' => $obj], str_repeat('0', 128)))
        ->toThrow(InvalidPaymentSignature::class);
})->group('T-P8-03', 'A1', 'YG-GAP-PAY-002', 'D-025')->group('payments');

it('flags amount/currency mismatches, unmatched references, pending/refunded events, and marks failed attempts without closing order', function (): void {
    ['payer' => $payer, 'order' => $order, 'attempt' => $attempt] = whkCheckout(25000);

    // Mismatched amount -> stored as Flagged, order remains Pending, zero entitlements granted
    $mismatchPayload = [
        'id' => 'txn_mismatch',
        'reference' => $attempt->reference,
        'amount_minor' => 1000,
        'currency' => 'EGP',
        'success' => true,
    ];
    $this->postJson(route('webhooks.paymob'), $mismatchPayload, ['X-Payment-Signature' => FakePaymentGateway::sign($mismatchPayload)])
        ->assertOk();
    $mismatchEvent = PaymentEvent::query()->where('provider_event_id', 'txn_mismatch')->sole();
    expect($mismatchEvent->outcome)->toBe(PaymentEventOutcome::Flagged)
        ->and($mismatchEvent->reason)->toBe('amount_mismatch')
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and(Entitlement::query()->where('user_id', $payer->id)->count())->toBe(0);

    // Mismatched currency -> Flagged
    $currencyPayload = [
        'id' => 'txn_usd',
        'reference' => $attempt->reference,
        'amount_minor' => 25000,
        'currency' => 'USD',
        'success' => true,
    ];
    $this->postJson(route('webhooks.paymob'), $currencyPayload, ['X-Payment-Signature' => FakePaymentGateway::sign($currencyPayload)])
        ->assertOk();
    expect(PaymentEvent::query()->where('provider_event_id', 'txn_usd')->sole()->reason)->toBe('amount_mismatch');

    // Pending / unmatched / refunded callbacks
    foreach ([
        ['id' => 'txn_unmatched', 'reference' => 'NO-SUCH-ATTEMPT', 'amount_minor' => 25000, 'currency' => 'EGP', 'success' => true],
        ['id' => 'txn_pending', 'reference' => $attempt->reference, 'amount_minor' => 25000, 'currency' => 'EGP', 'pending' => true],
        ['id' => 'txn_refunded', 'reference' => $attempt->reference, 'amount_minor' => 25000, 'currency' => 'EGP', 'refunded' => true],
    ] as $row) {
        $this->postJson(route('webhooks.paymob'), $row, ['X-Payment-Signature' => FakePaymentGateway::sign($row)])->assertOk();
    }
    expect(PaymentEvent::query()->where('provider_event_id', 'txn_unmatched')->sole()->outcome)->toBe(PaymentEventOutcome::Unmatched)
        ->and(PaymentEvent::query()->where('provider_event_id', 'txn_pending')->sole()->outcome)->toBe(PaymentEventOutcome::Ignored)
        ->and(PaymentEvent::query()->where('provider_event_id', 'txn_refunded')->sole()->outcome)->toBe(PaymentEventOutcome::Flagged);

    // Failed callback marks attempt failed but leaves order Pending so payer can retry
    $failedPayload = [
        'id' => 'txn_declined',
        'reference' => $attempt->reference,
        'amount_minor' => 25000,
        'currency' => 'EGP',
        'success' => false,
    ];
    app(ProcessPaymentWebhook::class)($failedPayload, FakePaymentGateway::sign($failedPayload));
    expect($attempt->fresh()->status)->toBe(PaymentAttemptStatus::Failed)
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending);
})->group('T-P8-03', 'A2', 'YG-GAP-PAY-002', 'YG-REV-PAY-002', 'ED-034')->group('payments');

it('fulfills paid orders once with idempotent webhook replay and single entitlement/ledger source', function (): void {
    ['payer' => $payer, 'course' => $course, 'order' => $order, 'attempt' => $attempt] = whkCheckout(25000);

    $paidPayload = [
        'id' => 'txn_paid_ok_1',
        'reference' => $attempt->reference,
        'amount_minor' => 25000,
        'currency' => 'EGP',
        'success' => true,
    ];
    $sig = FakePaymentGateway::sign($paidPayload);

    $this->postJson(route('webhooks.paymob'), $paidPayload, ['X-Payment-Signature' => $sig])->assertOk();
    // Replay identical webhook -> still 200 OK, zero duplicate fulfillment
    $this->postJson(route('webhooks.paymob'), $paidPayload, ['X-Payment-Signature' => $sig])->assertOk();

    expect($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($order->fresh()->paid_at)->not->toBeNull()
        ->and($attempt->fresh()->status)->toBe(PaymentAttemptStatus::Succeeded)
        ->and(PaymentEvent::query()->where('provider_event_id', 'txn_paid_ok_1')->sole()->outcome)->toBe(PaymentEventOutcome::Fulfilled)
        ->and(Entitlement::query()->where('learner_id', $payer->id)->where('course_id', $course->id)->count())->toBe(1)
        ->and(Entitlement::query()->sole()->source_type)->toBe(EntitlementSource::Purchase);
})->group('T-P8-03', 'A3', 'YG-GAP-PAY-002', 'ED-034')->group('payments');

it('flags paid webhooks targeting a disabled market such as Saudi SAR without granting entitlement', function (): void {
    $payer = User::factory()->create(['status' => 'active'])->assignRole('student');
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة', 'en' => 'Saudi Market Guard Course'],
        'slug_ar' => 'whk-sa-ar-'.uniqid(),
        'slug_en' => 'whk-sa-en-'.uniqid(),
        'status' => 'published',
        'access_mode' => 'paid',
    ]);

    $saOrder = Order::query()->create([
        'reference' => 'YG-ORD-SA-'.uniqid(),
        'payer_id' => $payer->id,
        'market' => Market::SaudiArabia->value,
        'currency' => 'SAR',
        'subtotal_minor' => 15000,
        'discount_minor' => 0,
        'total_minor' => 15000,
        'status' => OrderStatus::Pending,
        'idempotency_key' => 'sa-whk-'.uniqid(),
        'request_hash' => hash('sha256', uniqid('sa-', true)),
    ]);
    $attempt = PaymentAttempt::query()->create([
        'order_id' => $saOrder->id,
        'active_order_id' => $saOrder->id,
        'gateway' => 'fake',
        'sequence' => 1,
        'reference' => $saOrder->reference.'-A1',
        'amount_minor' => 15000,
        'currency' => 'SAR',
        'status' => PaymentAttemptStatus::Created,
        'expires_at' => now()->addMinutes(30),
    ]);

    $saPayload = [
        'id' => 'txn_sa_blocked_1',
        'reference' => $attempt->reference,
        'amount_minor' => 15000,
        'currency' => 'SAR',
        'success' => true,
    ];
    $this->postJson(route('webhooks.paymob'), $saPayload, ['X-Payment-Signature' => FakePaymentGateway::sign($saPayload)])
        ->assertOk();

    $event = PaymentEvent::query()->where('provider_event_id', 'txn_sa_blocked_1')->sole();
    expect($event->outcome)->toBe(PaymentEventOutcome::Flagged)
        ->and($event->reason)->toBe('market_unavailable')
        ->and($saOrder->fresh()->status)->toBe(OrderStatus::Pending)
        ->and(Entitlement::query()->where('learner_id', $payer->id)->count())->toBe(0);
})->group('T-P8-13', 'T-P8-03', 'A1', 'YG-GAP-MKT-001', 'PRD-04')->group('payments');
