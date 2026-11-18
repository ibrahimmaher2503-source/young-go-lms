<?php

// T-P8-11 | YG-GAP-PAY-003 | A1

declare(strict_types=1);

use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Actions\ProcessPaymentWebhook;
use App\Domain\Commerce\Actions\ReconcilePayments;
use App\Domain\Commerce\Actions\ReviewPaymentMismatch;
use App\Domain\Commerce\Actions\StartCheckout;
use App\Domain\Commerce\Contracts\PaymentGateway;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\PaymentAttempt;
use App\Domain\Commerce\PaymentAttemptStatus;
use App\Domain\Commerce\PaymentEvent;
use App\Domain\Commerce\PaymentReconciliation;
use App\Domain\Commerce\PaymentReconciliationMismatch;
use App\Domain\Commerce\Payments\FakePaymentGateway;
use App\Domain\Commerce\Payments\NullPaymentGateway;
use App\Domain\Commerce\Payments\PaymentGatewayException;
use App\Domain\Commerce\Payments\PaymobCredentials;
use App\Domain\Commerce\Payments\PaymobGateway;
use App\Domain\Commerce\Payments\VerifiedPaymentEvent;
use App\Domain\Commerce\ProductType;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Shared\Markets\Market;
use App\Models\Course;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Carbon::setTestNow('2026-11-18 09:00:00');
});

afterEach(fn () => Carbon::setTestNow());

/** A learner with an EGP course order and a card attempt that is still open (not yet verified). */
function t811_checkout(int $amountMinor = 25000): array
{
    $payer = User::factory()->create(['status' => 'active'])->assignRole('student');
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة', 'en' => 'Reconciliation Course'],
        'slug_ar' => 't811-ar-'.uniqid(), 'slug_en' => 't811-en-'.uniqid(), 'status' => 'published', 'access_mode' => 'paid',
    ]);
    CoursePrice::query()->create(['course_id' => $course->id, 'market' => 'EG', 'currency' => 'EGP', 'amount_minor' => $amountMinor]);
    $order = app(CreateOrder::class)($payer, Market::Egypt, [new OrderLine(ProductType::Course, $course->id, $payer)], 'it811-'.uniqid());
    $attempt = app(StartCheckout::class)($payer, $order, 'card');

    return compact('payer', 'course', 'order', 'attempt');
}

/** Same as t811_checkout, but the webhook already verified the payment: attempt succeeded, order paid, entitlement granted. */
function t811_paid(int $amountMinor = 25000): array
{
    $checkout = t811_checkout($amountMinor);
    $payload = ['id' => 't811-ok-'.uniqid(), 'reference' => $checkout['attempt']->reference, 'amount_minor' => $amountMinor, 'currency' => 'EGP', 'success' => true];
    app(ProcessPaymentWebhook::class)($payload, FakePaymentGateway::sign($payload));

    return $checkout + ['transactionId' => $payload['id']];
}

function t811_txn(string $id, ?string $reference, int $amountMinor = 25000, string $currency = 'EGP', bool $success = true, bool $pending = false, bool $refunded = false, bool $voided = false): VerifiedPaymentEvent
{
    return new VerifiedPaymentEvent('fake', $id, $reference, null, $amountMinor, $currency, $success, $pending, $refunded, $voided);
}

function t811_queue(VerifiedPaymentEvent ...$events): void
{
    foreach ($events as $event) {
        app(PaymentGateway::class)->queueTransaction($event);
    }
}

function t811_run(string $day = '2026-11-10'): PaymentReconciliation
{
    return app(ReconcilePayments::class)(CarbonImmutable::parse($day));
}

function t811_mismatch(): PaymentReconciliationMismatch
{
    return PaymentReconciliationMismatch::query()->sole();
}

