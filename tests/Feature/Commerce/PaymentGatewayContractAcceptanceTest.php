<?php

// T-P8-02 | D-025, YG-GAP-PAY-001, Arch 10.2/10.3 | A1 A2 A3

declare(strict_types=1);

use App\Domain\Commerce\Actions\AdjustOrderTotal;
use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Actions\StartCheckout;
use App\Domain\Commerce\Actions\TransitionOrder;
use App\Domain\Commerce\Contracts\PaymentGateway;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\PaymentAttempt;
use App\Domain\Commerce\PaymentAttemptStatus;
use App\Domain\Commerce\Payments\CheckoutIntent;
use App\Domain\Commerce\Payments\FakePaymentGateway;
use App\Domain\Commerce\Payments\InvalidPaymentSignature;
use App\Domain\Commerce\Payments\NullPaymentGateway;
use App\Domain\Commerce\Payments\PaymentGatewayException;
use App\Domain\Commerce\Payments\PaymentGatewayNotConfigured;
use App\Domain\Commerce\Payments\PaymentGatewayResolver;
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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    // Keep the Paymob branch of the resolver closed unless a test opts in.
    config([
        'payments.driver' => 'auto',
        'payments.methods' => ['card', 'wallet'],
        'payments.paymob.secret_key' => null,
        'payments.paymob.public_key' => null,
        'payments.paymob.hmac_secret' => null,
        'payments.paymob.api_key' => null,
        'payments.paymob.integration_ids' => ['card' => null, 'wallet' => null],
        'payments.live_enabled' => false,
    ]);
});

afterEach(function (): void {
    app()->detectEnvironment(fn () => 'testing');
});

/** A published, paid EG course with an EGP price. */
function t802Course(int $amountMinor = 25000): Course
{
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة', 'en' => 'Gateway Course '.uniqid()],
        'slug_ar' => 't802-ar-'.uniqid(),
        'slug_en' => 't802-en-'.uniqid(),
        'status' => 'published',
        'access_mode' => 'paid',
    ]);
    CoursePrice::query()->create(['course_id' => $course->id, 'market' => 'EG', 'currency' => 'EGP', 'amount_minor' => $amountMinor]);

    return $course;
}

function t802Payer(string $status = 'active'): User
{
    return User::factory()->create(['status' => $status])->assignRole('student');
}

function t802Finance(): User
{
    return User::factory()->create(['status' => 'active'])->assignRole('finance');
}

/** A pending order for the payer, priced by the server. */
function t802Order(User $payer, string $key, int $amountMinor = 25000): Order
{
    $course = t802Course($amountMinor);

    return app(CreateOrder::class)($payer, Market::Egypt, [new OrderLine(ProductType::Course, $course->id, $payer)], $key);
}

/** Runs the callable and returns the first refusal message raised. */
function t802Refusal(callable $call): string
{
    try {
        $call();
    } catch (ValidationException $e) {
        return array_values($e->errors())[0][0];
    }

    throw new RuntimeException('Expected the call to be refused.');
}

/** Paymob credentials built directly, so no real secret is ever read from the environment. */
function t802Credentials(array $integrations = ['card' => '111', 'wallet' => '222'], ?string $secret = 'egy_sk_test_SECRET0001'): PaymobCredentials
{
    return new PaymobCredentials('https://paymob.test', $secret, 'egy_pk_test_PUBLIC0002', 'API_KEY_SECRET0003', 'HMAC_SECRET0004', $integrations, 5);
}

/** Config for a complete Paymob setup, applied before the resolver runs. */
function t802PaymobConfig(string $secret = 'egy_sk_test_SECRET0001'): void
{
    config([
        'payments.paymob.secret_key' => $secret,
        'payments.paymob.public_key' => 'egy_pk_test_PUBLIC0002',
        'payments.paymob.hmac_secret' => 'HMAC_SECRET0004',
        'payments.paymob.integration_ids' => ['card' => '111', 'wallet' => null],
    ]);
}

/** A signed Paymob-style callback body (obj) with every field of the documented HMAC list. */
function t802PaymobObj(array $overrides = []): array
{
    return array_replace_recursive([
        'amount_cents' => 25000, 'created_at' => '2026-11-18T10:00:00', 'currency' => 'EGP', 'error_occured' => false,
        'has_parent_transaction' => false, 'id' => 987654, 'integration_id' => 111, 'is_3d_secure' => false,
        'is_auth' => false, 'is_capture' => false, 'is_refunded' => false, 'is_standalone_payment' => true,
        'is_voided' => false, 'order' => ['id' => 555], 'owner' => 42, 'pending' => false,
        'source_data' => ['pan' => '2345', 'sub_type' => 'MasterCard', 'type' => 'card'], 'success' => true,
        'special_reference' => 'YG-ORD-TESTREF001-A1',
    ], $overrides);
}

