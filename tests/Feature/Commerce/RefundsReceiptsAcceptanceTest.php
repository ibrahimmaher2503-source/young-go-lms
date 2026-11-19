<?php

declare(strict_types=1);

// T-P8-06 | YG-GAP-PAY-005, PRD 17.3, O-023 | A1 A2 A3

use App\Domain\Commerce\Actions\ApproveManualPaymentProof;
use App\Domain\Commerce\Actions\ApproveRefund;
use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Actions\ExecuteRefund;
use App\Domain\Commerce\Actions\GrantOrderEntitlements;
use App\Domain\Commerce\Actions\IssueReceipt;
use App\Domain\Commerce\Actions\RejectRefund;
use App\Domain\Commerce\Actions\RequestRefund;
use App\Domain\Commerce\Actions\SubmitEReceipt;
use App\Domain\Commerce\Actions\SubmitManualPaymentProof;
use App\Domain\Commerce\Actions\TransitionOrder;
use App\Domain\Commerce\Contracts\EReceiptProvider;
use App\Domain\Commerce\Contracts\PaymentGateway;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Commerce\EReceipt;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderItem;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\PaymentEvent;
use App\Domain\Commerce\Payments\FakePaymentGateway;
use App\Domain\Commerce\Payments\NullPaymentGateway;
use App\Domain\Commerce\ProductType;
use App\Domain\Commerce\Queries\PaymentHistory;
use App\Domain\Commerce\ReceiptPdf;
use App\Domain\Commerce\Receipts\FakeEReceiptProvider;
use App\Domain\Commerce\Receipts\NullEReceiptProvider;
use App\Domain\Commerce\Refund;
use App\Domain\Commerce\RefundStatus;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Shared\Markets\Market;
use App\Filament\Resources\Refunds\Pages\ListRefunds;
use App\Filament\Resources\Refunds\RefundResource;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Support\ActivityLogStatus;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');
    config(['refunds.revoke_access' => 'full']);
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function p806Staff(string $role = 'finance'): User
{
    return User::factory()->create(['status' => 'active'])->assignRole($role);
}

function p806Payer(): User
{
    return User::factory()->create(['status' => 'active'])->assignRole('student');
}

/** @return list<Course> */
function p806Courses(array $prices): array
{
    $courses = [];
    foreach ($prices as $price) {
        $course = Course::query()->create([
            'title' => ['ar' => 'دورة', 'en' => 'Refund course '.uniqid()],
            'slug_ar' => 'p806-ar-'.uniqid(), 'slug_en' => 'p806-en-'.uniqid(),
            'status' => 'published', 'access_mode' => 'paid',
        ]);
        CoursePrice::query()->create(['course_id' => $course->id, 'market' => 'EG', 'currency' => 'EGP', 'amount_minor' => $price]);
        $courses[] = $course;
    }

    return $courses;
}

/** A pending Egyptian order (default one course of 250.00 EGP) for the learner. */
function p806Order(User $payer, ?User $learner = null, int $price = 25000): Order
{
    [$course] = p806Courses([$price]);

    return app(CreateOrder::class)($payer, Market::Egypt, [new OrderLine(ProductType::Course, $course->id, $learner ?? $payer)], 'p806-'.uniqid());
}

/** Moves the order to paid and grants its courses with the given source (manual_payment by default). */
function p806Paid(Order $order, EntitlementSource $source = EntitlementSource::ManualPayment): Order
{
    app(TransitionOrder::class)($order, OrderStatus::Paid);
    // A manual grant needs an actor with manual_entitlement.grant; a gateway purchase has none.
    app(GrantOrderEntitlements::class)($order->refresh(), $source, $source === EntitlementSource::ManualPayment ? p806Staff() : null);

    return $order->refresh();
}

/** A paid order through a fulfilled gateway payment (a payment event row for the fake gateway). */
function p806GatewayPaid(Order $order): Order
{
    $n = uniqid();
    PaymentEvent::query()->create([
        'gateway' => 'fake', 'event_key' => 'p806-ev-'.$n, 'provider_event_id' => 'fake_txn_'.$n, 'state' => 'success',
        'order_id' => $order->getKey(), 'amount_minor' => $order->total_minor, 'currency' => $order->currency,
        'outcome' => 'fulfilled', 'processed_at' => now(), 'payload' => null,
    ]);

    return p806Paid($order, EntitlementSource::Purchase);
}

function p806Request(User $actor, Order $order, int $amount, string $reason = 'Customer asked for a refund'): Refund
{
    return app(RequestRefund::class)($actor, $order, $amount, $reason);
}

function p806Refused(Closure $call): ValidationException
{
    try {
        $call();
    } catch (ValidationException $refusal) {
        return $refusal;
    }

    throw new RuntimeException('Expected a validation refusal, nothing was refused.');
}