/** Money and status fingerprint the report must never change. */
function t811_state(): array
{
    return [
        'orders' => Order::query()->orderBy('id')->get(['id', 'status'])->map(fn (Order $o): array => [$o->id, (string) $o->getRawOriginal('status')])->all(),
        'attempts' => PaymentAttempt::query()->orderBy('id')->get(['id', 'status'])->map(fn (PaymentAttempt $a): array => [$a->id, (string) $a->getRawOriginal('status')])->all(),
        'entitlements' => Entitlement::query()->count(),
        'payment_events' => PaymentEvent::query()->count(),
    ];
}

// ---------------------------------------------------------------- A1 Report only, one test per mismatch type

it('A1 a clean match counts one matched transaction and no mismatch', function (): void {
    $checkout = t811_paid();
    t811_queue(t811_txn('t811-clean-1', $checkout['attempt']->reference));

    $run = t811_run();

    expect($run->transactions_seen)->toBe(1)
        ->and($run->matched)->toBe(1)
        ->and($run->mismatched)->toBe(0)
        ->and(PaymentReconciliationMismatch::query()->count())->toBe(0);
})->group('T-P8-11', 'A1');

it('A1 an unknown transaction reference is an unmatched_transaction with no_attempt_for_reference', function (): void {
    t811_queue(t811_txn('t811-unk-1', 'NO-SUCH-ATTEMPT'));

    $run = t811_run();

    expect($run->mismatched)->toBe(1)
        ->and(t811_mismatch()->type->value)->toBe('unmatched_transaction')
        ->and(t811_mismatch()->reason)->toBe('no_attempt_for_reference')
        ->and(t811_mismatch()->attempt_reference)->toBe('NO-SUCH-ATTEMPT');
})->group('T-P8-11', 'A1');

it('A1 a different amount or currency is an amount_mismatch', function (): void {
    $checkout = t811_paid(25000);
    t811_queue(t811_txn('t811-amt-1', $checkout['attempt']->reference, amountMinor: 1000));

    t811_run();

    expect(t811_mismatch()->type->value)->toBe('amount_mismatch')
        ->and(t811_mismatch()->reason)->toBe('amount_or_currency_differs')
        ->and(t811_mismatch()->gateway_amount_minor)->toBe(1000)
        ->and(t811_mismatch()->local_amount_minor)->toBe(25000);
})->group('T-P8-11', 'A1');

it('A1 a currency difference alone is also an amount_mismatch', function (): void {
    $checkout = t811_paid(25000);
    t811_queue(t811_txn('t811-cur-1', $checkout['attempt']->reference, currency: 'USD'));

    t811_run();

    expect(t811_mismatch()->type->value)->toBe('amount_mismatch')
        ->and(t811_mismatch()->reason)->toBe('amount_or_currency_differs');
})->group('T-P8-11', 'A1');

it('A1 success at the gateway while our attempt is not succeeded is a status_mismatch paid_at_gateway_not_locally', function (): void {
    $checkout = t811_checkout(25000);
    t811_queue(t811_txn('t811-pgl-1', $checkout['attempt']->reference));

    t811_run();

    expect(t811_mismatch()->type->value)->toBe('status_mismatch')
        ->and(t811_mismatch()->reason)->toBe('paid_at_gateway_not_locally')
        ->and($checkout['attempt']->fresh()->status)->toBe(PaymentAttemptStatus::Created)
        ->and($checkout['order']->fresh()->status)->toBe(OrderStatus::Pending);
})->group('T-P8-11', 'A1');

it('A1 a refund at the gateway is refunded_at_gateway with reason refunded', function (): void {
    $checkout = t811_paid();
    t811_queue(t811_txn('t811-ref-1', $checkout['attempt']->reference, refunded: true));

    t811_run();

    expect(t811_mismatch()->type->value)->toBe('refunded_at_gateway')
        ->and(t811_mismatch()->reason)->toBe('refunded');
})->group('T-P8-11', 'A1');

it('A1 a void at the gateway is refunded_at_gateway with reason voided', function (): void {
    $checkout = t811_paid();
    t811_queue(t811_txn('t811-void-1', $checkout['attempt']->reference, voided: true));

    t811_run();

    expect(t811_mismatch()->type->value)->toBe('refunded_at_gateway')
        ->and(t811_mismatch()->reason)->toBe('voided');
})->group('T-P8-11', 'A1');

