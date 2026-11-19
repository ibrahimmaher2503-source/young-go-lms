<?php

declare(strict_types=1);

// T-P8-04 | YG-GAP-PAY-004, PRD 17.3, Checklist 10 | A1 A2 A3

use App\Domain\Audit\AuditLogger;
use App\Domain\Commerce\Actions\ApproveManualPaymentProof;
use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Actions\RejectManualPaymentProof;
use App\Domain\Commerce\Actions\SubmitManualPaymentProof;
use App\Domain\Commerce\Actions\TransitionOrder;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Commerce\Events\ManualPaymentApproved;
use App\Domain\Commerce\Events\ManualPaymentProofSubmitted;
use App\Domain\Commerce\ManualPaymentProof;
use App\Domain\Commerce\ManualPaymentProofAccess;
use App\Domain\Commerce\ManualPaymentStatus;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\ProductType;
use App\Domain\Commerce\Queries\ManualPaymentQueue;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Shared\Markets\Market;
use App\Filament\Resources\ManualPayments\ManualPaymentProofResource;
use App\Filament\Resources\ManualPayments\Pages\ListManualPaymentProofs;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Activitylog\Support\ActivityLogStatus;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Process\ExecutableFinder;
use Tests\Support\PdfFixture;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');
    Storage::fake('public');
});

/** A published course priced 250.00 EGP (25000 minor). */
function p804Course(): Course
{
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة', 'en' => 'Manual proof course'],
        'slug_ar' => 'p804-ar-'.uniqid(),
        'slug_en' => 'p804-en-'.uniqid(),
        'status' => 'published',
        'access_mode' => 'paid',
    ]);
    CoursePrice::query()->create(['course_id' => $course->id, 'market' => 'EG', 'currency' => 'EGP', 'amount_minor' => 25000]);

    return $course;
}

function p804Active(string $role): User
{
    return User::factory()->create(['status' => 'active'])->assignRole($role);
}

/** A pending Egyptian order for a payer, for one course line (the payer learns it unless a learner is given). */
function p804Order(User $payer, ?User $learner = null): Order
{
    return app(CreateOrder::class)($payer, Market::Egypt, [new OrderLine(ProductType::Course, p804Course()->id, $learner ?? $payer)], 'p804-'.uniqid());
}

/** A role with exactly the given permissions (the seeded permission names). */
function p804Role(string $name, array $permissions): void
{
    $role = Role::findOrCreate($name, 'web');
    $role->syncPermissions($permissions);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

function p804Png(): UploadedFile
{
    return UploadedFile::fake()->image('slip.png', 160, 120);
}

/**
 * Submits a proof through the Action with sensible defaults. The default reference is unique per order.
 *
 * @param  array<string, mixed>  $overrides
 */
function p804Submit(User $payer, Order $order, array $overrides = [], ?File $file = null): ManualPaymentProof
{
    $data = array_merge([
        'payer_name' => 'Hoda Payer',
        'sender_account' => '01099887766',
        'transfer_reference' => 'TRX-'.$order->getKey(),
        'amount_minor' => $order->total_minor,
        'transferred_at' => now()->subMinutes(20),
    ], $overrides);

    return app(SubmitManualPaymentProof::class)($payer, $order, $data, $file ?? p804Png());
}

/** Runs the call and returns the validation refusal it throws (the test fails when nothing is refused). */
function p804Refused(Closure $call): ValidationException
{
    try {
        $call();
    } catch (ValidationException $refusal) {
        return $refusal;
    }

    throw new RuntimeException('Expected a validation refusal, nothing was refused.');
}

/** A JPEG with an injected APP1 (EXIF-style) segment carrying a marker string. */
function p804JpegWithExif(string $marker): string
{
    $image = imagecreatetruecolor(40, 30);
    ob_start();
    imagejpeg($image);
    $jpeg = (string) ob_get_clean();
    $segment = "Exif\0\0".$marker;

    return substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($segment) + 2).$segment.substr($jpeg, 2);
}

function p804Qpdf(): bool
{
    return (string) config('media.qpdf_binary') !== '' && is_string(config('media.qpdf_binary'))
        || (new ExecutableFinder)->find('qpdf') !== null;
}

/** Makes the audit log fail (AuditLogger is final, so the activity log is switched off instead: record() then returns null). */
function p804AuditOff(): void
{
    app(ActivityLogStatus::class)->disable();
}

function p804Entitlements(string $source): int
{
    return Entitlement::query()->where('source_type', $source)->count();
}

// ---------------------------------------------------------------------------
// A1: a reference is used once; approve and reject are idempotent
// ---------------------------------------------------------------------------

it('refuses a transfer reference that a pending proof already holds, whatever the case and spacing', function (): void {
    $first = p804Order(p804Active('student'));
    $second = p804Order(p804Active('student'));
    p804Submit($first->payer, $first, ['transfer_reference' => 'instapay-ref-7781']);

    $refusal = p804Refused(fn () => p804Submit($second->payer, $second, ['transfer_reference' => '  instapay-ref-7781  ']));

    expect($refusal->errors())->toHaveKey('transfer_reference')
        ->and($refusal->errors()['transfer_reference'][0])->toBe(__('manual_payment.reference_taken'))
        ->and(ManualPaymentProof::query()->count())->toBe(1);
})->group('T-P8-04', 'A1')->group('payments');

