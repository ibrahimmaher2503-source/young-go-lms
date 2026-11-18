<?php

declare(strict_types=1);

use App\Domain\InstructorFinance\Actions\ApproveSettlement;
use App\Domain\InstructorFinance\Actions\CancelSettlement;
use App\Domain\InstructorFinance\Actions\CloseCommissionRule;
use App\Domain\InstructorFinance\Actions\CreateSettlementBatch;
use App\Domain\InstructorFinance\Actions\RecordSettlementPayout;
use App\Domain\InstructorFinance\Actions\SaveCommissionRule;
use App\Domain\InstructorFinance\LedgerEntry;
use App\Domain\InstructorFinance\LedgerEntryType;
use App\Domain\InstructorFinance\LedgerStatus;
use App\Domain\InstructorFinance\PayoutMethod;
use App\Domain\InstructorFinance\Settlement;
use App\Domain\InstructorFinance\SettlementItem;
use App\Domain\InstructorFinance\SettlementStatus;
use App\Domain\Instructors\Actions\SavePayoutProfile;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->finance = User::factory()->create(['status' => 'active'])->assignRole('finance');
    $this->support = User::factory()->create(['status' => 'active'])->assignRole('support');
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    app(SavePayoutProfile::class)($instructor, [
        'method' => 'bank',
        'holder_name' => 'Test Holder',
        'bank_name' => 'Test Bank',
        'account_number' => 'EG1234567890',
    ]);
    $this->entry = LedgerEntry::query()->create([
        'instructor_id' => $instructor->id,
        'type' => LedgerEntryType::Earning->value,
        'status' => LedgerStatus::Accrued->value,
        'source_key' => 'settlement-edge:'.Str::uuid(),
        'currency' => 'EGP',
        'amount_minor' => 1000,
        'earned_at' => now()->subMonth(),
    ]);
});

it('rolls back a new settlement when its audit cannot be written', function (): void {
    activity()->disableLogging();

    expect(fn () => app(CreateSettlementBatch::class)($this->finance, 'EGP', [$this->entry->id]))
        ->toThrow(RuntimeException::class, 'Unable to record settlement audit.')
        ->and(Settlement::query()->exists())->toBeFalse()
        ->and(SettlementItem::query()->exists())->toBeFalse()
        ->and($this->entry->fresh()->status)->toBe(LedgerStatus::Accrued);
})->group('T-P8-10', 'PRD-17.4');

it('rejects settlement transitions that would change only part of the ledger', function (string $operation): void {
    $batch = app(CreateSettlementBatch::class)($this->finance, 'EGP', [$this->entry->id]);
    if ($operation === 'payout') {
        $batch = app(ApproveSettlement::class)($this->finance, $batch);
    }
    $this->entry->update(['status' => LedgerStatus::Held->value]);
    $item = $batch->items()->sole();
    $run = match ($operation) {
        'approve' => fn () => app(ApproveSettlement::class)($this->finance, $batch),
        'cancel' => fn () => app(CancelSettlement::class)($this->finance, $batch, 'Incorrect ledger selection'),
        'payout' => fn () => app(RecordSettlementPayout::class)($this->finance, $item, PayoutMethod::Bank, 'PAY-123', today()->toDateString()),
    };

    expect($run)->toThrow(ValidationException::class, __('settlements.state_invalid'))
        ->and($batch->fresh()->status)->toBe($batch->status)
        ->and($this->entry->fresh()->status)->toBe(LedgerStatus::Held)
        ->and($item->fresh()->paid_at)->toBeNull();
})->with(['approve', 'cancel', 'payout'])->group('T-P8-10', 'PRD-17.4');

it('rolls back settlement transitions that cannot be audited', function (string $operation): void {
    $batch = app(CreateSettlementBatch::class)($this->finance, 'EGP', [$this->entry->id]);
    if ($operation === 'payout') {
        $batch = app(ApproveSettlement::class)($this->finance, $batch);
    }
    $item = $batch->items()->sole();
    activity()->disableLogging();
    $run = match ($operation) {
        'approve' => fn () => app(ApproveSettlement::class)($this->finance, $batch),
        'cancel' => fn () => app(CancelSettlement::class)($this->finance, $batch, 'Incorrect ledger selection'),
        'payout' => fn () => app(RecordSettlementPayout::class)($this->finance, $item, PayoutMethod::Bank, 'PAY-123', today()->toDateString()),
    };

    expect($run)->toThrow(RuntimeException::class, 'Unable to record settlement audit.')
        ->and($batch->fresh()->status)->toBe($batch->status)
        ->and($this->entry->fresh()->status)->toBe($operation === 'payout' ? LedgerStatus::Approved : LedgerStatus::Pending)
        ->and($item->fresh()->paid_at)->toBeNull();
})->with(['approve', 'cancel', 'payout'])->group('T-P8-10', 'PRD-17.4');

it('validates settlement notes and payout dates and restricts mutations to finance', function (): void {
    $create = app(CreateSettlementBatch::class);
    expect(fn () => $create($this->finance, 'EGP', [$this->entry->id], str_repeat('a', 256)))
        ->toThrow(ValidationException::class, __('settlements.note_invalid'));
    $batch = $create($this->finance, 'EGP', [$this->entry->id]);
    $item = $batch->items()->sole();
    $approve = app(ApproveSettlement::class);
    $cancel = app(CancelSettlement::class);
    $payout = app(RecordSettlementPayout::class);

    expect(fn () => $approve($this->support, $batch))->toThrow(AuthorizationException::class)
        ->and(fn () => $cancel($this->support, $batch, 'Incorrect ledger selection'))->toThrow(AuthorizationException::class)
        ->and(fn () => $payout($this->support, $item, PayoutMethod::Bank, 'PAY-123', today()->toDateString()))->toThrow(AuthorizationException::class)
        ->and(fn () => $payout($this->finance, $item, PayoutMethod::Bank, 'PAY-123', 'not-a-date'))
        ->toThrow(ValidationException::class, __('settlements.paid_on_invalid'))
        ->and($batch->fresh()->status)->toBe(SettlementStatus::Draft)
        ->and($item->fresh()->paid_at)->toBeNull();
})->group('T-P8-10', 'PRD-17.4');

it('keeps an open commission rule intact when closing is forbidden, invalid or unaudited', function (): void {
    $rule = app(SaveCommissionRule::class)($this->finance, [
        'sale_type' => 'individual',
        'effective_from' => '2026-01-01',
        'rate_bps' => 1200,
    ]);
    $close = app(CloseCommissionRule::class);
    expect(fn () => $close($this->support, $rule, '2026-11-01'))->toThrow(AuthorizationException::class)
        ->and(fn () => $close($this->finance, $rule, '2026-13-01'))->toThrow(ValidationException::class, __('ledger.date_invalid'))
        ->and(fn () => $close($this->finance, $rule, '2026-01-xx'))->toThrow(ValidationException::class, __('ledger.date_invalid'));
    activity()->disableLogging();
    expect(fn () => $close($this->finance, $rule, '2026-11-01'))
        ->toThrow(RuntimeException::class, 'Unable to record commission rule audit.')
        ->and($rule->fresh()->effective_to)->toBeNull();
})->group('T-P8-09', 'PRD-17.4');