it('A1 a failed try at the gateway while we show paid is a status_mismatch failed_at_gateway_paid_locally', function (): void {
    $checkout = t811_paid();
    t811_queue(t811_txn('t811-fail-1', $checkout['attempt']->reference, success: false));

    t811_run();

    expect(t811_mismatch()->type->value)->toBe('status_mismatch')
        ->and(t811_mismatch()->reason)->toBe('failed_at_gateway_paid_locally');
})->group('T-P8-11', 'A1');

it('A1 a failed try beside a success for the same reference is just matched, not a mismatch', function (): void {
    $checkout = t811_paid();
    t811_queue(
        t811_txn('t811-fail-first', $checkout['attempt']->reference, success: false),
        t811_txn('t811-ok-second', $checkout['attempt']->reference),
    );

    $run = t811_run();

    expect($run->mismatched)->toBe(0)
        ->and($run->matched)->toBe(2)
        ->and(PaymentReconciliationMismatch::query()->count())->toBe(0);
})->group('T-P8-11', 'A1');

it('A1 a locally succeeded attempt missing from the gateway listing is missing_at_gateway', function (): void {
    $checkout = t811_paid();
    PaymentAttempt::query()->whereKey($checkout['attempt']->id)->update(['updated_at' => '2026-11-10 12:00:00']);

    t811_run('2026-11-10');

    expect(t811_mismatch()->type->value)->toBe('missing_at_gateway')
        ->and(t811_mismatch()->reason)->toBe('not_in_gateway_listing')
        ->and(t811_mismatch()->payment_attempt_id)->toBe($checkout['attempt']->id);
})->group('T-P8-11', 'A1');

it('A1 a pending gateway transaction is skipped: neither matched nor mismatched', function (): void {
    $checkout = t811_checkout();
    t811_queue(t811_txn('t811-pend-1', $checkout['attempt']->reference, success: false, pending: true));

    $run = t811_run();

    expect($run->transactions_seen)->toBe(1)
        ->and($run->matched)->toBe(0)
        ->and($run->mismatched)->toBe(0)
        ->and(PaymentReconciliationMismatch::query()->count())->toBe(0);
})->group('T-P8-11', 'A1');

it('A1 the report never changes orders, attempts, entitlements or payment events across every mismatch type', function (): void {
    $amount = t811_paid(25000);
    $refunded = t811_paid(30000);
    $declined = t811_paid(31000);
    $unknown = t811_paid(32000);
    PaymentAttempt::query()->whereKey($unknown['attempt']->id)->update(['updated_at' => '2026-11-10 12:00:00']);
    $pendingOrder = t811_checkout(33000);
    t811_queue(
        t811_txn('t811-snap-amt', $amount['attempt']->reference, amountMinor: 1),
        t811_txn('t811-snap-ref', $refunded['attempt']->reference, refunded: true),
        t811_txn('t811-snap-fail', $declined['attempt']->reference, success: false),
        t811_txn('t811-snap-unk', 'UNKNOWN-REF'),
        t811_txn('t811-snap-pgl', $pendingOrder['attempt']->reference),
    );
    $before = t811_state();

    $run = t811_run();

    expect(PaymentReconciliationMismatch::query()->count())->toBeGreaterThanOrEqual(5)
        ->and($run->mismatched)->toBe(PaymentReconciliationMismatch::query()->count())
        ->and(t811_state())->toBe($before)
        ->and($amount['order']->fresh()->status)->toBe(OrderStatus::Paid)
        ->and($pendingOrder['order']->fresh()->status)->toBe(OrderStatus::Pending);
})->group('T-P8-11', 'A1');

// ---------------------------------------------------------------- Idempotency