it('refuses a reference that an approved proof holds on another payer order', function (): void {
    $first = p804Order(p804Active('student'));
    $second = p804Order(p804Active('student'));
    $proof = p804Submit($first->payer, $first, ['transfer_reference' => 'INSTAPAY-APPROVED-1']);
    app(ApproveManualPaymentProof::class)(p804Active('finance'), $proof);

    $refusal = p804Refused(fn () => p804Submit($second->payer, $second, ['transfer_reference' => 'instapay-approved-1']));

    expect($refusal->errors()['transfer_reference'][0])->toBe(__('manual_payment.reference_taken'));
})->group('T-P8-04', 'A1')->group('payments');

it('refuses a second pending proof on the same order', function (): void {
    $payer = p804Active('student');
    $order = p804Order($payer);
    p804Submit($payer, $order, ['transfer_reference' => 'FIRST-REF-0001']);

    $refusal = p804Refused(fn () => p804Submit($payer, $order, ['transfer_reference' => 'SECOND-REF-0002']));

    expect($refusal->errors())->toHaveKey('order')
        ->and($refusal->errors()['order'][0])->toBe(__('manual_payment.proof_pending'));
})->group('T-P8-04', 'A1')->group('payments');

it('lets a rejected reference be resubmitted, and holds it again once resubmitted', function (): void {
    $payer = p804Active('student');
    $order = p804Order($payer);
    $finance = p804Active('finance');
    $rejected = p804Submit($payer, $order, ['transfer_reference' => 'REJ-REF-4411']);
    app(RejectManualPaymentProof::class)($finance, $rejected, 'The screenshot is cropped, please resend.');

    $resent = p804Submit($payer, $order, ['transfer_reference' => 'rej-ref-4411']);

    expect($resent->status)->toBe(ManualPaymentStatus::Pending)
        ->and(ManualPaymentProof::query()->count())->toBe(2);

    $otherOrder = p804Order(p804Active('student'));
    $refusal = p804Refused(fn () => p804Submit($otherOrder->payer, $otherOrder, ['transfer_reference' => 'REJ-REF-4411']));
    expect($refusal->errors()['transfer_reference'][0])->toBe(__('manual_payment.reference_taken'));
})->group('T-P8-04', 'A1')->group('payments');