/** The HMAC-SHA512 a valid Paymob callback carries, over the documented field order. */
function t802PaymobHmac(array $obj, string $secret = 'HMAC_SECRET0004'): string
{
    $flat = [];
    $walk = function (array $data, string $prefix) use (&$walk, &$flat): void {
        foreach ($data as $key => $value) {
            is_array($value) ? $walk($value, $prefix.$key.'.') : $flat[$prefix.$key] = $value;
        }
    };
    $walk($obj, '');
    $fields = [
        'amount_cents', 'created_at', 'currency', 'error_occured', 'has_parent_transaction', 'id', 'integration_id',
        'is_3d_secure', 'is_auth', 'is_capture', 'is_refunded', 'is_standalone_payment', 'is_voided', 'order.id', 'owner',
        'pending', 'source_data.pan', 'source_data.sub_type', 'source_data.type', 'success',
    ];
    $message = '';
    foreach ($fields as $field) {
        $value = $flat[$field] ?? null;
        $message .= is_bool($value) ? ($value ? 'true' : 'false') : (string) ($value ?? '');
    }

    return hash_hmac('sha512', $message, $secret);
}

// A1 Return URL never pays

it('leaves a pending order and its attempt untouched when the return page is opened', function (): void {
    $payer = t802Payer();
    $order = t802Order($payer, 't802-ret-0001');
    $attempt = app(StartCheckout::class)($payer, $order, 'card');
    $attemptsBefore = PaymentAttempt::query()->count();
    $auditBefore = Activity::query()->count();

    $status = app(CheckoutReturnStatus::class)($payer, $order);

    expect($status)->toBe(['order_status' => 'pending', 'attempt_status' => 'created', 'paid' => false])
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending)
        ->and(PaymentAttempt::query()->count())->toBe($attemptsBefore)
        ->and(Activity::query()->count())->toBe($auditBefore)
        ->and($attempt->fresh()->status)->toBe(PaymentAttemptStatus::Created);
})->group('T-P8-02', 'A1');

it('takes only the payer and the order, with no gateway or query parameter', function (): void {
    $params = array_map(
        static fn (ReflectionParameter $p): string => $p->getName(),
        (new ReflectionMethod(CheckoutReturnStatus::class, '__invoke'))->getParameters(),
    );

    expect($params)->toBe(['payer', 'order']);
})->group('T-P8-02', 'A1');

it('returns null for an order that belongs to another payer', function (): void {
    $owner = t802Payer();
    $stranger = t802Payer();
    $order = t802Order($owner, 't802-ret-0002');

    expect(app(CheckoutReturnStatus::class)($stranger, $order))->toBeNull();
})->group('T-P8-02', 'A1');

it('reports paid only after the verified transition moved the order', function (): void {
    $payer = t802Payer();
    $order = t802Order($payer, 't802-ret-0003');
    app(StartCheckout::class)($payer, $order, 'card');
    expect(app(CheckoutReturnStatus::class)($payer, $order)['paid'])->toBeFalse();

    app(TransitionOrder::class)($order, OrderStatus::Paid);

    expect(app(CheckoutReturnStatus::class)($payer, $order->fresh())['paid'])->toBeTrue();
})->group('T-P8-02', 'A1');

it('never calls TransitionOrder from the checkout start or the return page', function (): void {
    foreach (['StartCheckout', 'CheckoutReturnStatus'] as $name) {
        $path = match ($name) {
            'StartCheckout' => app_path('Domain/Commerce/Actions/StartCheckout.php'),
            default => app_path('Domain/Commerce/Queries/CheckoutReturnStatus.php'),
        };
        expect((string) file_get_contents($path))->not->toContain('TransitionOrder', $name);
    }
})->group('T-P8-02', 'A1');

// A2 Method list from config

it('offers the configured methods in config order, narrowed to what the gateway serves', function (): void {
    config(['payments.methods' => ['wallet', 'card']]);
    app()->forgetInstance(PaymentGateway::class);
    $fake = app(PaymentGateway::class);
    expect($fake)->toBeInstanceOf(FakePaymentGateway::class);

    $keys = array_column((new EnabledPaymentMethods($fake))(), 'key');
    expect($keys)->toBe(['wallet', 'card']);

    config(['payments.methods' => ['card']]);
    expect(array_column((new EnabledPaymentMethods($fake))(), 'key'))->toBe(['card']);
})->group('T-P8-02', 'A2');

