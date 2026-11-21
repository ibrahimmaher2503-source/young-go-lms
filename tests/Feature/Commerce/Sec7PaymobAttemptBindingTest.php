<?php

declare(strict_types=1);

use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Contracts\PaymentGateway;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\PaymentAttempt;
use App\Domain\Commerce\PaymentAttemptStatus;
use App\Domain\Commerce\PaymentEvent;
use App\Domain\Commerce\PaymentEventOutcome;
use App\Domain\Commerce\Payments\CheckoutIntent;
use App\Domain\Commerce\Payments\PaymentGatewayException;
use App\Domain\Commerce\Payments\PaymobCredentials;
use App\Domain\Commerce\Payments\PaymobGateway;
use App\Domain\Commerce\ProductType;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Shared\Markets\Market;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    config(['payments.driver' => 'auto', 'payments.methods' => ['card']]);
    // Paymob is the gateway under test; a test-only, fully configured adapter. No network, no real keys.
    app()->instance(PaymentGateway::class, new PaymobGateway(sec7Credentials()));
});

function sec7Credentials(): PaymobCredentials
{
    return new PaymobCredentials('https://paymob.test', 'egy_sk_test_SEC7', 'egy_pk_test_SEC7', 'SEC7_API_KEY', 'sec7-hmac-secret', ['card' => '111'], 5);
}

/**
 * A pending EGP course order with a server-side total of 25000 minor units, plus an unpaid Paymob attempt on it.
 *
 * @return array{payer: User, course: Course, order: Order, attempt: PaymentAttempt}
 */
function sec7Checkout(?string $providerOrderId, int $amountMinor = 25000): array
{
    $payer = User::factory()->create(['status' => 'active'])->assignRole('student');
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة', 'en' => 'Paymob Binding Course '.uniqid()],
        'slug_ar' => 'sec7-ar-'.uniqid(),
        'slug_en' => 'sec7-en-'.uniqid(),
        'status' => 'published',
        'access_mode' => 'paid',
    ]);
    CoursePrice::query()->create(['course_id' => $course->id, 'market' => 'EG', 'currency' => 'EGP', 'amount_minor' => $amountMinor]);
    $order = app(CreateOrder::class)($payer, Market::Egypt, [new OrderLine(ProductType::Course, $course->id, $payer)], 'sec7-ord-'.uniqid());

    $attempt = PaymentAttempt::query()->create([
        'order_id' => $order->id,
        'active_order_id' => $order->id,
        'gateway' => 'paymob',
        'provider_order_id' => $providerOrderId,
        'sequence' => 1,
        'reference' => $order->reference.'-A1',
        'amount_minor' => $order->total_minor,
        'currency' => $order->currency,
        'status' => PaymentAttemptStatus::Created,
        'expires_at' => now()->addMinutes(30),
    ]);

    return compact('payer', 'course', 'order', 'attempt');
}

/** The processed-transaction fields Paymob signs, in the order PaymobGateway concatenates them. */
function sec7HmacFields(): array
{
    return [
        'amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction', 'id', 'integration_id',
        'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment', 'is_voided', 'order.id', 'owner',
        'pending', 'source_data.pan', 'source_data.sub_type', 'source_data.type', 'success',
    ];
}

/** Flattens nested keys with dots, as PaymobGateway does before signing. */
function sec7Flatten(array $data, string $prefix = ''): array
{
    $flat = [];
    foreach ($data as $key => $value) {
        $name = $prefix.$key;
        if (is_array($value)) {
            $flat += sec7Flatten($value, $name.'.');
        } else {
            $flat[$name] = $value;
        }
    }

    return $flat;
}

/** A Paymob processed-transaction object. `order.id` is signed; `special_reference` is unsigned metadata. */
function sec7Obj(string $txnId, int $providerOrderId, int $amountMinor, string $specialReference, array $overrides = []): array
{
    return array_replace_recursive([
        'id' => $txnId,
        'amount_cents' => $amountMinor,
        'created_at' => '2026-11-20T10:00:00',
        'currency' => 'EGP',
        'error_occured' => false,
        'has_parent_transaction' => false,
        'integration_id' => 111,
        'is_3d_secure' => true,
        'is_auth' => false,
        'is_capture' => false,
        'is_refunded' => false,
        'is_standalone_payment' => true,
        'is_voided' => false,
        'order' => ['id' => $providerOrderId, 'merchant_order_id' => 'UNSIGNED-'.$txnId],
        'owner' => 123,
        'pending' => false,
        'source_data' => ['pan' => '0008', 'sub_type' => 'MasterCard', 'type' => 'card'],
        'success' => true,
        'special_reference' => $specialReference,
    ], $overrides);
}

/** HMAC-SHA512 over the signed fields, using the test HMAC secret. */
function sec7Sign(array $obj): string
{
    $flat = sec7Flatten($obj);
    $message = '';
    foreach (sec7HmacFields() as $field) {
        $value = $flat[$field] ?? null;
        $message .= is_bool($value) ? ($value ? 'true' : 'false') : (string) ($value ?? '');
    }

    return hash_hmac('sha512', $message, 'sec7-hmac-secret');
}

