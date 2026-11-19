<?php

declare(strict_types=1);

// T-P9-05 | PRD 20, Checklist 11 | A1 A2

use App\Domain\Commerce\Actions\ApproveRefund;
use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Actions\ExecuteRefund;
use App\Domain\Commerce\Actions\ProcessPaymentWebhook;
use App\Domain\Commerce\Actions\RejectManualPaymentProof;
use App\Domain\Commerce\Actions\RequestRefund;
use App\Domain\Commerce\Actions\StartCheckout;
use App\Domain\Commerce\Actions\SubmitManualPaymentProof;
use App\Domain\Commerce\Contracts\PaymentGateway;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Commerce\ManualPaymentStatus;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\PaymentAttempt;
use App\Domain\Commerce\PaymentAttemptStatus;
use App\Domain\Commerce\PaymentEventOutcome;
use App\Domain\Commerce\Payments\FakePaymentGateway;
use App\Domain\Commerce\Payments\InvalidPaymentSignature;
use App\Domain\Commerce\ProductType;
use App\Domain\Commerce\RefundStatus;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Notifications\NotificationMatrix;
use App\Domain\Notifications\Notifications\TemplatedNotice;
use App\Domain\Shared\Markets\Market;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Traits\Localizable;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    app()->instance(PaymentGateway::class, new FakePaymentGateway);
    Notification::fake();
});

/** @return array{User, User, Order} */
function paymentNoticeOrder(string $locale = 'en'): array
{
    $payer = User::factory()->create([
        'status' => 'active',
        'locale' => $locale,
        'notification_preferences' => json_encode(['email' => false]),
    ])->assignRole('parent');
    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');
    GuardianChildLink::query()->create([
        'guardian_id' => $payer->id,
        'child_id' => $learner->id,
        'status' => GuardianChildLink::STATUS_VERIFIED,
        'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT,
    ]);
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة', 'en' => 'Payment notice course'],
        'slug_ar' => 'payment-notice-ar',
        'slug_en' => 'payment-notice-en',
        'status' => 'published',
        'access_mode' => 'paid',
    ]);
    CoursePrice::query()->create([
        'course_id' => $course->id,
        'market' => Market::Egypt->value,
        'currency' => 'EGP',
        'amount_minor' => 25000,
    ]);
    $order = app(CreateOrder::class)($payer, Market::Egypt, [new OrderLine(ProductType::Course, $course->id, $learner)], 'payment-notice-order');

    return [$payer, $learner, $order];
}

/** @return array{id: string, reference: string, amount_minor: int, currency: string, success: bool} */
function paymentNoticeCallback(PaymentAttempt $attempt, string $transaction, bool $success): array
{
    return [
        'id' => $transaction,
        'reference' => $attempt->reference,
        'amount_minor' => $attempt->amount_minor,
        'currency' => $attempt->currency,
        'success' => $success,
    ];
}

function assertPaymentStatusNotice(User $payer, User $learner, Order $order, string $event, string $url, int $count = 1): void
{
    $matchingEvent = static fn (TemplatedNotice $notice): bool => $notice->event() === $event;
    expect(Notification::sent($payer, TemplatedNotice::class, $matchingEvent))->toHaveCount($count);
    Notification::assertNotSentTo($learner, TemplatedNotice::class, $matchingEvent);

    $definition = NotificationMatrix::find($event);
    $values = ['order' => $order->reference];
    $locale = $payer->preferredLocale();
    Notification::assertSentTo($payer, TemplatedNotice::class, function (TemplatedNotice $notice, array $channels, User $recipient, string $sentLocale) use ($matchingEvent, $definition, $values, $locale, $url): bool {
        if (! $matchingEvent($notice)) {
            return false;
        }

        expect($channels)->toBe(['database', 'mail'])
            ->and($sentLocale)->toBe($locale);
        $render = new class
        {
            use Localizable;
        };
        expect($render->withLocale($sentLocale, fn (): array => $notice->toArray($recipient)))->toBe([
            'title' => __($definition->titleKey, $values, $locale),
            'message' => __($definition->bodyKey, $values, $locale),
            'url' => $url,
            'type' => 'TemplatedNotice',
        ]);

        return true;
    });
}

it('notifies the payer once per pending order only after a checkout session is created', function (): void {
    [$payer, $learner, $order] = paymentNoticeOrder('ar');
    $checkout = app(StartCheckout::class);
    app(PaymentGateway::class)->failNext();
    expect(fn () => $checkout($payer, $order, 'card'))->toThrow(ValidationException::class);
    Notification::assertNothingSent();

    $first = $checkout($payer, $order, 'card');
    assertPaymentStatusNotice($payer, $learner, $order, 'payment.pending', route('checkout.order', $order));
    expect($checkout($payer, $order, 'card')->id)->toBe($first->id);
    $this->travel((int) config('payments.attempt_ttl_minutes') + 1)->minutes();
    expect($checkout($payer, $order, 'card')->id)->not->toBe($first->id);

    assertPaymentStatusNotice($payer, $learner, $order, 'payment.pending', route('checkout.order', $order));
})->group('T-P9-05', 'A1', 'A2');