it('offers only the card method when only the card integration id is configured', function (): void {
    config(['payments.methods' => ['card', 'wallet']]);
    $paymob = new PaymobGateway(t802Credentials(['card' => '111']));

    expect($paymob->supportedMethods())->toBe(['card'])
        ->and(array_column((new EnabledPaymentMethods($paymob))(), 'key'))->toBe(['card']);
})->group('T-P8-02', 'A2');

it('offers no method from the Null gateway', function (): void {
    expect((new EnabledPaymentMethods(new NullPaymentGateway))())->toBe([])
        ->and((new NullPaymentGateway)->supportedMethods())->toBe([]);
})->group('T-P8-02', 'A2');

it('refuses a method the gateway does not offer with payments.method_unavailable', function (): void {
    config(['payments.methods' => ['card']]);
    app()->forgetInstance(PaymentGateway::class);
    $payer = t802Payer();
    $order = t802Order($payer, 't802-meth-0001');

    expect(t802Refusal(fn () => app(StartCheckout::class)($payer, $order, 'wallet')))
        ->toBe(__('payments.method_unavailable'));
    expect(PaymentAttempt::query()->count())->toBe(0);
})->group('T-P8-02', 'A2');

// A3 No secrets in logs, exceptions or rows

it('keeps Paymob secrets out of logs, exceptions, attempt rows and audit rows on a 500', function (): void {
    $paymob = new PaymobGateway(t802Credentials());
    app()->instance(PaymentGateway::class, $paymob);
    Http::fake(['https://paymob.test/v1/intention/' => Http::response(['detail' => 'boom'], 500)]);

    $logged = [];
    Log::shouldReceive('warning')->andReturnUsing(function ($message) use (&$logged): void {
        $logged[] = (string) $message;
    });
    Log::shouldReceive('info', 'error', 'debug', 'notice', 'alert', 'critical', 'emergency')->zeroOrMoreTimes();

    $payer = t802Payer();
    $order = t802Order($payer, 't802-sec-0001');
    $message = t802Refusal(fn () => app(StartCheckout::class)($payer, $order, 'card'));

    $secrets = ['egy_sk_test_SECRET0001', 'egy_pk_test_PUBLIC0002', 'API_KEY_SECRET0003', 'HMAC_SECRET0004', 'Token '];
    $rowText = json_encode(DB::table('payment_attempts')->get()->all()).json_encode(Activity::query()->get()->toArray());
    foreach ($secrets as $secret) {
        expect($message)->not->toContain($secret)
            ->and(implode("\n", $logged))->not->toContain($secret)
            ->and($rowText)->not->toContain($secret);
    }
    expect($message)->toBe(__('payments.checkout_failed'))
        ->and(PaymentAttempt::query()->value('failure_code'))->toBe('http_500');
})->group('T-P8-02', 'A3');

it('keeps Paymob secrets out of logs and rows when the connection fails', function (): void {
    app()->instance(PaymentGateway::class, new PaymobGateway(t802Credentials()));
    Http::fake(fn () => throw new ConnectionException('connection to paymob.test refused'));

    $logged = [];
    Log::shouldReceive('warning')->andReturnUsing(function ($message) use (&$logged): void {
        $logged[] = (string) $message;
    });

    $payer = t802Payer();
    $order = t802Order($payer, 't802-sec-0002');
    t802Refusal(fn () => app(StartCheckout::class)($payer, $order, 'card'));

    $all = implode("\n", $logged).json_encode(PaymentAttempt::query()->get()->toArray());
    expect($all)->not->toContain('egy_sk_test_SECRET0001')
        ->and($all)->not->toContain('HMAC_SECRET0004')
        ->and($all)->not->toContain('API_KEY_SECRET0003')
        ->and(PaymentAttempt::query()->value('failure_code'))->toBe('unreachable');
})->group('T-P8-02', 'A3');

it('masks listed secrets and Paymob key patterns in any text', function (): void {
    $redactor = new SecretRedactor(['plain-secret-value']);

    $out = $redactor->redact('a plain-secret-value b egy_sk_test_ABC123 c Token abc.def and Bearer xyz');

    expect($out)->not->toContain('plain-secret-value')
        ->and($out)->not->toContain('egy_sk_test_ABC123')
        ->and($out)->not->toContain('abc.def')
        ->and($out)->not->toContain('xyz')
        ->and($out)->toContain('[REDACTED]');
})->group('T-P8-02', 'A3');