/** Posts a Paymob callback over the real route, signed with the test secret. */
function sec7Post(array $obj)
{
    return test()->postJson(route('webhooks.paymob'), ['obj' => $obj], ['X-Payment-Signature' => sec7Sign($obj)]);
}

function sec7Entitlements(): int
{
    return Entitlement::query()->count();
}

it('fails closed when Paymob returns no intention_order_id, without leaking the client secret', function (): void {
    Http::fake(['https://paymob.test/v1/intention/' => Http::response([
        'id' => 'INT_NO_ORDER', 'client_secret' => 'CLIENT_SECRET_SEC7',
    ], 200)]);
    $intent = new CheckoutIntent(
        'SEC7-REF-0001-A1', 'SEC7-REF-0001', 25000, 'EGP', [['name' => 'Course', 'amount_minor' => 25000, 'quantity' => 1]],
        ['card'], 'Test Learner', 'learner@example.test', 'https://app.test/webhooks/paymob', 'https://app.test/checkout/return',
    );

    $failure = null;
    try {
        (new PaymobGateway(sec7Credentials()))->createCheckout($intent);
    } catch (PaymentGatewayException $exception) {
        $failure = $exception;
    }

    expect($failure)->not->toBeNull()
        ->and($failure->failureCode)->toBe('bad_response')
        ->and($failure->getMessage())->not->toContain('CLIENT_SECRET_SEC7');
})->group('T-P8-02', 'T-P11-01');

it('binds a signed callback to the attempt whose provider order id it carries, not to a tampered special_reference', function (): void {
    ['order' => $orderA, 'attempt' => $attemptA] = sec7Checkout('9001');
    ['order' => $orderB, 'attempt' => $attemptB] = sec7Checkout('9002');

    // Signed order.id 9001 belongs to attempt A; the unsigned special_reference points at victim attempt B.
    sec7Post(sec7Obj('txn_cross_1', 9001, 25000, $attemptB->reference))->assertOk();

    $event = PaymentEvent::query()->where('provider_event_id', 'txn_cross_1')->sole();
    expect($event->outcome)->toBe(PaymentEventOutcome::Flagged)
        ->and($event->reason)->toBe('reference_mismatch')
        ->and($attemptB->fresh()->status)->toBe(PaymentAttemptStatus::Created)
        ->and($orderB->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($orderA->fresh()->status)->toBe(OrderStatus::Pending)
        ->and(sec7Entitlements())->toBe(0);
})->group('T-P8-03', 'T-P11-01');

it('flags a signed callback with an unknown provider order id even when special_reference names a real attempt', function (): void {
    ['order' => $orderB, 'attempt' => $attemptB] = sec7Checkout('9002');

    sec7Post(sec7Obj('txn_unknown_order_1', 9999, 25000, $attemptB->reference))->assertOk();

    $event = PaymentEvent::query()->where('provider_event_id', 'txn_unknown_order_1')->sole();
    expect($event->outcome)->toBe(PaymentEventOutcome::Flagged)
        ->and($event->reason)->toBe('provider_order_mismatch')
        ->and($orderB->fresh()->status)->toBe(OrderStatus::Pending)
        ->and($attemptB->fresh()->status)->toBe(PaymentAttemptStatus::Created)
        ->and(sec7Entitlements())->toBe(0);
})->group('T-P8-03', 'T-P11-01');

it('fails closed on a paymob attempt with no provider order id and does not backfill it from the callback', function (): void {
    ['order' => $order, 'attempt' => $attempt] = sec7Checkout(null);

    sec7Post(sec7Obj('txn_missing_1', 8888, 25000, $attempt->reference))->assertOk();

    $event = PaymentEvent::query()->where('provider_event_id', 'txn_missing_1')->sole();
    expect($event->outcome)->toBe(PaymentEventOutcome::Flagged)
        ->and($event->reason)->toBe('missing_provider_order')
        ->and($attempt->fresh()->provider_order_id)->toBeNull()
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and(sec7Entitlements())->toBe(0);
})->group('T-P8-03', 'T-P11-01');

it('fulfils a paymob callback whose signed order id and special_reference both match the attempt, once', function (): void {
    ['payer' => $payer, 'course' => $course, 'order' => $order, 'attempt' => $attempt] = sec7Checkout('9001');

    sec7Post(sec7Obj('txn_match_1', 9001, $order->total_minor, $attempt->reference))->assertOk();
    // Replay of the same signed callback changes nothing further.
    sec7Post(sec7Obj('txn_match_1', 9001, $order->total_minor, $attempt->reference))->assertOk();

    expect(PaymentEvent::query()->where('provider_event_id', 'txn_match_1')->sole()->outcome)->toBe(PaymentEventOutcome::Fulfilled)
        ->and($order->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($attempt->fresh()->status)->toBe(PaymentAttemptStatus::Succeeded)
        ->and($attempt->fresh()->provider_order_id)->toBe('9001')
        ->and(Entitlement::query()->where('learner_id', $payer->id)->where('course_id', $course->id)->count())->toBe(1);
})->group('T-P8-03', 'T-P11-01');