/** A role with exactly the given permissions. */
function p806Role(string $name, array $permissions): void
{
    Role::findOrCreate($name, 'web')->syncPermissions($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

/** The rows that must never change when a refund is recorded (order money, attempts, events, proofs). */
function p806Original(Order $order): array
{
    $id = $order->getKey();

    return [
        'order' => (array) DB::table('orders')->where('id', $id)->first(['subtotal_minor', 'discount_minor', 'total_minor', 'currency', 'paid_at']),
        'attempts' => DB::table('payment_attempts')->where('order_id', $id)->get()->map(static fn ($r): array => (array) $r)->all(),
        'events' => DB::table('payment_events')->where('order_id', $id)->get()->map(static fn ($r): array => (array) $r)->all(),
        'proofs' => DB::table('manual_payment_proofs')->where('order_id', $id)->get()->map(static fn ($r): array => (array) $r)->all(),
    ];
}

function p806Revoked(Order $order): int
{
    return Entitlement::query()->where('source_ref', 'order:'.$order->reference)->where('status', 'revoked')->count();
}

function p806Active(Order $order): int
{
    return Entitlement::query()->where('source_ref', 'order:'.$order->reference)->where('status', 'active')->count();
}

// ---------------------------------------------------------------------------
// A1: the original transaction is untouched; over-refunds are refused
// ---------------------------------------------------------------------------

it('partial then full refund leaves the original money rows byte-identical and walks the order to refunded', function (): void {
    $payer = p806Payer();
    $order = p806Order($payer);
    $proof = app(SubmitManualPaymentProof::class)($payer, $order, [
        'payer_name' => 'Hoda Payer', 'sender_account' => '01099887766', 'transfer_reference' => 'ORIG-REF-0001',
        'amount_minor' => $order->total_minor, 'transferred_at' => now()->subMinutes(10),
    ], UploadedFile::fake()->image('slip.png', 160, 120));
    app(ApproveManualPaymentProof::class)(p806Staff(), $proof);
    $order->refresh();
    $original = p806Original($order);
    $finance = p806Staff();

    $partial = p806Request($finance, $order, 10000);
    app(ApproveRefund::class)($finance, $partial);
    app(ExecuteRefund::class)($finance, $partial->refresh(), 'BANK-TRX-0001');

    expect($order->refresh()->status)->toBe(OrderStatus::PartiallyRefunded)
        ->and(p806Original($order))->toEqual($original);

    $rest = p806Request($finance, $order, 15000);
    app(ApproveRefund::class)($finance, $rest);
    app(ExecuteRefund::class)($finance, $rest->refresh(), 'BANK-TRX-0002');

    $fresh = $order->refresh();
    expect($fresh->status)->toBe(OrderStatus::Refunded)
        ->and($fresh->total_minor)->toBe(25000)
        ->and(p806Original($order))->toEqual($original)
        ->and(Refund::query()->where('order_id', $order->getKey())->where('status', RefundStatus::Executed->value)->count())->toBe(2)
        ->and(EReceipt::query()->where('kind', 'refund')->where('order_id', $order->getKey())->count())->toBe(2)
        ->and(Activity::query()->where('event', 'refund.executed')->count())->toBe(2);
})->group('T-P8-06', 'A1')->group('payments');

it('refuses an over-refund, a cumulative over-refund and one that exceeds an open requested refund with amount_exceeds', function (): void {
    $payer = p806Payer();
    $order = p806Paid(p806Order($payer));
    $finance = p806Staff();
    $partial = p806Request($finance, $order, 10000);
    app(ApproveRefund::class)($finance, $partial);
    app(ExecuteRefund::class)($finance, $partial->refresh(), 'BANK-TRX-0100');

    $single = p806Refused(fn () => p806Request($finance, $order->refresh(), 15001));
    expect($single->errors()['amount'][0])->toBe(__('refunds.amount_exceeds'));

    $open = p806Request($finance, $order->refresh(), 10000);
    $open = p806Request($finance, $order->refresh(), 5000);
    $cumulative = p806Refused(fn () => p806Request($finance, $order->refresh(), 1));
    expect($cumulative->errors()['amount'][0])->toBe(__('refunds.amount_exceeds'));

    expect(Refund::query()->where('order_id', $order->getKey())->count())->toBe(3)
        ->and($open->status)->toBe(RefundStatus::Requested);
})->group('T-P8-06', 'A1')->group('payments');

// ---------------------------------------------------------------------------
// A2: receipts render (asserted with Pdf::fake, no Chromium)
// ---------------------------------------------------------------------------

it('the Arabic receipt builds the receipts.pdf view with rtl, Arabic labels, the embedded font and private headers', function (): void {
    Pdf::fake();
    $order = p806Paid(p806Order(p806Payer()));
    $receipt = app(IssueReceipt::class)($order);

    $builder = app(ReceiptPdf::class)->build($receipt, 'ar');
    $builder->generatePdfContent();

    $builder->assertViewIs('receipts.pdf');
    $builder->assertViewHas('dir', 'rtl');
    $builder->assertSee(__('receipts.pdf.heading', [], 'ar'));
    $builder->assertSee('data:font/ttf;base64,');

    $headers = (new ReflectionProperty(PdfBuilder::class, 'responseHeaders'))->getValue($builder);
    expect($headers)->toMatchArray(['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
})->group('T-P8-06', 'A2')->group('payments');

it('the English receipt is left-to-right and the HTTP route answers with the receipt for lang=en and an unknown lang', function (): void {
    Pdf::fake();
    $payer = p806Payer();
    $order = p806Paid(p806Order($payer));
    $receipt = app(IssueReceipt::class)($order);

    $builder = app(ReceiptPdf::class)->build($receipt, 'en');
    $builder->generatePdfContent();
    $builder->assertViewHas('dir', 'ltr');
    $builder->assertSee(__('receipts.pdf.heading', [], 'en'));

    $this->actingAs($payer)->get(route('payments.receipt', ['order' => $order, 'lang' => 'en']))->assertOk();
    $this->actingAs($payer)->get(route('payments.receipt', ['order' => $order, 'lang' => 'fr']))->assertOk();
})->group('T-P8-06', 'A2')->group('payments');

it('ReceiptPdf data carries amounts and snapshot titles in both locales', function (): void {
    $order = p806Paid(p806Order(p806Payer()));
    $receipt = app(IssueReceipt::class)($order);

    $ar = app(ReceiptPdf::class)->data($receipt, 'ar');
    $en = app(ReceiptPdf::class)->data($receipt, 'en');

    expect($ar['number'])->toBe('RC-'.$order->reference)
        ->and($ar['dir'])->toBe('rtl')
        ->and($en['dir'])->toBe('ltr')
        ->and($ar['total'])->not->toBeEmpty()
        ->and($en['total'])->toContain('250')
        ->and($en['lines'][0]['title'])->not->toBe('')
        ->and($ar['lines'][0]['net'])->not->toBeEmpty();
})->group('T-P8-06', 'A2')->group('payments');

// ---------------------------------------------------------------------------
// A3: e-receipt adapter is disabled by default
// ---------------------------------------------------------------------------

it('the e-receipt adapter is the disabled null provider by default', function (): void {
    expect(config('e_receipts.driver'))->toBe('null')
        ->and(app(EReceiptProvider::class))->toBeInstanceOf(NullEReceiptProvider::class)
        ->and(app(EReceiptProvider::class)->isEnabled())->toBeFalse();
})->group('T-P8-06', 'YG-GAP-PAY-005', 'A3')->group('payments');

it('finance cannot submit an e-receipt while the adapter is disabled; the receipt stays not_submitted and nothing is audited', function (): void {
    $order = p806Paid(p806Order(p806Payer()));
    $receipt = app(IssueReceipt::class)($order);

    $refusal = p806Refused(fn () => app(SubmitEReceipt::class)(p806Staff(), $receipt));

    expect($refusal->errors()['e_receipt'][0])->toBe(__('refunds.e_receipt_disabled'))
        ->and($receipt->refresh()->status->value)->toBe('not_submitted')
        ->and(Activity::query()->where('event', 'e_receipt.submitted')->count())->toBe(0);
})->group('T-P8-06', 'A3')->group('payments');

it('the fake e-receipt driver is ignored outside local and testing', function (): void {
    config(['e_receipts.driver' => 'fake']);
    app()->detectEnvironment(static fn (): string => 'production');
    app()->forgetInstance(EReceiptProvider::class);

    try {
        expect(app(EReceiptProvider::class))->toBeInstanceOf(NullEReceiptProvider::class);
    } finally {
        app()->detectEnvironment(static fn (): string => 'testing');
        app()->forgetInstance(EReceiptProvider::class);
    }
})->group('T-P8-06', 'A3')->group('payments');

it('with the fake driver in testing the submit works once, is idempotent and stores provider and uuid', function (): void {
    config(['e_receipts.driver' => 'fake']);
    app()->forgetInstance(EReceiptProvider::class);
    expect(app(EReceiptProvider::class))->toBeInstanceOf(FakeEReceiptProvider::class);

    $order = p806Paid(p806Order(p806Payer()));
    $receipt = app(IssueReceipt::class)($order);
    $finance = p806Staff();

    app(SubmitEReceipt::class)($finance, $receipt);
    app(SubmitEReceipt::class)($finance, $receipt->refresh());

    $fresh = $receipt->refresh();
    expect($fresh->status->value)->toBe('submitted')
        ->and($fresh->provider)->toBe('fake')
        ->and($fresh->provider_uuid)->toBe('fake-doc-RC-'.$order->reference)
        ->and(Activity::query()->where('event', 'e_receipt.submitted')->count())->toBe(1);
})->group('T-P8-06', 'A3')->group('payments');

it('a learner without payment.approve_manual gets AuthorizationException when submitting an e-receipt', function (): void {
    $order = p806Paid(p806Order(p806Payer()));
    $receipt = app(IssueReceipt::class)($order);

    expect(fn () => app(SubmitEReceipt::class)(p806Payer(), $receipt))->toThrow(AuthorizationException::class);
})->group('T-P8-06', 'A3')->group('payments');

it('the seller tax id in the receipt snapshot is null by default', function (): void {
    config(['e_receipts.seller.tax_id' => null]);
    $receipt = app(IssueReceipt::class)(p806Paid(p806Order(p806Payer())));

    expect($receipt->snapshot['seller']['tax_id'])->toBeNull()
        ->and($receipt->snapshot['totals']['tax_minor'])->toBeNull()
        ->and($receipt->tax_minor)->toBeNull();
})->group('T-P8-06', 'A3')->group('payments');

// ---------------------------------------------------------------------------
// Permissions
// ---------------------------------------------------------------------------

it('learner, parent, instructor, support, inactive staff and a view-only role cannot request, approve, reject or execute', function (): void {
    p806Role('p806_view_only', ['payment.view']);
    $payer = p806Payer();
    $order = p806Paid(p806Order($payer));
    $refund = p806Request(p806Staff(), $order, 5000);

    $outsiders = [
        p806Payer(),
        User::factory()->create(['status' => 'active'])->assignRole('parent'),
        User::factory()->create(['status' => 'active'])->assignRole('instructor_academy'),
        User::factory()->create(['status' => 'active'])->assignRole('support'),
        User::factory()->create(['status' => 'inactive'])->assignRole('finance'),
        User::factory()->create(['status' => 'active'])->assignRole('p806_view_only'),
    ];

    foreach ($outsiders as $outsider) {
        expect(fn () => p806Request($outsider, $order, 1000))->toThrow(AuthorizationException::class);
        expect(fn () => app(ApproveRefund::class)($outsider, $refund))->toThrow(AuthorizationException::class);
        expect(fn () => app(RejectRefund::class)($outsider, $refund, 'Not allowed to reject this refund.'))->toThrow(AuthorizationException::class);
        expect(fn () => app(ExecuteRefund::class)($outsider, $refund, 'BANK-TRX-0200'))->toThrow(AuthorizationException::class);
    }

    expect($refund->refresh()->status)->toBe(RefundStatus::Requested);
})->group('T-P8-06', 'A2')->group('payments');

it('execute needs manual_entitlement.grant when the refund revokes access: a payment.refund-only role is refused and nothing moves', function (): void {
    p806Role('p806_refund_only', ['payment.refund']);
    $order = p806Paid(p806Order(p806Payer()));
    $refund = p806Request(p806Staff(), $order, 25000);
    app(ApproveRefund::class)(p806Staff(), $refund);
    $refundOnly = User::factory()->create(['status' => 'active'])->assignRole('p806_refund_only');

    expect(fn () => app(ExecuteRefund::class)($refundOnly, $refund->refresh(), 'BANK-TRX-0300'))->toThrow(AuthorizationException::class);

    expect($refund->refresh()->status)->toBe(RefundStatus::Approved)
        ->and($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and(p806Active($order))->toBe(1);
})->group('T-P8-06', 'A2')->group('payments');

// ---------------------------------------------------------------------------
// Methods and the gateway path
// ---------------------------------------------------------------------------

it('a manual-paid order gets the manual method and no gateway; a fulfilled gateway payment gets the gateway and its transaction id', function (): void {
    $manual = p806Request(p806Staff(), p806Paid(p806Order(p806Payer())), 1000);
    $gateway = p806Request(p806Staff(), p806GatewayPaid(p806Order(p806Payer())), 1000);

    expect($manual->method->value)->toBe('manual')
        ->and($manual->gateway)->toBeNull()
        ->and($gateway->method->value)->toBe('gateway')
        ->and($gateway->gateway)->toBe('fake')
        ->and($gateway->provider_transaction_id)->toStartWith('fake_txn_');
})->group('T-P8-06', 'A1')->group('payments');

it('a gateway refund calls the fake gateway once with amount, currency, order reference and the refund reference as key', function (): void {
    $order = p806GatewayPaid(p806Order(p806Payer()));
    $finance = p806Staff();
    $refund = p806Request($finance, $order, 10000);
    app(ApproveRefund::class)($finance, $refund);

    $gateway = app(PaymentGateway::class);
    expect($gateway)->toBeInstanceOf(FakePaymentGateway::class);
    app(ExecuteRefund::class)($finance, $refund->refresh());

    $calls = array_values(array_filter($gateway->calls, static fn (array $call): bool => $call['method'] === 'refund'));
    expect($calls)->toHaveCount(1)
        ->and($calls[0]['args']['amount_minor'])->toBe(10000)
        ->and($calls[0]['args']['currency'])->toBe('EGP')
        ->and($calls[0]['args']['order_reference'])->toBe($order->reference)
        ->and($calls[0]['args']['reference'])->toBe($refund->reference)
        ->and($refund->refresh()->status)->toBe(RefundStatus::Executed)
        ->and($refund->provider_refund_id)->toBe('fake_refund_'.$refund->reference);
})->group('T-P8-06', 'A1')->group('payments');

it('a failing gateway refund marks the refund failed with a code, keeps the order and access, and allows a new request', function (): void {
    $payer = p806Payer();
    $order = p806GatewayPaid(p806Order($payer));
    $finance = p806Staff();
    $refund = p806Request($finance, $order, 25000);
    app(ApproveRefund::class)($finance, $refund);
    app(PaymentGateway::class)->failNext('Provider down', 'gateway_error');

    $refusal = p806Refused(fn () => app(ExecuteRefund::class)($finance, $refund->refresh()));

    expect($refusal->errors()['refund'][0])->toBe(__('refunds.gateway_failed'));
    $failed = $refund->refresh();
    expect($failed->status)->toBe(RefundStatus::Failed)
        ->and($failed->failure_code)->toBe('gateway_error')
        ->and($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and(p806Active($order))->toBe(1)
        ->and(EReceipt::query()->where('kind', 'refund')->count())->toBe(0)
        ->and(Activity::query()->where('event', 'refund.failed')->count())->toBe(1);

    $retry = p806Request($finance, $order, 25000);
    expect($retry->status)->toBe(RefundStatus::Requested);
})->group('T-P8-06', 'A1')->group('payments');

it('a null gateway or a gateway that cannot refund gives gateway_unavailable and the refund stays approved', function (string $kind): void {
    $payer = p806Payer();
    $order = p806GatewayPaid(p806Order($payer));
    $finance = p806Staff();
    $refund = p806Request($finance, $order, 5000);
    app(ApproveRefund::class)($finance, $refund);

    if ($kind === 'null') {
        app()->instance(PaymentGateway::class, new NullPaymentGateway);
    } else {
        $this->mock(PaymentGateway::class, static fn ($mock) => $mock->shouldReceive('isConfigured')->andReturn(true)
            ->shouldReceive('supportsRefunds')->andReturn(false)
            ->shouldReceive('key')->andReturn('fake'));
    }

    $refusal = p806Refused(fn () => app(ExecuteRefund::class)($finance, $refund->refresh()));

    expect($refusal->errors()['refund'][0])->toBe(__('refunds.gateway_unavailable'))
        ->and($refund->refresh()->status)->toBe(RefundStatus::Approved)
        ->and(EReceipt::query()->where('kind', 'refund')->count())->toBe(0);
})->with(['null', 'no-refund-support'])->group('T-P8-06', 'A1')->group('payments');

it('executing twice makes one gateway call, issues one credit note and writes one audit', function (): void {
    $order = p806GatewayPaid(p806Order(p806Payer()));
    $finance = p806Staff();
    $refund = p806Request($finance, $order, 5000);
    app(ApproveRefund::class)($finance, $refund);

    app(ExecuteRefund::class)($finance, $refund->refresh());
    app(ExecuteRefund::class)($finance, $refund->refresh());

    $calls = array_filter(app(PaymentGateway::class)->calls, static fn (array $call): bool => $call['method'] === 'refund');
    expect($calls)->toHaveCount(1)
        ->and(EReceipt::query()->where('kind', 'refund')->count())->toBe(1)
        ->and(Activity::query()->where('event', 'refund.executed')->count())->toBe(1);
})->group('T-P8-06', 'A1')->group('payments');

it('a manual refund needs an external reference of 3 to 64 characters', function (): void {
    $order = p806Paid(p806Order(p806Payer()));
    $finance = p806Staff();
    $refund = p806Request($finance, $order, 5000);
    app(ApproveRefund::class)($finance, $refund);

    $refusal = p806Refused(fn () => app(ExecuteRefund::class)($finance, $refund->refresh(), 'ab'));

    expect($refusal->errors()['external_reference'][0])->toBe(__('refunds.external_reference_invalid'))
        ->and($refund->refresh()->status)->toBe(RefundStatus::Approved);
})->group('T-P8-06', 'A1')->group('payments');

// ---------------------------------------------------------------------------
// Access policy
// ---------------------------------------------------------------------------

it('full policy keeps access after a partial refund and revokes on the refund that completes the total', function (): void {
    config(['refunds.revoke_access' => 'full']);
    $payer = p806Payer();
    $order = p806Paid(p806Order($payer));
    $course = Course::query()->findOrFail(OrderItem::query()->where('order_id', $order->getKey())->value('product_id'));
    $finance = p806Staff();

    $partial = p806Request($finance, $order, 10000);
    app(ApproveRefund::class)($finance, $partial);
    app(ExecuteRefund::class)($finance, $partial->refresh(), 'BANK-TRX-0400');
    app(EntitlementService::class)->flush();
    expect(app(EntitlementService::class)->canAccessCourse($payer, $course)->allowed)->toBeTrue()
        ->and(p806Active($order))->toBe(1);

    $rest = p806Request($finance, $order, 15000);
    app(ApproveRefund::class)($finance, $rest);
    app(ExecuteRefund::class)($finance, $rest->refresh(), 'BANK-TRX-0401');
    app(EntitlementService::class)->flush();
    expect(p806Revoked($order))->toBe(1)
        ->and(app(EntitlementService::class)->canAccessCourse($payer, $course)->allowed)->toBeFalse();
})->group('T-P8-06', 'A1')->group('payments');

it('any policy revokes on the first refund and none never revokes', function (): void {
    $payer = p806Payer();
    $order = p806Paid(p806Order($payer));
    config(['refunds.revoke_access' => 'any']);
    $finance = p806Staff();
    $partial = p806Request($finance, $order, 1000);
    app(ApproveRefund::class)($finance, $partial);
    app(ExecuteRefund::class)($finance, $partial->refresh(), 'BANK-TRX-0500');
    expect(p806Revoked($order))->toBe(1);

    $other = p806Payer();
    $kept = p806Paid(p806Order($other));
    config(['refunds.revoke_access' => 'none']);
    $full = p806Request($finance, $kept, 25000);
    app(ApproveRefund::class)($finance, $full);
    app(ExecuteRefund::class)($finance, $full->refresh(), 'BANK-TRX-0501');
    expect(p806Revoked($kept))->toBe(0)
        ->and(p806Active($kept))->toBe(1)
        ->and($kept->refresh()->status)->toBe(OrderStatus::Refunded);
})->group('T-P8-06', 'A1')->group('payments');

it('a manual grant and an entitlement from another order for the same course are not revoked', function (): void {
    config(['refunds.revoke_access' => 'any']);
    $payer = p806Payer();
    $first = p806Paid(p806Order($payer));
    $courseId = (int) OrderItem::query()->where('order_id', $first->getKey())->value('product_id');
    $second = p806Order($payer);
    OrderItem::query()->where('order_id', $second->getKey())->update(['product_id' => $courseId]);
    $second = p806Paid($second);
    Entitlement::query()->create([
        'learner_id' => $payer->getKey(), 'course_id' => $courseId, 'source_type' => EntitlementSource::ManualGrant->value,
        'source_ref' => '', 'starts_at' => now(), 'status' => 'active', 'reason' => 'Goodwill access',
    ]);
    $finance = p806Staff();

    $refund = p806Request($finance, $first, 25000);
    app(ApproveRefund::class)($finance, $refund);
    app(ExecuteRefund::class)($finance, $refund->refresh(), 'BANK-TRX-0600');

    expect(p806Revoked($first))->toBe(1)
        ->and(p806Active($second))->toBe(1)
        ->and(Entitlement::query()->where('source_type', EntitlementSource::ManualGrant->value)->where('status', 'active')->count())->toBe(1);
})->group('T-P8-06', 'A1')->group('payments');

it('a parent-paid order: the child loses access after the refund and the parent never had it', function (): void {
    config(['refunds.revoke_access' => 'full']);
    $parent = User::factory()->create(['status' => 'active'])->assignRole('parent');
    $child = p806Payer();
    GuardianChildLink::query()->create([
        'guardian_id' => $parent->id, 'child_id' => $child->id,
        'status' => GuardianChildLink::STATUS_VERIFIED, 'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT,
    ]);
    $order = p806Paid(p806Order($parent, $child));
    $course = Course::query()->findOrFail(OrderItem::query()->where('order_id', $order->getKey())->value('product_id'));
    $finance = p806Staff();
    $refund = p806Request($finance, $order, 25000);
    app(ApproveRefund::class)($finance, $refund);
    app(ExecuteRefund::class)($finance, $refund->refresh(), 'BANK-TRX-0700');
    app(EntitlementService::class)->flush();

    expect(app(EntitlementService::class)->canAccessCourse($child, $course)->allowed)->toBeFalse()
        ->and(app(EntitlementService::class)->canAccessCourse($parent, $course)->allowed)->toBeFalse()
        ->and(Entitlement::query()->where('learner_id', $parent->getKey())->count())->toBe(0);
})->group('T-P8-06', 'A2')->group('payments');

// ---------------------------------------------------------------------------
// Receipts
// ---------------------------------------------------------------------------

it('the sale receipt is idempotent: one RC row and one e_receipt.issued audit however often it is downloaded', function (): void {
    Pdf::fake();
    $payer = p806Payer();
    $order = p806Paid(p806Order($payer));

    $this->actingAs($payer)->get(route('payments.receipt', $order))->assertOk();
    $this->actingAs($payer)->get(route('payments.receipt', $order))->assertOk();
    app(IssueReceipt::class)($order);

    $sale = EReceipt::query()->where('kind', 'sale')->where('order_id', $order->getKey())->get();
    expect($sale)->toHaveCount(1)
        ->and($sale->first()->number)->toBe('RC-'.$order->reference)
        ->and($sale->first()->snapshot['payment']['method'])->toBe('manual_transfer')
        ->and(Activity::query()->where('event', 'e_receipt.issued')->count())->toBe(1);
})->group('T-P8-06', 'A2')->group('payments');

it('the refund credit note is CN-<refund reference>, points at the original receipt and records the gateway method', function (): void {
    $order = p806GatewayPaid(p806Order(p806Payer()));
    $finance = p806Staff();
    $refund = p806Request($finance, $order, 5000);
    app(ApproveRefund::class)($finance, $refund);
    app(ExecuteRefund::class)($finance, $refund->refresh());

    $credit = EReceipt::query()->where('kind', 'refund')->sole();
    expect($credit->number)->toBe('CN-'.$refund->reference)
        ->and($credit->snapshot['original_receipt_number'])->toBe('RC-'.$order->reference)
        ->and($credit->snapshot['payment']['method'])->toBe('fake')
        ->and($credit->snapshot['totals']['total_minor'])->toBe(5000)
        ->and($credit->snapshot['totals']['tax_minor'])->toBeNull()
        ->and($credit->snapshot['buyer'])->toHaveKeys(['name', 'email']);
})->group('T-P8-06', 'A2')->group('payments');

it('pending orders and non-executed refunds give 404 on the routes and a receipt refusal in the Action', function (): void {
    $payer = p806Payer();
    $pending = p806Order($payer);
    $this->actingAs($payer)->get(route('payments.receipt', $pending))->assertNotFound();

    $refusal = p806Refused(fn () => app(IssueReceipt::class)($pending));
    expect($refusal->errors()['receipt'][0])->toBe(__('refunds.receipt_unavailable'));

    $order = p806Paid(p806Order($payer));
    $refund = p806Request(p806Staff(), $order, 5000);
    $this->actingAs($payer)->get(route('payments.refund-receipt', ['order' => $order, 'refund' => $refund]))->assertNotFound();
    expect(fn () => app(IssueReceipt::class)($order, $refund))->toThrow(ValidationException::class);
})->group('T-P8-06', 'A2')->group('payments');

it('a receipt audit failure rolls the receipt back', function (): void {
    $order = p806Paid(p806Order(p806Payer()));
    app(ActivityLogStatus::class)->disable();

    expect(fn () => app(IssueReceipt::class)($order))->toThrow(RuntimeException::class);

    expect(EReceipt::query()->count())->toBe(0);
})->group('T-P8-06', 'A1')->group('payments');

it('the receipt routes refuse another learner, another parent and guests', function (): void {
    Pdf::fake();
    $payer = p806Payer();
    $order = p806Paid(p806Order($payer));
    $stranger = p806Payer();

    $this->actingAs($stranger)->get(route('payments.receipt', $order))->assertNotFound();
    $this->actingAs($stranger)->get(route('payments.history'))->assertOk();
    auth()->logout();
    $this->get(route('payments.receipt', $order))->assertRedirect();
})->group('T-P8-06', 'A2')->group('payments');

// ---------------------------------------------------------------------------
// Payment history
// ---------------------------------------------------------------------------

it('the payment history shows only the payers own orders newest first, with open refunds in review', function (): void {
    $payer = p806Payer();
    $older = p806Paid(p806Order($payer));
    $newer = p806Paid(p806Order($payer));
    p806Request(p806Staff(), $newer, 5000);
    $other = p806Paid(p806Order(p806Payer()));

    $page = PaymentHistory::for($payer);
    expect($page->total())->toBe(2)
        ->and($page->items()[0]->getKey())->toBe($newer->getKey())
        ->and($page->items()[1]->getKey())->toBe($older->getKey());

    $html = $this->actingAs($payer)->get(route('payments.history'))->assertOk();
    $html->assertSee($newer->reference)->assertSee(__('refunds.refund_in_review'))->assertDontSee($other->reference);
})->group('T-P8-06', 'A2')->group('payments');

it('a parent sees the order they paid for a child, the child sees nothing of it, and guests are redirected to login', function (): void {
    $parent = User::factory()->create(['status' => 'active'])->assignRole('parent');
    $child = p806Payer();
    GuardianChildLink::query()->create([
        'guardian_id' => $parent->id, 'child_id' => $child->id,
        'status' => GuardianChildLink::STATUS_VERIFIED, 'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT,
    ]);
    $order = p806Paid(p806Order($parent, $child));

    expect(PaymentHistory::for($parent)->total())->toBe(1)
        ->and(PaymentHistory::for($child)->total())->toBe(0);

    $this->get(route('payments.history'))->assertRedirect();
    $this->actingAs($child)->get(route('payments.history'))->assertOk()->assertDontSee($order->reference);
})->group('T-P8-06', 'A2')->group('payments');

it('the history shows the empty state and paginates ten orders per page', function (): void {
    $payer = p806Payer();
    $this->actingAs($payer)->get(route('payments.history'))->assertOk()->assertSee(__('refunds.history_empty'));

    for ($i = 0; $i < 11; $i++) {
        p806Order($payer);
    }

    $page = PaymentHistory::for($payer);
    expect($page->total())->toBe(11)
        ->and($page->count())->toBe(10);
})->group('T-P8-06', 'A2')->group('payments');

it('a pending order shows the proof link and a paid order shows the receipt link', function (): void {
    $payer = p806Payer();
    $pending = p806Order($payer);
    $paid = p806Paid(p806Order($payer));

    $this->actingAs($payer)->get(route('payments.history'))
        ->assertSee(route('manual-payments.create', $pending))
        ->assertSee(route('payments.receipt', $paid));
})->group('T-P8-06', 'A2')->group('payments');

// ---------------------------------------------------------------------------
// Audit rollback
// ---------------------------------------------------------------------------

it('an audit failure rolls back request, approve, reject and execute', function (): void {
    $payer = p806Payer();
    $order = p806Paid(p806Order($payer));
    $finance = p806Staff();

    app(ActivityLogStatus::class)->disable();
    expect(fn () => p806Request($finance, $order, 5000))->toThrow(RuntimeException::class)
        ->and(Refund::query()->count())->toBe(0);
    app(ActivityLogStatus::class)->enable();

    $approved = p806Request($finance, $order, 5000);
    app(ActivityLogStatus::class)->disable();
    expect(fn () => app(ApproveRefund::class)($finance, $approved))->toThrow(RuntimeException::class);
    expect($approved->refresh()->status)->toBe(RefundStatus::Requested);
    app(ActivityLogStatus::class)->enable();

    $rejectable = p806Request($finance, $order, 4000);
    app(ActivityLogStatus::class)->disable();
    expect(fn () => app(RejectRefund::class)($finance, $rejectable, 'Duplicate request from the same payer.'))->toThrow(RuntimeException::class);
    expect($rejectable->refresh()->status)->toBe(RefundStatus::Requested);
    app(ActivityLogStatus::class)->enable();

    app(ApproveRefund::class)($finance, $approved->refresh());
    app(ActivityLogStatus::class)->disable();
    expect(fn () => app(ExecuteRefund::class)($finance, $approved->refresh(), 'BANK-TRX-0800'))->toThrow(RuntimeException::class);
    app(ActivityLogStatus::class)->enable();

    expect($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and($approved->refresh()->status)->toBe(RefundStatus::Approved)
        ->and(p806Active($order))->toBe(1);
})->group('T-P8-06', 'A1')->group('payments');

it('the refund audit labels exist in Arabic and English', function (): void {
    $ar = require lang_path('ar/audit-events.php');
    $en = require lang_path('en/audit-events.php');

    foreach (['refund.requested', 'refund.approved', 'refund.rejected', 'refund.executed', 'refund.failed', 'refund_review.approved', 'refund_review.rejected', 'e_receipt.issued', 'e_receipt.submitted'] as $event) {
        expect($ar)->toHaveKey($event)->and($en)->toHaveKey($event);
    }
})->group('T-P8-06', 'A1')->group('payments');

// ---------------------------------------------------------------------------
// Filament and lang parity
// ---------------------------------------------------------------------------

it('refund resource visibility: finance, admin and payment.view holders yes; learners and instructors no', function (): void {
    Filament::setCurrentPanel('finance');

    $this->actingAs(p806Staff('finance'));
    expect(RefundResource::canViewAny())->toBeTrue();
    $this->actingAs(p806Staff('admin'));
    expect(RefundResource::canViewAny())->toBeTrue();

    $this->actingAs(p806Payer());
    expect(RefundResource::canViewAny())->toBeFalse();
    $this->actingAs(User::factory()->create(['status' => 'active'])->assignRole('instructor_academy'));
    expect(RefundResource::canViewAny())->toBeFalse();
})->group('T-P8-06', 'A2')->group('payments');

it('the refunds list renders for finance, shows approve and reject on requested refunds and the request header action works', function (): void {
    $order = p806Paid(p806Order(p806Payer()));
    $refund = p806Request(p806Staff(), $order, 5000);
    $finance = p806Staff();
    Filament::setCurrentPanel('finance');
    $this->actingAs($finance);

    Livewire::test(ListRefunds::class)
        ->assertOk()
        ->assertTableActionVisible('approve', $refund)
        ->assertTableActionVisible('reject', $refund)
        ->callAction('request', data: [
            'order_reference' => $order->reference, 'amount' => '50.00', 'reason' => 'Partial refund for a lesson',
        ]);

    expect(Refund::query()->where('order_id', $order->getKey())->count())->toBe(2);
})->group('T-P8-06', 'A2')->group('payments');

it('the refunds and receipts lang files have the same keys in Arabic and English', function (): void {
    expect(array_keys(require lang_path('ar/refunds.php')))->toEqual(array_keys(require lang_path('en/refunds.php')))
        ->and(array_keys(require lang_path('ar/receipts.php')))->toEqual(array_keys(require lang_path('en/receipts.php')));
})->group('T-P8-06', 'A2')->group('payments');