it('shows no secret when credentials are dumped or serialized', function (): void {
    $creds = t802Credentials();

    ob_start();
    var_dump($creds);
    $dump = (string) ob_get_clean();

    $serialized = serialize($creds);

    foreach (['egy_sk_test_SECRET0001', 'egy_pk_test_PUBLIC0002', 'API_KEY_SECRET0003', 'HMAC_SECRET0004'] as $secret) {
        expect($dump)->not->toContain($secret)
            ->and($serialized)->not->toContain($secret);
    }
})->group('T-P8-02', 'A3');

// Amounts from the server order

it('sends the server order total and currency to the gateway, one line when the lines do not add up', function (): void {
    $payer = t802Payer();
    $order = t802Order($payer, 't802-amt-0001', 25000);
    $fake = app(PaymentGateway::class);

    app(StartCheckout::class)($payer, $order, 'card');

    $call = collect($fake->calls)->firstWhere('method', 'createCheckout');
    expect($call['args']['amount_minor'])->toBe(25000)
        ->and($call['args']['currency'])->toBe('EGP')
        ->and($call['args']['items'])->toHaveCount(1)
        ->and($call['args']['items'][0]['amount_minor'])->toBe(25000);
})->group('T-P8-02', 'A1');

it('closes an open session and starts a new one when the order total changes', function (): void {
    $payer = t802Payer();
    $order = t802Order($payer, 't802-amt-0002', 25000);
    $fake = app(PaymentGateway::class);
    $first = app(StartCheckout::class)($payer, $order, 'card');

    app(AdjustOrderTotal::class)($order->fresh(), t802Finance(), 2500, 'Discount applied to the open order');
    $second = app(StartCheckout::class)($payer, $order->fresh(), 'card');

    $calls = collect($fake->calls)->where('method', 'createCheckout')->values();
    expect($second->id)->not->toBe($first->id)
        ->and($second->sequence)->toBe(2)
        ->and($first->fresh()->status)->toBe(PaymentAttemptStatus::Cancelled)
        ->and($first->fresh()->active_order_id)->toBeNull()
        ->and($calls)->toHaveCount(2)
        ->and($calls[1]['args']['amount_minor'])->toBe(22500)
        ->and(Activity::query()->where('event', 'payment.checkout_closed')->count())->toBe(1);
})->group('T-P8-02', 'A1');

it('gives the gateway lines that add up to the total after a discount', function (): void {
    $payer = t802Payer();
    $order = t802Order($payer, 't802-amt-0003', 25000);
    app(AdjustOrderTotal::class)($order, t802Finance(), 5000, 'Discount before checkout');
    $fake = app(PaymentGateway::class);

    app(StartCheckout::class)($payer, $order->fresh(), 'card');

    $call = collect($fake->calls)->firstWhere('method', 'createCheckout');
    expect(array_sum(array_column($call['args']['items'], 'amount_minor')))->toBe(20000)
        ->and($call['args']['amount_minor'])->toBe(20000);
})->group('T-P8-02', 'A1');

// Idempotency

it('returns the same attempt and calls the gateway once for two starts', function (): void {
    $payer = t802Payer();
    $order = t802Order($payer, 't802-idem-0001');
    $fake = app(PaymentGateway::class);

    $a = app(StartCheckout::class)($payer, $order, 'card');
    $b = app(StartCheckout::class)($payer, $order, 'card');

    expect($b->id)->toBe($a->id)
        ->and(PaymentAttempt::query()->count())->toBe(1)
        ->and(collect($fake->calls)->where('method', 'createCheckout')->count())->toBe(1);
})->group('T-P8-02', 'A1');

it('allows only one open attempt per order at the database level', function (): void {
    $payer = t802Payer();
    $order = t802Order($payer, 't802-idem-0002');
    $open = app(StartCheckout::class)($payer, $order, 'card');

    expect(fn () => PaymentAttempt::query()->create([
        'order_id' => $order->id, 'gateway' => 'fake', 'sequence' => 9, 'reference' => 'X-A9',
        'status' => PaymentAttemptStatus::Created, 'active_order_id' => $order->id, 'amount_minor' => 25000,
        'currency' => 'EGP', 'expires_at' => now()->addMinutes(10),
    ]))->toThrow(QueryException::class);
    expect($open->fresh()->active_order_id)->toBe($order->id);
})->group('T-P8-02', 'A1');