it('A1 running the same day twice keeps one run and one row per mismatch, and a reviewed mismatch stays reviewed', function (): void {
    $checkout = t811_paid(25000);
    t811_queue(
        t811_txn('t811-idem-amt', $checkout['attempt']->reference, amountMinor: 1000),
        t811_txn('t811-idem-unk', 'UNKNOWN-IDEM'),
    );

    t811_run();
    t811_run();

    expect(PaymentReconciliation::query()->where('run_date', '2026-11-10')->count())->toBe(1)
        ->and(PaymentReconciliationMismatch::query()->count())->toBe(2)
        ->and(Activity::query()->where('event', 'payment.reconciliation_mismatch_found')->count())->toBe(2);

    $finance = User::factory()->create(['status' => 'active'])->assignRole('finance');
    $amountRow = PaymentReconciliationMismatch::query()->where('type', 'amount_mismatch')->sole();
    app(ReviewPaymentMismatch::class)($finance, $amountRow);

    t811_run();

    expect($amountRow->fresh()->status->value)->toBe('reviewed')
        ->and(PaymentReconciliationMismatch::query()->count())->toBe(2)
        ->and(Activity::query()->where('event', 'payment.reconciliation_mismatch_found')->count())->toBe(2)
        ->and(Activity::query()->where('event', 'payment.reconciliation_mismatch_reviewed')->count())->toBe(1);
})->group('T-P8-11', 'A1');

it('A1 a gateway failure throws PaymentGatewayException and writes no run or mismatch', function (): void {
    t811_queue(t811_txn('t811-fail-gw', 'UNKNOWN-GW'));
    app(PaymentGateway::class)->failNext('Gateway unreachable.');

    expect(fn () => t811_run())->toThrow(PaymentGatewayException::class)
        ->and(PaymentReconciliation::query()->count())->toBe(0)
        ->and(PaymentReconciliationMismatch::query()->count())->toBe(0);
})->group('T-P8-11', 'A1');

it('A1 the mismatch audit carries no secrets or card data', function (): void {
    t811_queue(t811_txn('t811-secret-1', 'UNKNOWN-SECRET'));

    t811_run();

    $dump = json_encode(Activity::query()->where('event', 'payment.reconciliation_mismatch_found')->get()->toArray());
    expect($dump)->not->toContain('egy_sk_')
        ->and($dump)->not->toContain('pan');
})->group('T-P8-11', 'A1');

// ---------------------------------------------------------------- Command

it('A1 payments:reconcile defaults to yesterday when no date is given', function (): void {
    Carbon::setTestNow('2026-11-18 09:00:00');

    $this->artisan('payments:reconcile')->assertExitCode(0);

    expect(PaymentReconciliation::query()->whereDate('run_date', '2026-11-17')->count())->toBe(1);
})->group('T-P8-11', 'A1');

it('A1 payments:reconcile honours --date and refuses an invalid date with exit 2', function (): void {
    $this->artisan('payments:reconcile', ['--date' => '2026-11-10'])->assertExitCode(0);
    expect(PaymentReconciliation::query()->whereDate('run_date', '2026-11-10')->count())->toBe(1);

    $this->artisan('payments:reconcile', ['--date' => 'not-a-date'])->assertExitCode(2);
})->group('T-P8-11', 'A1');

it('A1 payments:reconcile skips with exit 0 and no rows when the gateway is not configured', function (): void {
    app()->instance(PaymentGateway::class, new NullPaymentGateway);

    $this->artisan('payments:reconcile', ['--date' => '2026-11-10'])
        ->expectsOutputToContain('not configured; reconciliation skipped')
        ->assertExitCode(0);

    expect(PaymentReconciliation::query()->count())->toBe(0);
})->group('T-P8-11', 'A1');

