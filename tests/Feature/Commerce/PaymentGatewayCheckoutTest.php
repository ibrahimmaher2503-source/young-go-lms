<?php

declare(strict_types=1);

use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Actions\StartCheckout;
use App\Domain\Commerce\Contracts\PaymentGateway;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\PaymentAttempt;
use App\Domain\Commerce\PaymentAttemptStatus;
use App\Domain\Commerce\Payments\FakePaymentGateway;
use App\Domain\Commerce\Payments\NullPaymentGateway;
use App\Domain\Commerce\Payments\PaymobCredentials;
use App\Domain\Commerce\Payments\PaymobGateway;
use App\Domain\Commerce\Payments\SecretRedactor;
use App\Domain\Commerce\ProductType;
use App\Domain\Commerce\Queries\CheckoutReturnStatus;
use App\Domain\Commerce\Queries\EnabledPaymentMethods;
use App\Domain\Shared\Markets\Market;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

/** @return array{payer: User, order: Order} */
function pgcPendingOrder(int $amountMinor = 30000): array
{
    $payer = User::factory()->create(['status' => 'active', 'name' => 'Salma Buyer'])->assignRole('student');
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة الدفع', 'en' => 'Checkout Course'],
        'slug_ar' => 'pgc-ar-'.uniqid(),
        'slug_en' => 'pgc-en-'.uniqid(),
        'status' => 'published',
        'access_mode' => 'paid',
    ]);
    CoursePrice::query()->create(['course_id' => $course->id, 'market' => 'EG', 'currency' => 'EGP', 'amount_minor' => $amountMinor]);
    $order = app(CreateOrder::class)($payer, Market::Egypt, [new OrderLine(ProductType::Course, $course->id, $payer)], 'pgc-ord-'.uniqid());

    return compact('payer', 'order');
}

it('keeps return status read-only and never marks an order paid from checkout return or session start', function (): void {
    ['payer' => $payer, 'order' => $order] = pgcPendingOrder();
    $stranger = User::factory()->create(['status' => 'active'])->assignRole('student');

    $attempt = app(StartCheckout::class)($payer, $order, 'card');
    expect($attempt->status)->toBe(PaymentAttemptStatus::Created)
        ->and($attempt->amount_minor)->toBe(30000)
        ->and($attempt->currency)->toBe('EGP')
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending);

    // Repeating StartCheckout returns the exact same reusable live attempt (idempotent)
    /** @var FakePaymentGateway $gateway */
    $gateway = app(PaymentGateway::class);
    $callsBefore = count($gateway->calls);
    $reused = app(StartCheckout::class)($payer, $order, 'card');
    expect($reused->id)->toBe($attempt->id)
        ->and(count($gateway->calls))->toBe($callsBefore);

    // Return status is strictly read-only for the payer and returns null for strangers
    $returnStatus = app(CheckoutReturnStatus::class);
    expect($returnStatus($stranger, $order))->toBeNull()
        ->and($returnStatus($payer, $order))->toBe([
            'order_status' => OrderStatus::Pending->value,
            'attempt_status' => PaymentAttemptStatus::Created->value,
            'paid' => false,
        ])
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending);

    // Foreign payer refused on StartCheckout; failed gateway marks attempt failed cleanly
    expect(fn () => app(StartCheckout::class)($stranger, $order, 'card'))->toThrow(ValidationException::class);

    ['payer' => $p2, 'order' => $o2] = pgcPendingOrder(15000);
    $gateway->failNext('Gateway 503', 'upstream_503');
    expect(fn () => app(StartCheckout::class)($p2, $o2, 'card'))->toThrow(ValidationException::class);
    expect(PaymentAttempt::query()->where('order_id', $o2->id)->sole()->status)->toBe(PaymentAttemptStatus::Failed);
})->group('T-P8-02', 'A1', 'YG-GAP-PAY-001', 'D-025')->group('payments');

it('derives enabled payment methods from config and refuses checkout when gateway is unconfigured', function (): void {
    ['payer' => $payer, 'order' => $order] = pgcPendingOrder();

    Config::set('payments.methods', ['wallet', 'card']);
    expect(array_column(app(EnabledPaymentMethods::class)(), 'key'))->toBe(['wallet', 'card']);

    // Unsupported method rejected
    expect(fn () => app(StartCheckout::class)($payer, $order, 'crypto'))->toThrow(ValidationException::class);

    // NullPaymentGateway refuses before creating any PaymentAttempt row
    app()->instance(PaymentGateway::class, new NullPaymentGateway);
    expect(app(EnabledPaymentMethods::class)())->toBe([]);
    expect(fn () => app(StartCheckout::class)($payer, $order, 'card'))->toThrow(ValidationException::class);
    expect(PaymentAttempt::query()->where('order_id', $order->id)->count())->toBe(0);
})->group('T-P8-02', 'A2', 'YG-GAP-PAY-001')->group('payments');

it('blocks live Paymob keys when live_enabled is false, builds intention checkout safely, and scrubs all secrets', function (): void {
    // SecretRedactor removes credentials, tokens, and Paymob key prefixes
    $redactor = new SecretRedactor(['my_secret_123', 'my_hmac_456']);
    $clean = $redactor->redact('Error with my_secret_123, Token abc.def-1, Bearer xyz.2, and egy_sk_test_AbCd123 + my_hmac_456');
    expect($clean)->not->toContain('my_secret_123')
        ->not->toContain('my_hmac_456')
        ->not->toContain('abc.def-1')
        ->not->toContain('xyz.2')
        ->not->toContain('egy_sk_test_AbCd123');

    // Live secret key stays blocked until payments.live_enabled = true
    Config::set('payments.live_enabled', false);
    $liveCreds = new PaymobCredentials('https://accept.paymob.com', 'egy_sk_live_REALKEY', 'egy_pk_live_PUB', 'api_key', 'hmac_sec', ['card' => '11'], 10);
    expect($liveCreds->blockedAsLive())->toBeTrue()
        ->and((new PaymobGateway($liveCreds))->isConfigured())->toBeFalse()
        ->and(var_export($liveCreds->__debugInfo(), true))->not->toContain('egy_sk_live_REALKEY')
        ->and($liveCreds->__serialize())->toBe([]);

    // Test credentials + Http::fake verify CheckoutSession creation without exposing secret in session URL
    Http::fake([
        'https://accept.paymob.com/v1/intention/' => Http::response([
            'id' => 'int_101',
            'intention_order_id' => 9876,
            'client_secret' => 'cs_public_client_token',
        ], 200),
    ]);
    $testCreds = new PaymobCredentials('https://accept.paymob.com', 'egy_sk_test_SECRET', 'egy_pk_test_PUBLIC', null, 'hmac_secret', ['card' => '42'], 10);
    $paymob = new PaymobGateway($testCreds);
    expect($paymob->supportedMethods())->toBe(['card']);

    ['payer' => $payer, 'order' => $order] = pgcPendingOrder(40000);
    app()->instance(PaymentGateway::class, $paymob);
    $attempt = app(StartCheckout::class)($payer, $order, 'card');

    expect($attempt->provider_intention_id)->toBe('int_101')
        ->and($attempt->checkout_url)->toContain('/unifiedcheckout/')
        ->and($attempt->checkout_url)->not->toContain('egy_sk_test_SECRET');

    Http::assertSent(fn ($req) => $req->hasHeader('Authorization', 'Token egy_sk_test_SECRET')
        && $req['amount'] === 40000
        && $req['currency'] === 'EGP');
})->group('T-P8-02', 'A3', 'YG-GAP-PAY-001', 'D-025')->group('payments');