it('starts sequence 2 with reference suffix -A2 after the first session expires', function (): void {
    $payer = t802Payer();
    $order = t802Order($payer, 't802-idem-0003');
    $first = app(StartCheckout::class)($payer, $order, 'card');

    $this->travel(31)->minutes();
    try {
        $second = app(StartCheckout::class)($payer, $order->fresh(), 'card');
    } finally {
        $this->travelBack();
    }

    expect($second->sequence)->toBe(2)
        ->and($second->reference)->toBe($order->reference.'-A2')
        ->and($first->fresh()->status)->toBe(PaymentAttemptStatus::Expired);
})->group('T-P8-02', 'A1');

it('refuses a second start while a stale initiated attempt is still inside its ttl', function (): void {
    $payer = t802Payer();
    $order = t802Order($payer, 't802-idem-0004');
    PaymentAttempt::query()->create([
        'order_id' => $order->id, 'gateway' => 'fake', 'sequence' => 1, 'reference' => $order->reference.'-A1',
        'status' => PaymentAttemptStatus::Initiated, 'active_order_id' => $order->id, 'amount_minor' => 25000,
        'currency' => 'EGP', 'expires_at' => now()->addMinutes(10),
    ]);

    expect(t802Refusal(fn () => app(StartCheckout::class)($payer, $order, 'card')))
        ->toBe(__('payments.checkout_in_progress'));
})->group('T-P8-02', 'A1');

// Failure

it('marks the attempt failed, frees the slot, stores only the failure code, and allows a retry', function (): void {
    $payer = t802Payer();
    $order = t802Order($payer, 't802-fail-0001');
    $fake = app(PaymentGateway::class);
    $fake->failNext('provider said no', 'gateway_error');

    expect(t802Refusal(fn () => app(StartCheckout::class)($payer, $order, 'card')))
        ->toBe(__('payments.checkout_failed'));

    $failed = PaymentAttempt::query()->where('order_id', $order->id)->firstOrFail();
    expect($failed->status)->toBe(PaymentAttemptStatus::Failed)
        ->and($failed->failure_code)->toBe('gateway_error')
        ->and($failed->active_order_id)->toBeNull()
        ->and($failed->payload)->toBeNull()
        ->and(Activity::query()->where('event', 'payment.checkout_failed')->count())->toBe(1);

    $retry = app(StartCheckout::class)($payer, $order->fresh(), 'card');
    expect($retry->sequence)->toBe(2)
        ->and($retry->reference)->toBe($order->reference.'-A2')
        ->and($retry->status)->toBe(PaymentAttemptStatus::Created);
})->group('T-P8-02', 'A1');

// Refusals with no rows written

it('refuses with gateway_unavailable and writes nothing when the Null gateway is configured', function (): void {
    config(['payments.driver' => 'null']);
    app()->forgetInstance(PaymentGateway::class);
    $payer = t802Payer();
    $order = t802Order($payer, 't802-ref-0001');
    $attemptsBefore = PaymentAttempt::query()->count();

    expect(app(PaymentGatewayResolver::class)())->toBeInstanceOf(NullPaymentGateway::class)
        ->and(t802Refusal(fn () => app(StartCheckout::class)($payer, $order, 'card')))
        ->toBe(__('payments.gateway_unavailable'))
        ->and(PaymentAttempt::query()->count())->toBe($attemptsBefore);
})->group('T-P8-02', 'A1');

it('refuses to start checkout for another payer, an inactive payer, or a non-pending order', function (): void {
    $payer = t802Payer();
    $stranger = t802Payer();
    $order = t802Order($payer, 't802-ref-0002');
    $start = app(StartCheckout::class);

    expect(t802Refusal(fn () => $start($stranger, $order, 'card')))->toBe(__('payments.order_invalid'));

    DB::table('users')->where('id', $payer->id)->update(['status' => 'suspended']);
    expect(t802Refusal(fn () => $start($payer->fresh(), $order->fresh(), 'card')))->toBe(__('payments.order_invalid'));
    DB::table('users')->where('id', $payer->id)->update(['status' => 'active']);

    foreach ([OrderStatus::Paid, OrderStatus::Failed, OrderStatus::Cancelled] as $status) {
        $closed = t802Order($payer, 't802-ref-st-'.$status->value);
        DB::table('orders')->where('id', $closed->id)->update(['status' => $status->value]);
        expect(t802Refusal(fn () => $start($payer, $closed->fresh(), 'card')))->toBe(__('payments.order_invalid'));
    }
    expect(PaymentAttempt::query()->count())->toBe(0);
})->group('T-P8-02', 'A1');