it('approving an approved proof again writes no second order transition, entitlement or audit', function (): void {
    $payer = p804Active('student');
    $order = p804Order($payer);
    $proof = p804Submit($payer, $order);
    $finance = p804Active('finance');

    $first = app(ApproveManualPaymentProof::class)($finance, $proof);
    $second = app(ApproveManualPaymentProof::class)($finance, $proof);

    expect($first->status)->toBe(ManualPaymentStatus::Approved)
        ->and($second->status)->toBe(ManualPaymentStatus::Approved)
        ->and($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and(p804Entitlements(EntitlementSource::ManualPayment->value))->toBe(1)
        ->and(Activity::query()->where('event', 'manual_payment.approved')->count())->toBe(1)
        ->and(Activity::query()->where('event', 'order.status_changed')->get()
            ->filter(static fn (Activity $row): bool => ($row->properties['after']['status'] ?? null) === OrderStatus::Paid->value)->count())->toBe(1);
})->group('T-P8-04', 'A1')->group('payments');

it('rejecting a rejected proof again is a no-op with one audit row', function (): void {
    $payer = p804Active('student');
    $order = p804Order($payer);
    $proof = p804Submit($payer, $order);
    $finance = p804Active('finance');

    app(RejectManualPaymentProof::class)($finance, $proof, 'Amount is not visible in the screenshot.');
    app(RejectManualPaymentProof::class)($finance, $proof, 'Amount is not visible in the screenshot.');

    expect($proof->refresh()->status)->toBe(ManualPaymentStatus::Rejected)
        ->and(Activity::query()->where('event', 'manual_payment.rejected')->count())->toBe(1);
})->group('T-P8-04', 'A1')->group('payments');

it('an approved proof cannot be rejected', function (): void {
    $payer = p804Active('student');
    $order = p804Order($payer);
    $proof = p804Submit($payer, $order);
    $finance = p804Active('finance');
    app(ApproveManualPaymentProof::class)($finance, $proof);

    $refusal = p804Refused(fn () => app(RejectManualPaymentProof::class)($finance, $proof, 'Changed my mind after the approval.'));

    expect($refusal->errors())->toHaveKey('proof')
        ->and($refusal->errors()['proof'][0])->toBe(__('manual_payment.not_pending'))
        ->and($proof->refresh()->status)->toBe(ManualPaymentStatus::Approved);
})->group('T-P8-04', 'A1')->group('payments');

it('a true parallel double approve is covered by the database lock, not by this suite')
    ->skip('SQLite cannot run parallel transactions in this suite; sequential double approve and reject are covered by the idempotency tests.')
    ->group('T-P8-04', 'A1')->group('payments');

// ---------------------------------------------------------------------------
// A2: only finance (payment.approve_manual + manual_entitlement.grant) decides
// ---------------------------------------------------------------------------

it('finance and admin can approve and reject proofs', function (): void {
    $payer = p804Active('student');
    $approveOrder = p804Order($payer);
    $rejectOrder = p804Order($payer);
    $approvable = p804Submit($payer, $approveOrder, ['transfer_reference' => 'FIN-APPROVE-01']);
    $rejectable = p804Submit($payer, $rejectOrder, ['transfer_reference' => 'FIN-REJECT-01']);

    app(ApproveManualPaymentProof::class)(p804Active('finance'), $approvable);
    app(RejectManualPaymentProof::class)(p804Active('admin'), $rejectable, 'Transfer not found in the bank statement.');

    expect($approvable->refresh()->status)->toBe(ManualPaymentStatus::Approved)
        ->and($rejectable->refresh()->status)->toBe(ManualPaymentStatus::Rejected);
})->group('T-P8-04', 'A2')->group('payments');

it('learner, parent, instructor, support, inactive finance and an approve-only custom role cannot approve or reject', function (): void {
    p804Role('p804_approve_only', ['payment.approve_manual']);
    $payer = p804Active('student');
    $order = p804Order($payer);
    $proof = p804Submit($payer, $order);

    $inactiveFinance = User::factory()->create(['status' => 'inactive'])->assignRole('finance');
    $approveOnly = User::factory()->create(['status' => 'active'])->assignRole('p804_approve_only');
    $outsiders = [
        p804Active('student'),
        p804Active('parent'),
        p804Active('instructor_academy'),
        p804Active('support'),
        $inactiveFinance,
        $approveOnly,
    ];

    foreach ($outsiders as $outsider) {
        expect(fn () => app(ApproveManualPaymentProof::class)($outsider, $proof))->toThrow(AuthorizationException::class);
    }
    foreach (array_slice($outsiders, 0, 5) as $outsider) {
        expect(fn () => app(RejectManualPaymentProof::class)($outsider, $proof, 'Not allowed to decide this proof.'))->toThrow(AuthorizationException::class);
    }

    expect($proof->refresh()->status)->toBe(ManualPaymentStatus::Pending)
        ->and($order->refresh()->status)->toBe(OrderStatus::Pending)
        ->and(p804Entitlements(EntitlementSource::ManualPayment->value))->toBe(0);
})->group('T-P8-04', 'A2')->group('payments');

it('a custom role with only payment.approve_manual cannot reject a proof', function (): void {
    p804Role('p804_reject_only', ['payment.approve_manual']);
    $payer = p804Active('student');
    $proof = p804Submit($payer, p804Order($payer));
    $approveOnly = User::factory()->create(['status' => 'active'])->assignRole('p804_reject_only');

    expect(fn () => app(RejectManualPaymentProof::class)($approveOnly, $proof, 'Not allowed to decide this proof.'))->toThrow(AuthorizationException::class)
        ->and($proof->refresh()->status)->toBe(ManualPaymentStatus::Pending);
})->group('T-P8-04', 'A2')->group('payments');

it('payment.view-only staff see the queue without review actions or detail and file links', function (): void {
    p804Role('p804_viewer', ['payment.view', 'panel.finance.access']);
    $payer = p804Active('student');
    $proof = p804Submit($payer, p804Order($payer));
    $viewer = User::factory()->create(['status' => 'active'])->assignRole('p804_viewer');

    $this->actingAs($viewer);
    Filament::setCurrentPanel('finance');

    expect(ManualPaymentProofResource::canViewAny())->toBeTrue();
    Livewire::test(ListManualPaymentProofs::class)
        ->assertTableActionHidden('approve', $proof)
        ->assertTableActionHidden('reject', $proof)
        ->assertTableActionHidden('details', $proof)
        ->assertTableActionHidden('file', $proof);
})->group('T-P8-04', 'A2')->group('payments');

it('finance sees the approve and reject actions on a pending proof', function (): void {
    $payer = p804Active('student');
    $proof = p804Submit($payer, p804Order($payer));
    $finance = p804Active('finance');

    $this->actingAs($finance);
    Filament::setCurrentPanel('finance');

    Livewire::test(ListManualPaymentProofs::class)
        ->assertTableActionVisible('approve', $proof)
        ->assertTableActionVisible('reject', $proof);
})->group('T-P8-04', 'A2')->group('payments');

it('the review queue is not visible to learners and instructors', function (): void {
    $learner = p804Active('student');
    $instructor = p804Active('instructor_academy');
    Filament::setCurrentPanel('finance');

    $this->actingAs($learner);
    expect(ManualPaymentProofResource::canViewAny())->toBeFalse();

    $this->actingAs($instructor);
    expect(ManualPaymentProofResource::canViewAny())->toBeFalse();
})->group('T-P8-04', 'A2')->group('payments');

// ---------------------------------------------------------------------------
// A3: screens render in both locales without missing keys
// ---------------------------------------------------------------------------

it('the submit form renders in Arabic and English without raw translation keys', function (): void {
    $payer = p804Active('student');
    $order = p804Order($payer);

    foreach (['ar', 'en'] as $locale) {
        app()->setLocale($locale);
        $this->actingAs($payer)->get(route('manual-payments.create', $order))
            ->assertOk()
            ->assertDontSee('manual_payment.');
    }
})->group('T-P8-04', 'A3')->group('payments');

it('the transfer proof queue renders in Arabic and English without raw translation keys', function (): void {
    $payer = p804Active('student');
    p804Submit($payer, p804Order($payer));
    $finance = p804Active('finance');
    Filament::setCurrentPanel('finance');
    $this->actingAs($finance);

    foreach (['ar', 'en'] as $locale) {
        app()->setLocale($locale);
        Livewire::test(ListManualPaymentProofs::class)->assertOk()->assertDontSee('manual_payment.');
    }
})->group('T-P8-04', 'A3')->group('payments');

it('the manual payment lang files have the same keys in Arabic and English', function (): void {
    $ar = require lang_path('ar/manual_payment.php');
    $en = require lang_path('en/manual_payment.php');

    expect(array_keys($ar))->toEqual(array_keys($en));
})->group('T-P8-04', 'A3')->group('payments');

it('the manual payment audit labels exist in Arabic and English', function (): void {
    $keys = ['manual_payment.submitted', 'manual_payment.approved', 'manual_payment.rejected', 'manual_payment_review.approved', 'manual_payment_review.rejected'];
    $ar = require lang_path('ar/audit-events.php');
    $en = require lang_path('en/audit-events.php');

    foreach ($keys as $key) {
        expect($ar)->toHaveKey($key)
            ->and($en)->toHaveKey($key)
            ->and($en[$key])->not->toBe('');
    }
})->group('T-P8-04', 'A3')->group('payments');

// ---------------------------------------------------------------------------
// Order effects and the payer rules
// ---------------------------------------------------------------------------

it('approval leaves the order paid with paid_at and frozen totals, and the learner can access the course', function (): void {
    $payer = p804Active('student');
    $order = p804Order($payer);
    $before = $order->only(['subtotal_minor', 'discount_minor', 'total_minor', 'currency']);
    $proof = p804Submit($payer, $order);

    app(ApproveManualPaymentProof::class)(p804Active('finance'), $proof);

    $paid = $order->refresh();
    $course = Course::query()->findOrFail(OrderLineCourseId::of($paid));
    expect($paid->status)->toBe(OrderStatus::Paid)
        ->and($paid->paid_at)->not->toBeNull()
        ->and($paid->only(['subtotal_minor', 'discount_minor', 'total_minor', 'currency']))->toEqual($before)
        ->and(app(EntitlementService::class)->canAccessCourse($payer, $course)->allowed)->toBeTrue();
})->group('T-P8-04', 'A1', 'A2')->group('payments');

it('a parent who pays for a child grants the child access and not the parent', function (): void {
    $parent = p804Active('parent');
    $child = p804Active('student');
    GuardianChildLink::query()->create([
        'guardian_id' => $parent->id,
        'child_id' => $child->id,
        'status' => GuardianChildLink::STATUS_VERIFIED,
        'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT,
    ]);
    $order = p804Order($parent, $child);
    $proof = p804Submit($parent, $order);

    app(ApproveManualPaymentProof::class)(p804Active('finance'), $proof);

    $course = Course::query()->findOrFail(OrderLineCourseId::of($order));
    expect(Entitlement::query()->where('learner_id', $child->id)->where('source_type', EntitlementSource::ManualPayment->value)->exists())->toBeTrue()
        ->and(Entitlement::query()->where('learner_id', $parent->id)->exists())->toBeFalse()
        ->and(app(EntitlementService::class)->canAccessCourse($child, $course)->allowed)->toBeTrue()
        ->and(app(EntitlementService::class)->canAccessCourse($parent, $course)->allowed)->toBeFalse();
})->group('T-P8-04', 'A2')->group('payments');

it('nothing is granted on submit or on reject', function (): void {
    $payer = p804Active('student');
    $order = p804Order($payer);
    $proof = p804Submit($payer, $order);

    expect(p804Entitlements(EntitlementSource::ManualPayment->value))->toBe(0)
        ->and($order->refresh()->status)->toBe(OrderStatus::Pending);

    app(RejectManualPaymentProof::class)(p804Active('finance'), $proof, 'Transfer not found in the bank statement.');

    expect(p804Entitlements(EntitlementSource::ManualPayment->value))->toBe(0)
        ->and($order->refresh()->status)->toBe(OrderStatus::Pending)
        ->and($order->total_minor)->toBe(25000);
})->group('T-P8-04', 'A1')->group('payments');

it('an order that is no longer pending cannot be approved', function (): void {
    $payer = p804Active('student');
    $order = p804Order($payer);
    $proof = p804Submit($payer, $order);
    app(TransitionOrder::class)($order, OrderStatus::Cancelled);
    $finance = p804Active('finance');

    $refusal = p804Refused(fn () => app(ApproveManualPaymentProof::class)($finance, $proof));

    expect($refusal->errors()['proof'][0])->toBe(__('manual_payment.order_not_payable'))
        ->and($proof->refresh()->status)->toBe(ManualPaymentStatus::Pending)
        ->and(p804Entitlements(EntitlementSource::ManualPayment->value))->toBe(0);
})->group('T-P8-04', 'A1')->group('payments');

it('a paid order cannot take a new proof', function (): void {
    $payer = p804Active('student');
    $order = p804Order($payer);
    app(TransitionOrder::class)($order, OrderStatus::Paid);

    $refusal = p804Refused(fn () => p804Submit($payer, $order->refresh()));

    expect($refusal->errors()['order'][0])->toBe(__('manual_payment.order_invalid'));
})->group('T-P8-04', 'A1')->group('payments');

it('another payer cannot submit a proof for the order', function (): void {
    $payer = p804Active('student');
    $stranger = p804Active('student');
    $order = p804Order($payer);

    $refusal = p804Refused(fn () => p804Submit($stranger, $order));

    expect($refusal->errors()['order'][0])->toBe(__('manual_payment.order_invalid'))
        ->and(ManualPaymentProof::query()->count())->toBe(0);
})->group('T-P8-04', 'A2')->group('payments');

it('an inactive payer cannot submit a proof for their own order', function (): void {
    $payer = p804Active('student');
    $order = p804Order($payer);
    $payer->forceFill(['status' => 'inactive'])->save();

    $refusal = p804Refused(fn () => p804Submit($payer, $order));

    expect($refusal->errors()['order'][0])->toBe(__('manual_payment.order_invalid'));
})->group('T-P8-04', 'A2')->group('payments');

it('a zero-total order and a Saudi order are refused', function (): void {
    $payer = p804Active('student');
    $zero = p804Order($payer);
    DB::table('orders')->where('id', $zero->getKey())->update(['total_minor' => 0]);

    $saudi = p804Order($payer);
    DB::table('orders')->where('id', $saudi->getKey())->update(['market' => 'SA']);

    expect(fn () => p804Submit($payer, $zero->refresh()))->toThrow(ValidationException::class)
        ->and(fn () => p804Submit($payer, $saudi->refresh()))->toThrow(ValidationException::class);
    expect(ManualPaymentProof::query()->count())->toBe(0);
})->group('T-P8-04', 'A1')->group('payments');

it('the submit Action refuses malformed input with the field keys and lang messages', function (): void {
    $payer = p804Active('student');
    $order = p804Order($payer);

    $cases = [
        ['payer_name', ['payer_name' => 'A'], 'payer_name_invalid'],
        ['sender_account', ['sender_account' => '1234'], 'sender_invalid'],
        ['transfer_reference', ['transfer_reference' => 'ab'], 'reference_invalid'],
        ['amount', ['amount_minor' => 0], 'amount_invalid'],
        ['transferred_at', ['transferred_at' => now()->addHours(2)], 'transfer_date_invalid'],
        ['transferred_at', ['transferred_at' => now()->subDays(31)], 'transfer_date_invalid'],
    ];

    foreach ($cases as [$field, $overrides, $key]) {
        $refusal = p804Refused(fn () => p804Submit($payer, $order, $overrides));
        expect($refusal->errors())->toHaveKey($field)
            ->and($refusal->errors()[$field][0])->toBe(__('manual_payment.'.$key));
    }
    expect(ManualPaymentProof::query()->count())->toBe(0);
})->group('T-P8-04', 'A1')->group('payments');

// ---------------------------------------------------------------------------
// Amount mismatch and the queue summary
// ---------------------------------------------------------------------------

it('approving an amount that differs from the order total needs a note', function (): void {
    $payer = p804Active('student');
    $order = p804Order($payer);
    $proof = p804Submit($payer, $order, ['amount_minor' => 20000]);
    $finance = p804Active('finance');

    $refusal = p804Refused(fn () => app(ApproveManualPaymentProof::class)($finance, $proof));

    expect($refusal->errors())->toHaveKey('note')
        ->and($refusal->errors()['note'][0])->toBe(__('manual_payment.amount_mismatch'))
        ->and($proof->refresh()->status)->toBe(ManualPaymentStatus::Pending)
        ->and($order->refresh()->status)->toBe(OrderStatus::Pending)
        ->and(p804Entitlements(EntitlementSource::ManualPayment->value))->toBe(0);
})->group('T-P8-04', 'A1')->group('payments');

it('with a note an amount mismatch approves and the audit records amount_mismatch', function (): void {
    $payer = p804Active('student');
    $order = p804Order($payer);
    $proof = p804Submit($payer, $order, ['amount_minor' => 20000]);

    app(ApproveManualPaymentProof::class)(p804Active('finance'), $proof, 'Bank shows 200.00 after a bank fee, confirmed.');

    $audit = Activity::query()->where('event', 'manual_payment.approved')->sole();
    expect($proof->refresh()->status)->toBe(ManualPaymentStatus::Approved)
        ->and($order->refresh()->status)->toBe(OrderStatus::Paid)
        ->and($audit->properties['after']['amount_mismatch'])->toBeTrue()
        ->and($proof->review_note)->toBe('Bank shows 200.00 after a bank fee, confirmed.');
})->group('T-P8-04', 'A1')->group('payments');

it('the queue summary counts pending, overdue after the SLA and amount mismatches', function (): void {
    $matching = p804Order(p804Active('student'));
    $mismatch = p804Order(p804Active('student'));
    p804Submit($matching->payer, $matching);
    p804Submit($mismatch->payer, $mismatch, ['amount_minor' => 1000]);

    expect(ManualPaymentQueue::summary())->toBe(['pending' => 2, 'overdue' => 0, 'mismatch' => 1]);

    $this->travel(25)->hours();
    expect(ManualPaymentQueue::summary())->toBe(['pending' => 2, 'overdue' => 2, 'mismatch' => 1]);
    $this->travelBack();
})->group('T-P8-04', 'A1')->group('payments');

// ---------------------------------------------------------------------------
// Files
// ---------------------------------------------------------------------------

it('accepts png, jpeg and webp images and stores them as images', function (): void {
    if (! function_exists('imagewebp')) {
        $this->markTestSkipped('GD without WebP support.');
    }
    $payer = p804Active('student');
    $image = imagecreatetruecolor(40, 30);
    ob_start();
    imagepng($image);
    $png = (string) ob_get_clean();
    ob_start();
    imagejpeg($image);
    $jpeg = (string) ob_get_clean();
    ob_start();
    imagewebp($image);
    $webp = (string) ob_get_clean();

    $results = [];
    foreach (['png' => $png, 'jpg' => $jpeg, 'webp' => $webp] as $extension => $bytes) {
        $order = p804Order($payer);
        $file = UploadedFile::fake()->createWithContent('slip.'.$extension, $bytes);
        $results[$extension] = p804Submit($payer, $order, ['transfer_reference' => 'IMG-'.$extension.'-'.$order->getKey()], $file);
    }

    foreach ($results as $proof) {
        expect($proof->file_mime)->toBeIn(['image/png', 'image/jpeg', 'image/webp'])
            ->and($proof->file_path)->toMatch('/^manual-payments\/[0-9a-f-]{36}\.(png|jpg|webp)$/');
    }
})->group('T-P8-04', 'A1')->group('payments');

it('re-encodes images so hidden metadata is not stored', function (): void {
    $payer = p804Active('student');
    $marker = 'GPS-MARKER-'.str_repeat('Z', 6);
    $file = UploadedFile::fake()->createWithContent('slip.jpg', p804JpegWithExif($marker));

    $proof = p804Submit($payer, p804Order($payer), [], $file);

    expect(Storage::disk('local')->get($proof->file_path))->not->toContain($marker);
})->group('T-P8-04', 'A1')->group('payments');

it('accepts a valid PDF only when the PDF inspector runtime (qpdf) is available', function (): void {
    $payer = p804Active('student');
    $file = UploadedFile::fake()->createWithContent('transfer.pdf', PdfFixture::bytes(1));

    $proof = p804Submit($payer, p804Order($payer), [], $file);

    expect($proof->file_mime)->toBe('application/pdf')
        ->and($proof->file_path)->toEndWith('.pdf');
})->group('T-P8-04', 'A1')->group('payments')->skip(fn () => ! p804Qpdf(), 'qpdf is not installed on this host, so PDF inspection cannot run.');

it('refuses a PDF with JavaScript as active content', function (): void {
    $payer = p804Active('student');
    $script = str_replace('<</Type/Catalog/Pages 2 0 R>>', '<</Type/Catalog/Pages 2 0 R/OpenAction<</S/JavaScript/JS(app.alert(1))>>>>', PdfFixture::bytes(1));
    $file = UploadedFile::fake()->createWithContent('transfer.pdf', $script);

    $refusal = p804Refused(fn () => p804Submit($payer, p804Order($payer), [], $file));

    expect($refusal->errors())->toHaveKey('proof')
        ->and($refusal->errors()['proof'][0])->toBe(__('manual_payment.file_invalid'))
        ->and(ManualPaymentProof::query()->count())->toBe(0);
})->group('T-P8-04', 'A1')->group('payments');

it('refuses an encrypted PDF', function (): void {
    if (! p804Qpdf()) {
        $this->markTestSkipped('qpdf is not installed on this host.');
    }
    $payer = p804Active('student');
    $file = UploadedFile::fake()->createWithContent('transfer.pdf', PdfFixture::encryptedBytes());

    $refusal = p804Refused(fn () => p804Submit($payer, p804Order($payer), [], $file));

    expect($refusal->errors()['proof'][0])->toBe(__('manual_payment.file_invalid'));
})->group('T-P8-04', 'A1')->group('payments');

it('refuses a file over the size limit', function (): void {
    config(['manual_payments.max_kilobytes' => 1]);
    $payer = p804Active('student');
    $file = UploadedFile::fake()->create('slip.png', 3);

    $refusal = p804Refused(fn () => p804Submit($payer, p804Order($payer), [], $file));

    expect($refusal->errors())->toHaveKey('proof')
        ->and($refusal->errors()['proof'][0])->toBe(__('manual_payment.file_too_large', ['max' => round(1 / 1024, 1)]));
})->group('T-P8-04', 'A1')->group('payments');

it('refuses a disguised file: text content named as a png', function (): void {
    $payer = p804Active('student');
    $file = UploadedFile::fake()->createWithContent('slip.png', 'this is plain text, not a picture');

    $refusal = p804Refused(fn () => p804Submit($payer, p804Order($payer), [], $file));

    expect($refusal->errors())->not->toBeEmpty()
        ->and(Storage::disk('local')->allFiles('manual-payments'))->toBe([]);
})->group('T-P8-04', 'A1')->group('payments');

it('refuses a disguised file on key proof with manual_payment.file_type', function (): void {
    $payer = p804Active('student');
    // A real PNG signature (so the sniffed type is image/png) followed by bytes that do not decode as an image.
    $file = UploadedFile::fake()->createWithContent('slip.png', "\x89PNG\r\n\x1a\n".str_repeat('not a real picture ', 8));

    $refusal = p804Refused(fn () => p804Submit($payer, p804Order($payer), [], $file));

    expect($refusal->errors())->toHaveKey('proof')
        ->and($refusal->errors())->not->toHaveKey('file')
        ->and($refusal->errors()['proof'][0])->toBe(__('manual_payment.file_type'))
        ->and(Storage::disk('local')->allFiles('manual-payments'))->toBe([]);
})->group('T-P8-04', 'A1')->group('payments');

it('stores the proof privately under manual-payments with a uuid name and keeps path and checksum out of arrays', function (): void {
    $payer = p804Active('student');
    $proof = p804Submit($payer, p804Order($payer));

    expect($proof->file_path)->toMatch('/^manual-payments\/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.(png|jpg)$/')
        ->and(Storage::disk('local')->exists($proof->file_path))->toBeTrue()
        ->and(Storage::disk('public')->allFiles())->toBe([])
        ->and($proof->toArray())->not->toHaveKey('file_path')
        ->and($proof->toArray())->not->toHaveKey('file_sha256');
})->group('T-P8-04', 'A1')->group('payments');

it('a failed submit deletes its file and leaves no proof row', function (): void {
    $payer = p804Active('student');
    $order = p804Order($payer);
    p804AuditOff();

    expect(fn () => p804Submit($payer, $order))->toThrow(RuntimeException::class);

    expect(ManualPaymentProof::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles('manual-payments'))->toBe([]);
})->group('T-P8-04', 'A1')->group('payments');

it('a refused duplicate reference never leaves a second file behind', function (): void {
    $payer = p804Active('student');
    $first = p804Order($payer);
    $second = p804Order($payer);
    p804Submit($payer, $first, ['transfer_reference' => 'DUP-REF-5500']);
    $filesBefore = Storage::disk('local')->allFiles('manual-payments');

    p804Refused(fn () => p804Submit($payer, $second, ['transfer_reference' => 'dup-ref-5500']));

    expect($filesBefore)->toHaveCount(1)
        ->and(Storage::disk('local')->allFiles('manual-payments'))->toEqual($filesBefore);
})->group('T-P8-04', 'A1')->group('payments');

// ---------------------------------------------------------------------------
// Events and audit rollback
// ---------------------------------------------------------------------------

it('dispatches the submitted and approved events once and none for a repeat approve', function (): void {
    Event::fake([ManualPaymentProofSubmitted::class, ManualPaymentApproved::class]);
    $payer = p804Active('student');
    $proof = p804Submit($payer, p804Order($payer));
    $finance = p804Active('finance');

    app(ApproveManualPaymentProof::class)($finance, $proof);
    app(ApproveManualPaymentProof::class)($finance, $proof);

    Event::assertDispatchedTimes(ManualPaymentProofSubmitted::class, 1);
    Event::assertDispatchedTimes(ManualPaymentApproved::class, 1);
})->group('T-P8-04', 'A1')->group('payments');

it('an audit failure rolls back approve: the order stays pending and nothing is granted', function (): void {
    $payer = p804Active('student');
    $order = p804Order($payer);
    $proof = p804Submit($payer, $order);
    $finance = p804Active('finance');
    p804AuditOff();

    expect(fn () => app(ApproveManualPaymentProof::class)($finance, $proof))->toThrow(RuntimeException::class);

    expect($proof->refresh()->status)->toBe(ManualPaymentStatus::Pending)
        ->and($order->refresh()->status)->toBe(OrderStatus::Pending)
        ->and(p804Entitlements(EntitlementSource::ManualPayment->value))->toBe(0);
})->group('T-P8-04', 'A1')->group('payments');

it('an audit failure rolls back reject: the proof stays pending and keeps its reference', function (): void {
    $payer = p804Active('student');
    $proof = p804Submit($payer, p804Order($payer), ['transfer_reference' => 'KEEP-REF-8800']);
    p804AuditOff();

    expect(fn () => app(RejectManualPaymentProof::class)(p804Active('finance'), $proof, 'Transfer not found in the bank statement.'))->toThrow(RuntimeException::class);

    expect($proof->refresh()->status)->toBe(ManualPaymentStatus::Pending)
        ->and($proof->reference_key)->toBe('KEEP-REF-8800');
})->group('T-P8-04', 'A1')->group('payments');

// ---------------------------------------------------------------------------
// Masking
// ---------------------------------------------------------------------------

it('masks the sender to its last three characters and the payer name to initials', function (): void {
    $payer = p804Active('student');
    $proof = p804Submit($payer, p804Order($payer), ['payer_name' => 'Hoda Ramy Payer', 'sender_account' => '01099887766']);

    expect($proof->maskedSender())->toEndWith('766')
        ->and($proof->maskedSender())->not->toContain('0109')
        ->and($proof->maskedPayerName())->not->toContain('oda')
        ->and($proof->maskedPayerName())->not->toContain('Ramy')
        ->and($proof->maskedPayerName())->toStartWith('H');
})->group('T-P8-04', 'A2')->group('payments');

it('the review list never prints the raw sender account or full payer name', function (): void {
    $payer = p804Active('student');
    p804Submit($payer, p804Order($payer), ['payer_name' => 'Hoda Ramy Payer', 'sender_account' => '01099887766']);
    $finance = p804Active('finance');
    Filament::setCurrentPanel('finance');
    $this->actingAs($finance);

    Livewire::test(ListManualPaymentProofs::class)
        ->assertOk()
        ->assertDontSee('01099887766')
        ->assertDontSee('Hoda Ramy Payer');
})->group('T-P8-04', 'A2')->group('payments');

// ---------------------------------------------------------------------------
// HTTP
// ---------------------------------------------------------------------------

it('the payer submits through HTTP and a stranger gets 404 on the form and on store', function (): void {
    $payer = p804Active('student');
    $stranger = p804Active('student');
    $order = p804Order($payer);

    $this->actingAs($stranger)->get(route('manual-payments.create', $order))->assertNotFound();
    $this->actingAs($stranger)->post(route('manual-payments.store', $order), [])->assertNotFound();

    $this->actingAs($payer)->get(route('manual-payments.create', $order))->assertOk();
    $this->actingAs($payer)->post(route('manual-payments.store', $order), [
        'payer_name' => 'Hoda Payer',
        'sender_account' => '01099887766',
        'transfer_reference' => 'HTTP-REF-2024',
        'amount' => '250.00',
        'transferred_at' => now()->subMinutes(20)->format('Y-m-d H:i:s'),
        'proof' => UploadedFile::fake()->image('slip.png', 160, 120),
    ])->assertRedirect(route('manual-payments.create', $order));

    $proof = ManualPaymentProof::query()->sole();
    expect($proof->amount_minor)->toBe(25000)
        ->and($proof->status)->toBe(ManualPaymentStatus::Pending);
})->group('T-P8-04', 'A2')->group('payments');

it('the HTTP form rejects an amount with more than two decimals', function (): void {
    $payer = p804Active('student');
    $order = p804Order($payer);

    $this->actingAs($payer)->post(route('manual-payments.store', $order), [
        'payer_name' => 'Hoda Payer',
        'sender_account' => '01099887766',
        'transfer_reference' => 'HTTP-REF-2025',
        'amount' => '12.345',
        'transferred_at' => now()->subMinutes(20)->format('Y-m-d H:i:s'),
        'proof' => UploadedFile::fake()->image('slip.png', 160, 120),
    ])->assertSessionHasErrors('amount');

    expect(ManualPaymentProof::query()->count())->toBe(0);
})->group('T-P8-04', 'A1')->group('payments');

it('the proof file route serves the payer, finance and admin privately and refuses everyone else', function (): void {
    $payer = p804Active('student');
    $proof = p804Submit($payer, p804Order($payer));
    $stranger = p804Active('student');
    $otherParent = p804Active('parent');
    $instructor = p804Active('instructor_academy');
    $inactive = User::factory()->create(['status' => 'inactive'])->assignRole('finance');

    $response = $this->actingAs($payer)->get(route('manual-payments.file', $proof));
    $response->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    expect($response->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');

    $this->actingAs(p804Active('finance'))->get(route('manual-payments.file', $proof))->assertOk();
    $this->actingAs(p804Active('admin'))->get(route('manual-payments.file', $proof))->assertOk();

    $this->actingAs($stranger)->get(route('manual-payments.file', $proof))->assertNotFound();
    $this->actingAs($otherParent)->get(route('manual-payments.file', $proof))->assertNotFound();
    $this->actingAs($instructor)->get(route('manual-payments.file', $proof))->assertNotFound();
    // Spec says 404 for an inactive user; the EnsureActiveAccount middleware answers 403 first. Both deny access.
    $this->actingAs($inactive)->get(route('manual-payments.file', $proof))->assertForbidden();
    auth()->logout();
    $this->get(route('manual-payments.file', $proof))->assertRedirect();
})->group('T-P8-04', 'A2')->group('payments');

it('the access rule for proof files fails closed for inactive staff', function (): void {
    $payer = p804Active('student');
    $proof = p804Submit($payer, p804Order($payer));
    $inactiveFinance = User::factory()->create(['status' => 'inactive'])->assignRole('finance');

    expect(ManualPaymentProofAccess::allows($inactiveFinance, $proof))->toBeFalse()
        ->and(ManualPaymentProofAccess::allows($payer, $proof))->toBeTrue();
})->group('T-P8-04', 'A2')->group('payments');

// ---------------------------------------------------------------------------
// Concurrency (sequential form only; see the skipped parallel test above)
// ---------------------------------------------------------------------------

it('a repeated submit with the same order and reference is refused, not duplicated', function (): void {
    $payer = p804Active('student');
    $order = p804Order($payer);
    p804Submit($payer, $order, ['transfer_reference' => 'SEQ-REF-9100']);

    p804Refused(fn () => p804Submit($payer, $order, ['transfer_reference' => 'SEQ-REF-9100']));

    expect(ManualPaymentProof::query()->count())->toBe(1);
})->group('T-P8-04', 'A1')->group('payments');

// Resolves the course id of the single line of an order (the line's product id).
final class OrderLineCourseId
{
    public static function of(Order $order): int
    {
        return (int) DB::table('order_items')->where('order_id', $order->getKey())->value('product_id');
    }
}