it('A1 payments:reconcile exits 1 on a gateway error and prints the counts on success', function (): void {
    app(PaymentGateway::class)->failNext('Gateway unreachable.');
    $this->artisan('payments:reconcile', ['--date' => '2026-11-10'])->assertExitCode(1);
    expect(PaymentReconciliation::query()->count())->toBe(0);

    $checkout = t811_paid();
    t811_queue(t811_txn('t811-cmd-1', $checkout['attempt']->reference));

    $this->artisan('payments:reconcile', ['--date' => '2026-11-10'])
        ->expectsOutputToContain('1 transactions, 1 matched, 0 mismatches')
        ->assertExitCode(0);
})->group('T-P8-11', 'A1');

it('A1 the schedule lists payments:reconcile daily at 04:30 without overlap', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(static fn ($e): bool => str_contains((string) $e->command, 'payments:reconcile'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('30 4 * * *')
        ->and($event->description)->toBe('payments:reconcile')
        ->and($event->expiresAt)->not->toBeNull();
})->group('T-P8-11', 'A1');

// ---------------------------------------------------------------- Review action

it('A1 review needs an active user with payment.approve_manual, and a second review is a no-op', function (): void {
    $checkout = t811_checkout();
    t811_queue(t811_txn('t811-rev-1', 'UNKNOWN-REV'));
    t811_run();
    $mismatch = t811_mismatch();
    $review = app(ReviewPaymentMismatch::class);

    $viewer = User::factory()->create(['status' => 'active'])->assignRole('content_manager');
    $viewer->givePermissionTo('payment.view');
    $inactive = User::factory()->create(['status' => 'inactive'])->assignRole('finance');

    expect(fn () => $review($viewer, $mismatch))->toThrow(AuthorizationException::class)
        ->and(fn () => $review($inactive, $mismatch))->toThrow(AuthorizationException::class)
        ->and($mismatch->fresh()->status->value)->toBe('open');

    $finance = User::factory()->create(['status' => 'active'])->assignRole('finance');
    $review($finance, $mismatch);
    $review($finance, $mismatch->fresh());

    expect($mismatch->fresh()->status->value)->toBe('reviewed')
        ->and($mismatch->fresh()->reviewed_by)->toBe($finance->id)
        ->and(Activity::query()->where('event', 'payment.reconciliation_mismatch_reviewed')->count())->toBe(1)
        ->and($checkout['order']->fresh()->status)->toBe(OrderStatus::Pending);
})->group('T-P8-11', 'A1');

// ---------------------------------------------------------------- Gateways

it('A1 the Null gateway lists no transactions', function (): void {
    Http::fake();

    expect((new NullPaymentGateway)->listTransactions(CarbonImmutable::parse('2026-11-10'), CarbonImmutable::parse('2026-11-10')->endOfDay()))->toBe([]);
    Http::assertNothingSent();
})->group('T-P8-11', 'A1');

it('A1 the Fake gateway returns every queued transaction and records the call', function (): void {
    t811_queue(t811_txn('t811-list-1', 'REF-1'), t811_txn('t811-list-2', 'REF-2'));
    $gateway = app(PaymentGateway::class);

    $listed = $gateway->listTransactions(CarbonImmutable::parse('2026-11-10'), CarbonImmutable::parse('2026-11-10')->endOfDay());

    expect($listed)->toHaveCount(2)
        ->and(collect($gateway->calls)->pluck('method')->all())->toContain('listTransactions');
})->group('T-P8-11', 'A1');

it('A1 Paymob without complete credentials returns [] and sends no request', function (): void {
    Http::fake();
    $gateway = new PaymobGateway(new PaymobCredentials('https://accept.paymob.com', null, null, null, null, [], 10));

    expect($gateway->listTransactions(CarbonImmutable::parse('2026-11-10'), CarbonImmutable::parse('2026-11-10')->endOfDay()))->toBe([]);
    Http::assertNothingSent();
})->group('T-P8-11', 'A1');

function t811_paymob(): PaymobGateway
{
    return new PaymobGateway(new PaymobCredentials(
        'https://accept.paymob.com', 'egy_sk_test_t811', 'egy_pk_test_t811', 'api_key_t811', 'hmac_t811', ['card' => '10'], 10,
    ));
}

it('A1 Paymob with credentials authenticates, keeps only rows inside the window and sends the token only in the header', function (): void {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok_t811_secret']),
        '*/api/acceptance/transactions*' => Http::response(['results' => [
            ['id' => 501, 'created_at' => '2026-11-10T10:00:00', 'amount_cents' => 25000, 'currency' => 'EGP', 'success' => true, 'order' => ['merchant_order_id' => 'ATT-IN']],
            ['id' => 502, 'created_at' => '2026-11-09T23:00:00', 'amount_cents' => 25000, 'currency' => 'EGP', 'success' => true, 'order' => ['merchant_order_id' => 'ATT-OLD']],
            ['id' => 503, 'created_at' => '2026-11-11T00:30:00', 'amount_cents' => 25000, 'currency' => 'EGP', 'success' => true, 'order' => ['merchant_order_id' => 'ATT-NEW']],
        ], 'next' => null]),
    ]);

    $listed = t811_paymob()->listTransactions(CarbonImmutable::parse('2026-11-10'), CarbonImmutable::parse('2026-11-10')->endOfDay());

    expect($listed)->toHaveCount(1)
        ->and($listed[0]->eventId)->toBe('501');
    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/api/auth/tokens'));
    Http::assertSent(fn ($request): bool => $request->hasHeader('Authorization', 'Bearer tok_t811_secret') && str_contains($request->url(), 'page=1'));
    Http::assertSent(fn ($request): bool => ! str_contains($request->url(), 'tok_t811_secret') && ! str_contains($request->url(), 'api_key_t811'));
})->group('T-P8-11', 'A1');

