<?php

declare(strict_types=1);

use App\Domain\Audit\AuditLogger;
use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\Actions\ProcessPaymentWebhook;
use App\Domain\Commerce\Actions\StartCheckout;
use App\Domain\Commerce\Actions\SubmitManualPaymentProof;
use App\Domain\Commerce\Actions\TransitionOrder;
use App\Domain\Commerce\Contracts\PaymentGateway;
use App\Domain\Commerce\ManualPaymentProof;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\OrderStatus;
use App\Domain\Commerce\PaymentAttempt;
use App\Domain\Commerce\PaymentAttemptStatus;
use App\Domain\Commerce\PaymentEventOutcome;
use App\Domain\Commerce\Payments\FakePaymentGateway;
use App\Domain\Commerce\ProductType;
use App\Domain\Settings\Actions\EnableGatedFlag;
use App\Domain\Settings\PlatformFlag;
use App\Domain\Shared\Markets\Market;
use App\Domain\Shared\Markets\MarketResolver;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('keeps Saudi order and payment flows disabled even when market configuration is switched', function (): void {
    $this->seed(RolePermissionSeeder::class);
    $admin = User::factory()->create()->assignRole('super_admin');
    $payer = User::factory()->create();
    $flags = app(EnableGatedFlag::class);
    expect(fn () => $flags->handle(PlatformFlag::SaudiCheckout, $payer, 'fixture-approval'))->toThrow(AuthorizationException::class)
        ->and(fn () => $flags->handle(PlatformFlag::SaudiCheckout, $admin, ' '))->toThrow(InvalidArgumentException::class);
    $flags->handle(PlatformFlag::SaudiCheckout, $admin, 'fixture-approval');
    expect(DB::table('flag_approvals')->where('flag', PlatformFlag::SaudiCheckout->value)->where('approved_by', $admin->id)->value('approval_ref'))
        ->toBe('fixture-approval');

    config(['markets.SA.active' => true]);
    expect(Market::SaudiArabia->currency())->toBe('SAR')
        ->and(app(MarketResolver::class)->checkout('eg'))->toBe(Market::Egypt)
        ->and(fn () => app(MarketResolver::class)->checkout('sa'))->toThrow(DomainException::class);

    $line = new OrderLine(ProductType::Course, 1, $payer);
    expect(fn () => app(CreateOrder::class)($payer, Market::SaudiArabia, [$line], 'sa-order-test'))
        ->toThrow(ValidationException::class, __('commerce.market_unavailable'));
    expect(Order::query()->count())->toBe(0);

    $order = Order::query()->create([
        'reference' => 'YG-SAR-TEST', 'payer_id' => $payer->id, 'market' => 'SA', 'currency' => 'SAR',
        'subtotal_minor' => 1000, 'discount_minor' => 0, 'total_minor' => 1000, 'status' => OrderStatus::Pending,
        'idempotency_key' => 'legacy-test-sar', 'request_hash' => str_repeat('a', 64),
    ]);
    $gateway = new FakePaymentGateway;
    $checkout = new StartCheckout(app(AuditLogger::class), $gateway);

    expect(fn () => $checkout($payer, $order))->toThrow(ValidationException::class, __('payments.market_unavailable'));
    expect($gateway->calls)->toBeEmpty()->and(PaymentAttempt::query()->count())->toBe(0);
    expect(fn () => app(SubmitManualPaymentProof::class)($payer, $order, [
        'payer_name' => 'Test Payer', 'sender_account' => '12345', 'transfer_reference' => 'SA-TEST',
        'amount_minor' => 1000, 'transferred_at' => now(),
    ], UploadedFile::fake()->create('proof.pdf')))
        ->toThrow(ValidationException::class, __('manual_payment.order_invalid'));
    expect(ManualPaymentProof::query()->count())->toBe(0);
    expect(fn () => app(TransitionOrder::class)($order, OrderStatus::Paid))
        ->toThrow(ValidationException::class, __('commerce.market_unavailable'));

    $attempt = PaymentAttempt::query()->create([
        'order_id' => $order->id, 'gateway' => 'fake', 'sequence' => 1, 'reference' => 'fixture-sa-attempt',
        'status' => PaymentAttemptStatus::Created, 'active_order_id' => $order->id,
        'amount_minor' => 1000, 'currency' => 'SAR', 'expires_at' => now()->addMinute(),
    ]);
    app()->instance(PaymentGateway::class, $gateway);
    $payload = ['id' => 'fixture-sa-callback', 'reference' => $attempt->reference, 'amount_minor' => 1000, 'currency' => 'SAR', 'success' => true];
    $webhook = app(ProcessPaymentWebhook::class)($payload, FakePaymentGateway::sign($payload));

    expect($webhook->event->outcome)->toBe(PaymentEventOutcome::Flagged)
        ->and($webhook->event->reason)->toBe('market_unavailable')
        ->and($order->refresh()->status)->toBe(OrderStatus::Pending)
        ->and($attempt->refresh()->status)->toBe(PaymentAttemptStatus::Created);
})->group('T-P8-13', 'T-P8-13:A1', 'D-009', 'D-010')->group('payments');