it('notifies the payer once per accepted decline and leaves the order pending for a retry', function (): void {
    [$payer, $learner, $order] = paymentNoticeOrder();
    $checkout = app(StartCheckout::class);
    $webhook = app(ProcessPaymentWebhook::class);
    $attempt = $checkout($payer, $order, 'card');
    $declined = paymentNoticeCallback($attempt, 'private-provider-transaction', false);

    expect(fn () => $webhook($declined, 'invalid-signature'))->toThrow(InvalidPaymentSignature::class);
    $unmatched = array_replace($declined, ['id' => 'unmatched', 'reference' => 'no-such-attempt']);
    expect($webhook($unmatched, FakePaymentGateway::sign($unmatched))->event->outcome)->toBe(PaymentEventOutcome::Unmatched);
    $wrongAmount = array_replace($declined, ['id' => 'wrong-amount', 'amount_minor' => 1]);
    expect($webhook($wrongAmount, FakePaymentGateway::sign($wrongAmount))->event->outcome)->toBe(PaymentEventOutcome::Flagged);
    Notification::assertNotSentTo($payer, TemplatedNotice::class, fn (TemplatedNotice $notice): bool => $notice->event() === 'payment.failed');

    $signature = FakePaymentGateway::sign($declined);
    expect($webhook($declined, $signature)->event->outcome)->toBe(PaymentEventOutcome::AttemptFailed);
    $webhook($declined, $signature);
    $secondDecline = array_replace($declined, ['id' => 'another-decline-for-same-attempt']);
    $webhook($secondDecline, FakePaymentGateway::sign($secondDecline));
    expect($attempt->fresh()->status)->toBe(PaymentAttemptStatus::Failed)
        ->and($order->fresh()->status)->toBe(OrderStatus::Pending);

    $retry = $checkout($payer, $order, 'card');
    $declinedAgain = paymentNoticeCallback($retry, 'private-provider-transaction-retry', false);
    expect($webhook($declinedAgain, FakePaymentGateway::sign($declinedAgain))->event->outcome)->toBe(PaymentEventOutcome::AttemptFailed);

    assertPaymentStatusNotice($payer, $learner, $order, 'payment.failed', route('checkout.order', $order), 2);
})->group('T-P9-05', 'A1', 'A2');

it('notifies the payer once for a rejected transfer proof without revealing the finance review reason', function (): void {
    [$payer, $learner, $order] = paymentNoticeOrder('ar');
    $finance = User::factory()->create(['status' => 'active'])->assignRole('finance');
    Storage::fake('local');
    $proof = app(SubmitManualPaymentProof::class)($payer, $order, [
        'payer_name' => 'Hoda Payer',
        'sender_account' => '01099887766',
        'transfer_reference' => 'PRIVATE-PROOF-REFERENCE',
        'amount_minor' => $order->total_minor,
        'transferred_at' => now()->subMinutes(20),
    ], UploadedFile::fake()->image('slip.png', 160, 120));
    Notification::assertNotSentTo($payer, TemplatedNotice::class, fn (TemplatedNotice $notice): bool => $notice->event() === 'payment.review_rejected');

    $reject = app(RejectManualPaymentProof::class);
    $reason = 'Private finance note for the rejected transfer.';
    expect($reject($finance, $proof, $reason)->status)->toBe(ManualPaymentStatus::Rejected);
    $reject($finance, $proof, $reason);
    expect($order->fresh()->status)->toBe(OrderStatus::Pending);

    assertPaymentStatusNotice($payer, $learner, $order, 'payment.review_rejected', route('manual-payments.create', $order));
})->group('T-P9-05', 'A1', 'A2');

it('notifies the payer once for an executed refund without revealing finance or provider details', function (): void {
    [$payer, $learner, $order] = paymentNoticeOrder();
    $finance = User::factory()->create(['status' => 'active'])->assignRole('finance');
    $attempt = app(StartCheckout::class)($payer, $order, 'card');
    $paid = paymentNoticeCallback($attempt, 'private-provider-payment', true);
    app(ProcessPaymentWebhook::class)($paid, FakePaymentGateway::sign($paid));

    $refund = app(RequestRefund::class)($finance, $order, $order->total_minor, 'Private finance note for the requested refund.');
    app(ApproveRefund::class)($finance, $refund);
    Notification::assertNotSentTo($payer, TemplatedNotice::class, fn (TemplatedNotice $notice): bool => $notice->event() === 'payment.refunded');

    $execute = app(ExecuteRefund::class);
    $result = $execute($finance, $refund);
    $execute($finance, $refund);
    expect($result->status)->toBe(RefundStatus::Executed)
        ->and($result->provider_refund_id)->not->toBeNull();

    assertPaymentStatusNotice($payer, $learner, $order, 'payment.refunded', route('payments.history'));
})->group('T-P9-05', 'A1', 'A2');