it('A1 Paymob paging stops after a page that is entirely older than the window', function (): void {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok_t811']),
        '*/api/acceptance/transactions*' => Http::response(['results' => [
            ['id' => 601, 'created_at' => '2026-11-08T10:00:00', 'amount_cents' => 1, 'currency' => 'EGP', 'success' => true],
        ], 'next' => 'more']),
    ]);

    t811_paymob()->listTransactions(CarbonImmutable::parse('2026-11-10'), CarbonImmutable::parse('2026-11-10')->endOfDay());

    $listCalls = collect(Http::recorded())->filter(fn (array $pair): bool => str_contains($pair[0]->url(), '/api/acceptance/transactions'))->count();
    expect($listCalls)->toBe(1);
})->group('T-P8-11', 'A1');

it('A1 Paymob paging stops at the 50 page cap', function (): void {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok_t811']),
        '*/api/acceptance/transactions*' => Http::response(['results' => [
            ['id' => 701, 'created_at' => '2026-11-10T10:00:00', 'amount_cents' => 1, 'currency' => 'EGP', 'success' => true],
        ], 'next' => 'more']),
    ]);

    t811_paymob()->listTransactions(CarbonImmutable::parse('2026-11-10'), CarbonImmutable::parse('2026-11-10')->endOfDay());

    $listCalls = collect(Http::recorded())->filter(fn (array $pair): bool => str_contains($pair[0]->url(), '/api/acceptance/transactions'))->count();
    expect($listCalls)->toBe(50);
})->group('T-P8-11', 'A1');

it('A1 a Paymob HTTP failure throws PaymentGatewayException without the secret in the message', function (): void {
    Http::fake([
        '*/api/auth/tokens' => Http::response(['token' => 'tok_t811_secret']),
        '*/api/acceptance/transactions*' => Http::response(['detail' => 'api_key_t811 rejected'], 500),
    ]);

    try {
        t811_paymob()->listTransactions(CarbonImmutable::parse('2026-11-10'), CarbonImmutable::parse('2026-11-10')->endOfDay());
        $message = '';
    } catch (PaymentGatewayException $exception) {
        $message = $exception->getMessage();
    }

    expect($message)->not->toBe('')
        ->and($message)->not->toContain('api_key_t811')
        ->and($message)->not->toContain('tok_t811_secret');
})->group('T-P8-11', 'A1');
