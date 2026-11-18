<?php

// T-P8-11 | YG-GAP-PAY-003 | A1

declare(strict_types=1);

use App\Domain\Commerce\Actions\ReconcilePayments;
use App\Domain\Commerce\Actions\ReviewPaymentMismatch;
use App\Domain\Commerce\Contracts\PaymentGateway;
use App\Domain\Commerce\PaymentReconciliationMismatch;
use App\Domain\Commerce\Payments\VerifiedPaymentEvent;
use App\Filament\Pages\PaymentReconciliationPage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    Carbon::setTestNow('2026-11-18 09:00:00');
});

afterEach(fn () => Carbon::setTestNow());

/** Queues unmatched gateway transactions and runs one reconciliation day, so the page shows real mismatches. */
function t811p_seed_mismatches(int $count, string $prefix = 'page'): void
{
    for ($i = 1; $i <= $count; $i++) {
        app(PaymentGateway::class)->queueTransaction(new VerifiedPaymentEvent(
            'fake', $prefix.'-txn-'.$i, 'UNKNOWN-'.$prefix.'-'.$i, null, 25000, 'EGP', true, false, false, false,
        ));
    }
    app(ReconcilePayments::class)(CarbonImmutable::parse('2026-11-10'));
}

function t811p_user(string $role, string $status = 'active'): User
{
    return User::factory()->create(['status' => $status])->assignRole($role);
}

it('A1 admin and finance panels both list the payment reconciliation page', function (): void {
    $admin = t811p_user('admin');
    $finance = t811p_user('finance');

    $this->actingAs($admin)->get('/admin/payment-reconciliation')->assertOk()->assertSee('data-payment-reconciliation', false);
    $this->actingAs($finance)->get('/finance/payment-reconciliation')->assertOk()->assertSee('data-payment-reconciliation', false);
})->group('T-P8-11', 'A1');

it('A1 a learner and a user with neither payment permission get 403 on the page', function (): void {
    $student = t811p_user('student');
    $contentManager = t811p_user('content_manager');

    $this->actingAs($student)->get('/admin/payment-reconciliation')->assertForbidden();
    $this->actingAs($contentManager)->get('/admin/payment-reconciliation')->assertForbidden();
})->group('T-P8-11', 'A1');

it('A1 payment.view alone sees the page with no review button, and a forced markReviewed shows a denied notice', function (): void {
    t811p_seed_mismatches(1, 'view');
    $mismatch = PaymentReconciliationMismatch::query()->sole();
    $viewer = t811p_user('content_manager');
    $viewer->givePermissionTo('payment.view');

    $this->actingAs($viewer)->get('/admin/payment-reconciliation')
        ->assertOk()
        ->assertSee('data-mismatch="'.$mismatch->id.'"', false)
        ->assertDontSee('wire:click="markReviewed', false);

    Livewire::actingAs($viewer)->test(PaymentReconciliationPage::class)
        ->call('markReviewed', $mismatch->id)
        ->assertNotified(__('payment_reconciliation.denied'));

    expect($mismatch->fresh()->status->value)->toBe('open');
})->group('T-P8-11', 'A1');

it('A1 payment.approve_manual can mark a mismatch reviewed from the page', function (): void {
    t811p_seed_mismatches(1, 'approve');
    $mismatch = PaymentReconciliationMismatch::query()->sole();
    $admin = t811p_user('admin');

    $this->actingAs($admin)->get('/admin/payment-reconciliation')
        ->assertOk()
        ->assertSee('wire:click="markReviewed('.$mismatch->id.')"', false);

    Livewire::actingAs($admin)->test(PaymentReconciliationPage::class)
        ->call('markReviewed', $mismatch->id)
        ->assertNotified(__('payment_reconciliation.reviewed_done'));

    expect($mismatch->fresh()->status->value)->toBe('reviewed')
        ->and($mismatch->fresh()->reviewed_by)->toBe($admin->id);
})->group('T-P8-11', 'A1');

it('A1 an inactive account cannot review a mismatch', function (): void {
    t811p_seed_mismatches(1, 'inactive');
    $mismatch = PaymentReconciliationMismatch::query()->sole();
    $inactive = t811p_user('admin', 'inactive');

    expect(fn () => app(ReviewPaymentMismatch::class)($inactive, $mismatch))
        ->toThrow(AuthorizationException::class)
        ->and($mismatch->fresh()->status->value)->toBe('open');
})->group('T-P8-11', 'A1');