it('refuses a zero total order without writing rows', function (): void {
    $payer = t802Payer();
    $order = t802Order($payer, 't802-ref-0003');
    DB::table('orders')->where('id', $order->id)->update(['total_minor' => 0]);

    expect(t802Refusal(fn () => app(StartCheckout::class)($payer, $order->fresh(), 'card')))
        ->toBe(__('payments.order_invalid'));
    expect(PaymentAttempt::query()->count())->toBe(0);
})->group('T-P8-02', 'A1');

it('refuses a Saudi order (inactive market) without writing rows', function (): void {
    $payer = t802Payer();
    $order = Order::query()->create([
        'reference' => 'YG-ORD-SAR0000001', 'payer_id' => $payer->id, 'market' => 'SA', 'currency' => 'SAR',
        'subtotal_minor' => 1000, 'discount_minor' => 0, 'total_minor' => 1000, 'status' => OrderStatus::Pending,
        'idempotency_key' => 't802-sa-0001', 'request_hash' => str_repeat('a', 64),
    ]);

    expect(t802Refusal(fn () => app(StartCheckout::class)($payer, $order, 'card')))
        ->toBe(__('payments.market_unavailable'));
    expect(PaymentAttempt::query()->count())->toBe(0);
})->group('T-P8-02', 'A1');

// PaymentGatewayResolver

it('resolves the Paymob adapter when the Paymob configuration is complete', function (): void {
    t802PaymobConfig();

    expect((new PaymentGatewayResolver)())->toBeInstanceOf(PaymobGateway::class);
})->group('T-P8-02', 'A2');

it('resolves the Fake gateway when the Paymob configuration is incomplete in testing', function (): void {
    expect((new PaymentGatewayResolver)())->toBeInstanceOf(FakePaymentGateway::class);
})->group('T-P8-02', 'A2');

it('resolves the Null gateway when the Paymob configuration is incomplete in production', function (): void {
    app()->detectEnvironment(fn () => 'production');

    expect((new PaymentGatewayResolver)())->toBeInstanceOf(NullPaymentGateway::class);
})->group('T-P8-02', 'A2');

it('keeps a live key on the Null gateway in production while live payments are not enabled', function (): void {
    t802PaymobConfig('egy_sk_live_LIVESECRET0009');
    app()->detectEnvironment(fn () => 'production');

    expect((new PaymentGatewayResolver)())->toBeInstanceOf(NullPaymentGateway::class);
    expect((new PaymobGateway(PaymobCredentials::fromConfig()))->isConfigured())->toBeFalse();
})->group('T-P8-02', 'A2');

it('refuses the Fake driver outside local and testing and returns Null', function (): void {
    config(['payments.driver' => 'fake']);
    app()->detectEnvironment(fn () => 'production');

    expect((new PaymentGatewayResolver)())->toBeInstanceOf(NullPaymentGateway::class);
})->group('T-P8-02', 'A2');

it('refuses to build the Fake gateway in production', function (): void {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => new FakePaymentGateway)->toThrow(LogicException::class);
})->group('T-P8-02', 'A2');

// PaymobGateway over Http::fake

it('posts the intention with the secret token, server amounts and attempt reference, and returns the checkout URL', function (): void {
    Http::fake(['https://paymob.test/v1/intention/' => Http::response([
        'id' => 'INT_77', 'client_secret' => 'CLIENT_SECRET_XYZ', 'intention_order_id' => 77,
    ], 200)]);
    $paymob = new PaymobGateway(t802Credentials());
    $intent = new CheckoutIntent(
        'YG-ORD-REF0000001-A1', 'YG-ORD-REF0000001', 25000, 'EGP',
        [['name' => 'Course A', 'amount_minor' => 25000, 'quantity' => 1]], ['card'],
        'Test Learner', 'learner@example.test', 'https://app.test/webhooks/paymob', 'https://app.test/checkout/return?order=x',
    );

    $session = $paymob->createCheckout($intent);

    Http::assertSent(function ($request): bool {
        $body = $request->data();

        return $request->url() === 'https://paymob.test/v1/intention/'
            && $request->header('Authorization')[0] === 'Token egy_sk_test_SECRET0001'
            && $body['amount'] === 25000
            && $body['currency'] === 'EGP'
            && $body['payment_methods'] === [111]
            && $body['special_reference'] === 'YG-ORD-REF0000001-A1'
            && $body['notification_url'] === 'https://app.test/webhooks/paymob'
            && $body['redirection_url'] === 'https://app.test/checkout/return?order=x';
    });
    expect($session->redirectUrl)->toContain('publicKey=egy_pk_test_PUBLIC0002')
        ->and($session->redirectUrl)->toContain('clientSecret=CLIENT_SECRET_XYZ')
        ->and($session->providerIntentId)->toBe('INT_77');
})->group('T-P8-02', 'A1');

