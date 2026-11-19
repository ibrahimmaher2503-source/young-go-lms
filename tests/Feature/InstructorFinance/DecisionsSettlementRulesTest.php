<?php

declare(strict_types=1);

// T-DECISIONS-2026-11-18 | O-012 | A1 A2

use App\Domain\InstructorFinance\Actions\ApproveSettlement;
use App\Domain\InstructorFinance\Actions\CreateSettlementBatch;
use App\Domain\InstructorFinance\LedgerEntry;
use App\Domain\InstructorFinance\LedgerEntryType;
use App\Domain\InstructorFinance\LedgerStatus;
use App\Domain\InstructorFinance\Settlement;
use App\Domain\InstructorFinance\SettlementStatus;
use App\Domain\Instructors\Actions\SavePayoutProfile;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Carbon::setTestNow('2026-11-18 12:00:00');
    // The decided values, set here because phpunit.xml relaxes them for the legacy suites.
    config([
        'commissions.settlement.four_eyes' => true,
        'commissions.settlement.minimum_payout_minor' => ['EGP' => 50000, 'SAR' => 10000],
    ]);
});

afterEach(fn () => Carbon::setTestNow());

/** A finance user (holds settlement.manage). */
function dsrFinance(string $role = 'finance'): User
{
    return User::factory()->create(['status' => 'active'])->assignRole($role);
}

/** An instructor with a bank payout profile, so the readiness check passes. */
function dsrInstructor(): User
{
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    app(SavePayoutProfile::class)($instructor, [
        'method' => 'bank', 'holder_name' => 'Decision Holder', 'bank_name' => 'Decision Bank', 'account_number' => 'EG9876543210',
    ]);

    return $instructor;
}

/** One accrued earning line written directly (test seam, same as the ledger writer's output). */
function dsrEntry(User $instructor, int $amount, string $currency = 'EGP'): LedgerEntry
{
    return LedgerEntry::query()->create([
        'instructor_id' => $instructor->getKey(), 'type' => LedgerEntryType::Earning->value, 'status' => LedgerStatus::Accrued->value,
        'source_key' => 'dsr:'.Str::uuid(), 'currency' => $currency, 'amount_minor' => $amount, 'earned_at' => Carbon::parse('2026-10-10 10:00:00'),
    ]);
}

function dsrAudits(string $event): int
{
    return Activity::query()->where('event', $event)->count();
}

/** @param  list<LedgerEntry>  $entries */
function dsrBatch(User $actor, string $currency, array $entries): Settlement
{
    return app(CreateSettlementBatch::class)($actor, $currency, array_map(static fn (LedgerEntry $e): int => $e->id, $entries));
}

// ---------------------------------------------------------------- A1: four-eyes

it('refuses the approval by the finance user who created the batch and leaves the batch and entries untouched', function (): void {
    $creator = dsrFinance();
    $entry = dsrEntry(dsrInstructor(), 60000);
    $batch = dsrBatch($creator, 'EGP', [$entry]);
    $approvalsBefore = dsrAudits('settlement.approved');

    $message = null;
    try {
        app(ApproveSettlement::class)($creator, $batch);
    } catch (ValidationException $exception) {
        $message = $exception->errors()['settlement'][0] ?? null;
    }

    expect($message)->toBe(__('settlements.four_eyes_refused'))
        ->and($batch->fresh()->status)->toBe(SettlementStatus::Draft)
        ->and($entry->fresh()->status)->toBe(LedgerStatus::Pending)
        ->and(dsrAudits('settlement.approved'))->toBe($approvalsBefore);
})->group('T-DECISIONS-2026-11-18', 'O-012', 'A1');

it('lets a different finance user approve a batch that another finance user created', function (): void {
    $creator = dsrFinance();
    $approver = dsrFinance();
    $entry = dsrEntry(dsrInstructor(), 60000);
    $batch = dsrBatch($creator, 'EGP', [$entry]);

    $approved = app(ApproveSettlement::class)($approver, $batch);

    expect($approved->status)->toBe(SettlementStatus::Approved)
        ->and($approved->approved_by)->toBe($approver->id)
        ->and($entry->fresh()->status)->toBe(LedgerStatus::Approved);
})->group('T-DECISIONS-2026-11-18', 'O-012', 'A1');

it('lets a super_admin approve a batch they created themselves', function (): void {
    $admin = dsrFinance('super_admin');
    $batch = dsrBatch($admin, 'EGP', [dsrEntry(dsrInstructor(), 60000)]);

    expect(app(ApproveSettlement::class)($admin, $batch)->status)->toBe(SettlementStatus::Approved);
})->group('T-DECISIONS-2026-11-18', 'O-012', 'A1');