it('A1 the filter shows only reviewed mismatches when set to reviewed', function (): void {
    t811p_seed_mismatches(2, 'filter');
    $reviewed = PaymentReconciliationMismatch::query()->orderBy('id')->firstOrFail();
    $open = PaymentReconciliationMismatch::query()->orderByDesc('id')->firstOrFail();
    app(ReviewPaymentMismatch::class)(t811p_user('finance'), $reviewed);

    Livewire::actingAs(t811p_user('admin'))->test(PaymentReconciliationPage::class)
        ->set('filter', 'reviewed')
        ->assertSee('data-mismatch="'.$reviewed->id.'"', false)
        ->assertDontSee('data-mismatch="'.$open->id.'"', false);
})->group('T-P8-11', 'A1');

it('A1 the list is capped at 100 rows while the open count shows the full number', function (): void {
    t811p_seed_mismatches(101, 'cap');
    $admin = t811p_user('admin');

    $html = $this->actingAs($admin)->get('/admin/payment-reconciliation')->assertOk()->getContent();

    expect(substr_count((string) $html, 'data-mismatch="'))->toBe(100)
        ->and($html)->toContain('data-open-count>101<');
})->group('T-P8-11', 'A1');

it('A1 the page shows the last run tiles and the empty state when nothing is found', function (): void {
    $admin = t811p_user('admin');

    $this->actingAs($admin)->get('/admin/payment-reconciliation')
        ->assertOk()
        ->assertSee('data-reconciliation-never', false);

    t811p_seed_mismatches(1, 'tiles');
    $this->actingAs($admin)->get('/admin/payment-reconciliation')
        ->assertOk()
        ->assertSee('data-reconciliation-run', false)
        ->assertSee('data-open-count', false);

    PaymentReconciliationMismatch::query()->delete();
    $this->actingAs($admin)->get('/admin/payment-reconciliation')->assertOk()->assertSee('data-mismatch-empty', false);
})->group('T-P8-11', 'A1');

it('A1 renders in AR (rtl) and EN with no raw translation keys', function (): void {
    t811p_seed_mismatches(1, 'locale');
    $finance = t811p_user('finance');

    $finance->forceFill(['locale' => 'ar'])->save();
    $this->actingAs($finance)->get('/finance/payment-reconciliation')
        ->assertOk()
        ->assertSee('dir="rtl"', false)
        ->assertDontSee('payment_reconciliation.', false);

    $finance->forceFill(['locale' => 'en'])->save();
    $this->actingAs($finance)->get('/finance/payment-reconciliation')
        ->assertOk()
        ->assertSee('Payment reconciliation')
        ->assertDontSee('payment_reconciliation.', false);
})->group('T-P8-11', 'A1');

it('A1 the AR and EN reconciliation lang files have the same keys and every used type and reason', function (): void {
    $ar = require base_path('lang/ar/payment_reconciliation.php');
    $en = require base_path('lang/en/payment_reconciliation.php');

    $flatten = function (array $array, string $prefix = '') use (&$flatten): array {
        $keys = [];
        foreach ($array as $key => $value) {
            $path = $prefix.$key;
            $keys = [...$keys, ...(is_array($value) ? $flatten($value, $path.'.') : [$path])];
        }

        return $keys;
    };

    $arKeys = $flatten($ar);
    $enKeys = $flatten($en);
    sort($arKeys);
    sort($enKeys);

    expect($arKeys)->toBe($enKeys);
    foreach (['unmatched_transaction', 'amount_mismatch', 'status_mismatch', 'missing_at_gateway', 'refunded_at_gateway'] as $type) {
        expect($ar['types'])->toHaveKey($type)->and($en['types'])->toHaveKey($type);
    }
    foreach (['no_attempt_for_reference', 'amount_or_currency_differs', 'paid_at_gateway_not_locally', 'failed_at_gateway_paid_locally', 'not_in_gateway_listing', 'refunded', 'voided'] as $reason) {
        expect($ar['reasons'])->toHaveKey($reason)->and($en['reasons'])->toHaveKey($reason);
    }
})->group('T-P8-11', 'A1');

it('A1 the three reconciliation audit events have AR and EN labels', function (): void {
    $ar = require base_path('lang/ar/audit-events.php');
    $en = require base_path('lang/en/audit-events.php');

    foreach (['payment.reconciliation_completed', 'payment.reconciliation_mismatch_found', 'payment.reconciliation_mismatch_reviewed'] as $event) {
        expect($en)->toHaveKey($event)->and($ar)->toHaveKey($event);
    }
})->group('T-P8-11', 'A1');

it('A1 app:check-translations and app:check-rtl pass', function (): void {
    $this->artisan('app:check-translations')->assertExitCode(0);
    $this->artisan('app:check-rtl')->assertExitCode(0);
})->group('T-P8-11', 'A1');