it('refuses a malformed Paymob response as bad_response and a non-2xx as http_<status>', function (): void {
    $intent = new CheckoutIntent(
        'YG-ORD-REF0000002-A1', 'YG-ORD-REF0000002', 1000, 'EGP', [['name' => 'x', 'amount_minor' => 1000, 'quantity' => 1]],
        ['card'], 'A', 'a@example.test', 'https://app.test/n', 'https://app.test/r',
    );
    $paymob = new PaymobGateway(t802Credentials());

    Http::fake(['https://paymob.test/v1/intention/' => Http::sequence()
        ->push(['id' => 'x'], 200)
        ->push(['detail' => 'nope'], 422)]);
    try {
        $paymob->createCheckout($intent);
        $this->fail('Expected a bad_response exception.');
    } catch (PaymentGatewayException $e) {
        expect($e->failureCode)->toBe('bad_response');
    }

    try {
        $paymob->createCheckout($intent);
        $this->fail('Expected an http_422 exception.');
    } catch (PaymentGatewayException $e) {
        expect($e->failureCode)->toBe('http_422');
    }
})->group('T-P8-02', 'A1');

it('makes no HTTP request when the Paymob gateway is unconfigured', function (): void {
    $paymob = new PaymobGateway(new PaymobCredentials('https://paymob.test', null, null, null, null, [], 5));
    $intent = new CheckoutIntent(
        'YG-ORD-REF0000003-A1', 'YG-ORD-REF0000003', 1000, 'EGP', [['name' => 'x', 'amount_minor' => 1000, 'quantity' => 1]],
        ['card'], 'A', 'a@example.test', 'https://app.test/n', 'https://app.test/r',
    );
    Http::fake();

    expect(fn () => $paymob->createCheckout($intent))->toThrow(PaymentGatewayNotConfigured::class);
    Http::assertNothingSent();
})->group('T-P8-02', 'A1');

// verifyCallback (pure)

it('accepts a correctly signed Paymob callback and returns the normalised event without card data', function (): void {
    $paymob = new PaymobGateway(t802Credentials());
    $obj = t802PaymobObj();

    $event = $paymob->verifyCallback(['obj' => $obj], t802PaymobHmac($obj));

    expect($event->gateway)->toBe('paymob')
        ->and($event->amountMinor)->toBe(25000)
        ->and($event->currency)->toBe('EGP')
        ->and($event->success)->toBeTrue()
        ->and($event->attemptReference)->toBe('YG-ORD-TESTREF001-A1')
        ->and($event->eventId)->toBe('987654')
        ->and(json_encode($event->payload))->not->toContain('2345')
        ->and(json_encode($event->payload))->not->toContain('MasterCard');
})->group('T-P8-02', 'A1');

it('rejects a tampered amount, a wrong secret, a missing or empty signature, and a missing obj', function (): void {
    $paymob = new PaymobGateway(t802Credentials());
    $obj = t802PaymobObj();
    $good = t802PaymobHmac($obj);

    $tampered = t802PaymobObj(['amount_cents' => 1]);
    expect(fn () => $paymob->verifyCallback(['obj' => $tampered], $good))->toThrow(InvalidPaymentSignature::class);
    expect(fn () => $paymob->verifyCallback(['obj' => $obj], t802PaymobHmac($obj, 'WRONG_SECRET')))->toThrow(InvalidPaymentSignature::class);
    expect(fn () => $paymob->verifyCallback(['obj' => $obj], null))->toThrow(InvalidPaymentSignature::class);
    expect(fn () => $paymob->verifyCallback(['obj' => $obj], ''))->toThrow(InvalidPaymentSignature::class);
    expect(fn () => $paymob->verifyCallback(['id' => 1], $good))->toThrow(InvalidPaymentSignature::class);
})->group('T-P8-02', 'A1');

it('signs and verifies fake callbacks and rejects an altered payload', function (): void {
    $fake = new FakePaymentGateway;
    $payload = ['id' => 'txn_1', 'reference' => 'REF-A1', 'amount_minor' => 1000, 'currency' => 'EGP', 'success' => true];

    $event = $fake->verifyCallback($payload, FakePaymentGateway::sign($payload));
    expect($event->success)->toBeTrue()
        ->and($event->attemptReference)->toBe('REF-A1');

    $altered = $payload;
    $altered['amount_minor'] = 1;
    expect(fn () => $fake->verifyCallback($altered, FakePaymentGateway::sign($payload)))->toThrow(InvalidPaymentSignature::class);
})->group('T-P8-02', 'A1');