it('lets the creator approve their own batch only when four-eyes is switched off', function (): void {
    config(['commissions.settlement.four_eyes' => false]);
    $creator = dsrFinance();
    $batch = dsrBatch($creator, 'EGP', [dsrEntry(dsrInstructor(), 60000)]);

    expect(app(ApproveSettlement::class)($creator, $batch)->status)->toBe(SettlementStatus::Approved);
})->group('T-DECISIONS-2026-11-18', 'O-012', 'A1');

// ---------------------------------------------------------------- A2: approval is idempotent

it('returns the already approved batch on a second approval without a second audit row', function (): void {
    $creator = dsrFinance();
    $approver = dsrFinance();
    $batch = dsrBatch($creator, 'EGP', [dsrEntry(dsrInstructor(), 60000)]);
    app(ApproveSettlement::class)($approver, $batch);
    $approvalsBefore = dsrAudits('settlement.approved');

    $again = app(ApproveSettlement::class)($approver, $batch->fresh());

    expect($again->status)->toBe(SettlementStatus::Approved)
        ->and(dsrAudits('settlement.approved'))->toBe($approvalsBefore);
})->group('T-DECISIONS-2026-11-18', 'O-012', 'A2');

// ---------------------------------------------------------------- A3: minimum payout

it('carries an instructor below the EGP minimum as below_minimum and keeps the entries accrued', function (): void {
    $finance = dsrFinance();
    $low = dsrInstructor();
    $ok = dsrInstructor();
    $lowEntry = dsrEntry($low, 49999);
    $okEntry = dsrEntry($ok, 50000);

    $batch = dsrBatch($finance, 'EGP', [$lowEntry, $okEntry]);

    $carried = collect($batch->carried_forward)->keyBy('instructor_id');
    expect($batch->instructor_count)->toBe(1)
        ->and($batch->total_minor)->toBe(50000)
        ->and($carried[$low->id]['reason'])->toBe('below_minimum')
        ->and($carried[$low->id]['amount_minor'])->toBe(49999)
        ->and($carried->has($ok->id))->toBeFalse()
        ->and($lowEntry->fresh()->status)->toBe(LedgerStatus::Accrued)
        ->and($okEntry->fresh()->status)->toBe(LedgerStatus::Pending);
})->group('T-DECISIONS-2026-11-18', 'O-012', 'A3');

it('uses the SAR minimum of 10000 for SAR batches', function (): void {
    $finance = dsrFinance();
    $low = dsrInstructor();
    $ok = dsrInstructor();
    $lowEntry = dsrEntry($low, 9999, 'SAR');
    $okEntry = dsrEntry($ok, 10000, 'SAR');

    $batch = dsrBatch($finance, 'SAR', [$lowEntry, $okEntry]);

    expect($batch->instructor_count)->toBe(1)
        ->and(collect($batch->carried_forward)->firstWhere('instructor_id', $low->id)['reason'])->toBe('below_minimum')
        ->and($lowEntry->fresh()->status)->toBe(LedgerStatus::Accrued)
        ->and($okEntry->fresh()->status)->toBe(LedgerStatus::Pending);
})->group('T-DECISIONS-2026-11-18', 'O-012', 'A3');

it('refuses the batch with nothing_to_settle when every instructor is below the minimum', function (): void {
    $finance = dsrFinance();
    $entry = dsrEntry(dsrInstructor(), 49999);
    $message = null;
    try {
        dsrBatch($finance, 'EGP', [$entry]);
    } catch (ValidationException $exception) {
        $message = $exception->errors()['entries'][0] ?? null;
    }

    expect($message)->toBe(__('settlements.nothing_to_settle'))
        ->and(Settlement::query()->count())->toBe(0)
        ->and($entry->fresh()->status)->toBe(LedgerStatus::Accrued);
})->group('T-DECISIONS-2026-11-18', 'O-012', 'A3');

// ---------------------------------------------------------------- A4: wording

it('has the carry_below_minimum and four_eyes_refused texts in AR and EN', function (): void {
    foreach (['ar', 'en'] as $locale) {
        app()->setLocale($locale);
        expect(__('settlements.carry_below_minimum'))->not->toBe('settlements.carry_below_minimum')
            ->and(__('settlements.four_eyes_refused'))->not->toBe('settlements.four_eyes_refused');
    }
    app()->setLocale('en');
})->group('T-DECISIONS-2026-11-18', 'O-012', 'A4');