// Encryption, audit, lang, arch

it('stores the checkout URL encrypted and hides it from serialization', function (): void {
    $payer = t802Payer();
    $order = t802Order($payer, 't802-enc-0001');
    $attempt = app(StartCheckout::class)($payer, $order, 'card');

    $raw = (string) DB::table('payment_attempts')->where('id', $attempt->id)->value('checkout_url');

    expect($raw)->not->toBe('')
        ->and($raw)->not->toContain('fake-gateway.test')
        ->and($attempt->fresh()->checkout_url)->toContain('fake-gateway.test')
        ->and($attempt->fresh()->toArray())->not->toHaveKey('checkout_url');
})->group('T-P8-02', 'A3');

it('writes payment.checkout_created once and has its labels in AR and EN', function (): void {
    $payer = t802Payer();
    $order = t802Order($payer, 't802-aud-0001');
    app(StartCheckout::class)($payer, $order, 'card');
    app(StartCheckout::class)($payer, $order->fresh(), 'card');

    expect(Activity::query()->where('event', 'payment.checkout_created')->count())->toBe(1);

    $ar = require base_path('lang/ar/audit-events.php');
    $en = require base_path('lang/en/audit-events.php');
    expect($ar)->toHaveKey('payment.checkout_created')
        ->and($en)->toHaveKey('payment.checkout_created');
})->group('T-P8-02', 'A3');

it('rolls the attempt update back when the checkout audit cannot be recorded', function (): void {
    $payer = t802Payer();
    $order = t802Order($payer, 't802-aud-0002');

    activity()->disableLogging();
    try {
        expect(fn () => app(StartCheckout::class)($payer, $order, 'card'))->toThrow(RuntimeException::class);
    } finally {
        activity()->enableLogging();
    }

    $attempt = PaymentAttempt::query()->where('order_id', $order->id)->firstOrFail();
    expect($attempt->status)->toBe(PaymentAttemptStatus::Initiated)
        ->and($attempt->getRawOriginal('checkout_url'))->toBeNull();
})->group('T-P8-02', 'A3');

it('keeps the payments lang keys in AR and EN in step and covers the keys the code uses', function (): void {
    $ar = require base_path('lang/ar/payments.php');
    $en = require base_path('lang/en/payments.php');

    expect(array_diff(array_keys($ar), array_keys($en)))->toBe([])
        ->and(array_diff(array_keys($en), array_keys($ar)))->toBe([]);

    $used = [];
    foreach (glob(app_path('Domain/Commerce/{Actions,Queries,Payments}/*.php'), GLOB_BRACE) as $file) {
        preg_match_all("/__\\('payments\\.([a-z_]+)'/", (string) file_get_contents($file), $matches);
        $used = [...$used, ...array_filter($matches[1], static fn (string $k): bool => ! str_ends_with($k, '_'))];
    }
    // StartCheckout builds keys dynamically (payments.'.$key), so the refusal keys it can raise are listed here.
    $used = [...$used, 'gateway_unavailable', 'order_invalid', 'market_unavailable', 'method_unavailable', 'checkout_in_progress', 'checkout_failed'];
    foreach (array_unique($used) as $key) {
        expect(array_key_exists($key, $en))->toBeTrue("missing en key payments.{$key}");
    }
    // Method labels are built as payments.method_<key> from config, so check the configured ones directly.
    foreach (['card', 'wallet'] as $method) {
        expect(array_key_exists('method_'.$method, $en))->toBeTrue()
            ->and(array_key_exists('method_'.$method, $ar))->toBeTrue();
    }
})->group('T-P8-02', 'A2');

it('keeps env() only in config and the payment domain free of HTTP imports and final actions', function (): void {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Domain/Commerce'), FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }
        $source = (string) file_get_contents($file->getPathname());
        expect(preg_match('/(?<![\w>:])env\(/', $source))->toBe(0, $file->getFilename())
            ->and(preg_match('/^use (Illuminate\\\\Http\\\\|App\\\\Http\\\\|Livewire\\\\|Filament\\\\)/m', $source))->toBe(0, $file->getFilename());
    }
    expect((new ReflectionClass(StartCheckout::class))->isFinal())->toBeTrue()
        ->and((new ReflectionClass(FakePaymentGateway::class))->isFinal())->toBeTrue();
})->group('T-P8-02', 'A3');
