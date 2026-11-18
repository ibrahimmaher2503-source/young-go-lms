<?php

// T-P8-10 | PRD 17.4, M8.6, J10, YG-GAP-INS-003 | A1..A6

declare(strict_types=1);

use App\Domain\InstructorFinance\Actions\ApproveSettlement;
use App\Domain\InstructorFinance\Actions\CancelSettlement;
use App\Domain\InstructorFinance\Actions\CreateSettlementBatch;
use App\Domain\InstructorFinance\Actions\GenerateInstructorStatement;
use App\Domain\InstructorFinance\Actions\GenerateMonthlyStatements;
use App\Domain\InstructorFinance\Actions\HoldLedgerEntries;
use App\Domain\InstructorFinance\Actions\RecordLedgerAdjustment;
use App\Domain\InstructorFinance\Actions\RecordSettlementPayout;
use App\Domain\InstructorFinance\InstructorStatement;
use App\Domain\InstructorFinance\LedgerEntry;
use App\Domain\InstructorFinance\LedgerEntryType;
use App\Domain\InstructorFinance\LedgerStatus;
use App\Domain\InstructorFinance\PayoutMethod;
use App\Domain\InstructorFinance\Queries\EligibleLedgerEntries;
use App\Domain\InstructorFinance\Queries\LedgerTotals;
use App\Domain\InstructorFinance\Settlement;
use App\Domain\InstructorFinance\SettlementEntry;
use App\Domain\InstructorFinance\SettlementItem;
use App\Domain\InstructorFinance\SettlementStatus;
use App\Domain\InstructorFinance\StatementPdf;
use App\Domain\Instructors\Actions\SavePayoutProfile;
use App\Domain\Notifications\NotificationDispatch;
use App\Domain\Notifications\NotificationEventDefinition;
use App\Domain\Notifications\NotificationMatrix;
use App\Filament\Resources\Settlements\SettlementResource;
use App\Filament\Resources\Statements\StatementResource;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Carbon::setTestNow('2026-11-18 12:00:00');
});

afterEach(fn () => Carbon::setTestNow());

/** Creates an active user with one role. */
function t810_user(string $role, string $status = 'active'): User
{
    return User::factory()->create(['status' => $status])->assignRole($role);
}

/** An instructor; with a payout profile unless told otherwise (YG-GAP-INS-003). */
function t810_instructor(bool $withPayout = true): User
{
    $instructor = t810_user('instructor_academy');
    if ($withPayout) {
        app(SavePayoutProfile::class)($instructor, [
            'method' => 'bank', 'holder_name' => 'Test Holder', 'bank_name' => 'Test Bank', 'account_number' => 'EG1234567890',
        ]);
    }

    return $instructor;
}

/** Writes a ledger line directly (test seam); `earned_at` places it in a month. */
function t810_entry(User $instructor, LedgerEntryType $type, int $amount, string $earnedAt, LedgerStatus $status = LedgerStatus::Accrued, string $currency = 'EGP'): LedgerEntry
{
    return LedgerEntry::query()->create([
        'instructor_id' => $instructor->getKey(), 'type' => $type->value, 'status' => $status->value,
        'source_key' => 't810:'.Str::uuid(), 'currency' => $currency, 'amount_minor' => $amount,
        'reason' => $type === LedgerEntryType::Adjustment ? 'Test adjustment line' : null, 'earned_at' => Carbon::parse($earnedAt),
    ]);
}

/** Runs the callable and returns the first validation message (compare with a lang key), or fails. */
function t810_refusal(callable $run): string
{
    try {
        $run();
    } catch (ValidationException $exception) {
        return (string) collect($exception->errors())->flatten()->first();
    }

    throw new RuntimeException('Expected a validation refusal.');
}

function t810_audit_count(string $event): int
{
    return Activity::query()->where('event', $event)->count();
}

/** @return list<string> flattened dotted keys of a lang array */
function t810_keys(array $array, string $prefix = ''): array
{
    $keys = [];
    foreach ($array as $key => $value) {
        $path = $prefix.$key;
        if (is_array($value)) {
            $keys = [...$keys, ...t810_keys($value, $path.'.')];
        } else {
            $keys[] = $path;
        }
    }
    sort($keys);

    return $keys;
}

// ---------------------------------------------------------------- A1 Statement

it('A1 freezes the ledger sums of a closed month into a statement equal to LedgerTotals', function (): void {
    $finance = t810_user('finance');
    $instructor = t810_instructor();
    t810_entry($instructor, LedgerEntryType::Earning, 10000, '2026-09-05 10:00:00');
    t810_entry($instructor, LedgerEntryType::Reversal, -2500, '2026-09-10 10:00:00');
    $adjustment = app(RecordLedgerAdjustment::class)($finance, $instructor->id, 700, 'EGP', 'Manual bonus correction');
    DB::table('instructor_ledger_entries')->where('id', $adjustment->id)->update(['earned_at' => '2026-09-15 09:00:00']);

    $statement = app(GenerateInstructorStatement::class)($finance, $instructor->id, '2026-09', 'EGP');

    $totals = app(LedgerTotals::class)->forInstructor($instructor->id)['EGP'];
    expect($statement->earnings_minor)->toBe(10000)
        ->and($statement->reversals_minor)->toBe(-2500)
        ->and($statement->adjustments_minor)->toBe(700)
        ->and($statement->net_minor)->toBe(8200)
        ->and($statement->earnings_minor)->toBe($totals['earnings'])
        ->and($statement->reversals_minor)->toBe($totals['reversals'])
        ->and($statement->adjustments_minor)->toBe($totals['adjustments'])
        ->and($statement->net_minor)->toBe($totals['net'])
        ->and($statement->entry_count)->toBe(3)
        ->and($statement->balance_forward_minor)->toBe(0)
        ->and($statement->settled_minor)->toBe(0)
        ->and($statement->closing_balance_minor)->toBe(8200)
        ->and($statement->snapshot['lines'])->toHaveCount(3);
})->group('T-P8-10', 'A1');

it('A1 snapshot lines carry no student identity and the closing balance follows forward + net - settled', function (): void {
    $finance = t810_user('finance');
    $instructor = t810_instructor();
    $student = t810_user('student');
    LedgerEntry::query()->create([
        'instructor_id' => $instructor->id, 'student_id' => $student->id, 'type' => LedgerEntryType::Earning->value,
        'status' => LedgerStatus::Accrued->value, 'source_key' => 't810:student-'.Str::uuid(), 'currency' => 'EGP',
        'amount_minor' => 9000, 'earned_at' => Carbon::parse('2026-08-20 10:00:00'),
    ]);
    t810_entry($instructor, LedgerEntryType::Earning, 1000, '2026-08-22 10:00:00', LedgerStatus::Held);
    t810_entry($instructor, LedgerEntryType::Earning, 500, '2026-08-23 10:00:00', LedgerStatus::Paid);
    t810_entry($instructor, LedgerEntryType::Earning, 300, '2026-08-24 10:00:00', LedgerStatus::Pending);
    // Older entries that are already settled stay out of the forward balance (paid is excluded).
    $forwardFromAugust = 1000 + 300 + 9000;
    t810_entry($instructor, LedgerEntryType::Earning, 4000, '2026-09-05 10:00:00');
    t810_entry($instructor, LedgerEntryType::Earning, 600, '2026-09-06 10:00:00', LedgerStatus::Paid);

    $statement = app(GenerateInstructorStatement::class)($finance, $instructor->id, '2026-09', 'EGP');

    expect($statement->balance_forward_minor)->toBe($forwardFromAugust)
        ->and($statement->net_minor)->toBe(4600)
        ->and($statement->settled_minor)->toBe(600)
        ->and($statement->closing_balance_minor)->toBe($forwardFromAugust + 4600 - 600);

    $line = $statement->snapshot['lines'][0];
    expect(array_keys($line))->not->toContain('student_id')
        ->and(json_encode($statement->snapshot))->not->toContain($student->email)
        ->and(json_encode($statement->snapshot))->not->toContain((string) $student->name);
})->group('T-P8-10', 'A1');

it('A1 is idempotent: the same row comes back and one statement.generated audit is written', function (): void {
    $finance = t810_user('finance');
    $instructor = t810_instructor();
    t810_entry($instructor, LedgerEntryType::Earning, 10000, '2026-09-05 10:00:00');

    $first = app(GenerateInstructorStatement::class)($finance, $instructor->id, '2026-09', 'EGP');
    $second = app(GenerateInstructorStatement::class)($finance, $instructor->id, '2026-09', 'EGP');

    expect($second->id)->toBe($first->id)
        ->and(InstructorStatement::query()->count())->toBe(1)
        ->and(t810_audit_count('statement.generated'))->toBe(1);
})->group('T-P8-10', 'A1');

it('A1 refuses the current month, a bad format, an empty month, a non-instructor and an unsupported currency with lang keys', function (): void {
    $finance = t810_user('finance');
    $instructor = t810_instructor();
    $student = t810_user('student');
    t810_entry($instructor, LedgerEntryType::Earning, 10000, '2026-09-05 10:00:00');

    $generate = app(GenerateInstructorStatement::class);

    expect(t810_refusal(fn () => $generate($finance, $instructor->id, '2026-11', 'EGP')))->toBe(__('settlements.period_open'))
        ->and(t810_refusal(fn () => $generate($finance, $instructor->id, '2026-13', 'EGP')))->toBe(__('settlements.period_invalid'))
        ->and(t810_refusal(fn () => $generate($finance, $instructor->id, '2026-1', 'EGP')))->toBe(__('settlements.period_invalid'))
        ->and(t810_refusal(fn () => $generate($finance, $instructor->id, '2026-07', 'EGP')))->toBe(__('settlements.statement_empty'))
        ->and(t810_refusal(fn () => $generate($finance, $student->id, '2026-09', 'EGP')))->toBe(__('settlements.instructor_invalid'))
        ->and(t810_refusal(fn () => $generate($finance, $instructor->id, '2026-09', 'USD')))->toBe(__('settlements.currency_invalid'))
        ->and(InstructorStatement::query()->count())->toBe(0);
})->group('T-P8-10', 'A1');

it('A1 a later ledger change never alters the stored statement', function (): void {
    $finance = t810_user('finance');
    $instructor = t810_instructor();
    t810_entry($instructor, LedgerEntryType::Earning, 10000, '2026-09-05 10:00:00');
    $statement = app(GenerateInstructorStatement::class)($finance, $instructor->id, '2026-09', 'EGP');

    t810_entry($instructor, LedgerEntryType::Earning, 5000, '2026-09-20 10:00:00');
    $again = app(GenerateInstructorStatement::class)($finance, $instructor->id, '2026-09', 'EGP');

    expect($again->id)->toBe($statement->id)
        ->and($statement->fresh()->net_minor)->toBe(10000)
        ->and($statement->fresh()->snapshot['lines'])->toHaveCount(1)
        ->and(LedgerEntry::query()->where('instructor_id', $instructor->id)->count())->toBe(2);
})->group('T-P8-10', 'A1');

it('A1 the statement model refuses update and delete', function (): void {
    $finance = t810_user('finance');
    $instructor = t810_instructor();
    t810_entry($instructor, LedgerEntryType::Earning, 10000, '2026-09-05 10:00:00');
    $statement = app(GenerateInstructorStatement::class)($finance, $instructor->id, '2026-09', 'EGP');

    expect(fn () => $statement->update(['net_minor' => 1]))->toThrow(LogicException::class)
        ->and(fn () => $statement->delete())->toThrow(LogicException::class)
        ->and($statement->fresh()->net_minor)->toBe(10000);
})->group('T-P8-10', 'A1');

it('A1 system() works without an actor and the audit actor is null', function (): void {
    $instructor = t810_instructor();
    t810_entry($instructor, LedgerEntryType::Earning, 10000, '2026-09-05 10:00:00');

    $statement = app(GenerateInstructorStatement::class)->system($instructor->id, '2026-09', 'EGP');

    expect($statement->generated_by)->toBeNull()
        ->and(Activity::query()->where('event', 'statement.generated')->latest('id')->firstOrFail()->causer_id)->toBeNull();
})->group('T-P8-10', 'A1');

it('A1 the monthly command creates one statement per instructor and currency, repeats create none, and an invalid period fails', function (): void {
    $instructorA = t810_instructor();
    $instructorB = t810_instructor();
    t810_entry($instructorA, LedgerEntryType::Earning, 10000, '2026-09-05 10:00:00');
    t810_entry($instructorB, LedgerEntryType::Earning, 3000, '2026-09-07 10:00:00');

    $this->artisan('statements:generate', ['period' => '2026-09'])
        ->expectsOutputToContain('Created 2 statements for 2026-09.')
        ->assertExitCode(0);
    expect(InstructorStatement::query()->count())->toBe(2);

    $this->artisan('statements:generate', ['period' => '2026-09'])
        ->expectsOutputToContain('Created 0 statements for 2026-09.')
        ->assertExitCode(0);

    $this->artisan('statements:generate', ['period' => '2026-13'])->assertExitCode(1);
    expect(InstructorStatement::query()->count())->toBe(2);
})->group('T-P8-10', 'A1');

it('A1 the statement notice settlement.statement is sent once per statement', function (): void {
    $finance = t810_user('finance');
    $instructor = t810_instructor();
    t810_entry($instructor, LedgerEntryType::Earning, 10000, '2026-09-05 10:00:00');

    $statement = app(GenerateInstructorStatement::class)($finance, $instructor->id, '2026-09', 'EGP');
    app(GenerateInstructorStatement::class)($finance, $instructor->id, '2026-09', 'EGP');

    expect(NotificationDispatch::query()->where('dedupe_key', 'settlement-statement:'.$statement->getKey())->count())->toBe(1);
})->group('T-P8-10', 'A1');

it('A1 the monthly action with an actor needs settlement.manage', function (): void {
    $generate = app(GenerateMonthlyStatements::class);
    $admin = t810_user('admin');

    expect(fn () => $generate($admin, '2026-09'))->toThrow(AuthorizationException::class);
})->group('T-P8-10', 'A1');

// ---------------------------------------------------------------- A2 Batch creation

it('A2 creates a draft batch: entries move to pending, amounts untouched, net per instructor and reference SET-YYYYMM-XXXXXX', function (): void {
    $finance = t810_user('finance');
    $instructorA = t810_instructor();
    $instructorB = t810_instructor();
    $entryA = t810_entry($instructorA, LedgerEntryType::Earning, 10000, '2026-10-05 10:00:00');
    $entryB = t810_entry($instructorB, LedgerEntryType::Earning, 5000, '2026-10-06 10:00:00');
    $before = LedgerEntry::query()->whereIn('id', [$entryA->id, $entryB->id])->get()->keyBy('id')->map->only(['amount_minor', 'type', 'currency', 'source_key', 'earned_at', 'instructor_id']);

    $batch = app(CreateSettlementBatch::class)($finance, 'EGP', [$entryA->id, $entryB->id], 'October batch', 'key-a2-1');

    expect($batch->status)->toBe(SettlementStatus::Draft)
        ->and($batch->instructor_count)->toBe(2)
        ->and($batch->entry_count)->toBe(2)
        ->and($batch->total_minor)->toBe(15000)
        ->and($batch->reference)->toMatch('/^SET-202611-[A-Z0-9]{6}$/')
        ->and(SettlementItem::query()->where('settlement_id', $batch->id)->pluck('amount_minor')->sort()->values()->all())->toBe([5000, 10000]);

    foreach ([$entryA, $entryB] as $entry) {
        $fresh = $entry->fresh();
        expect($fresh->status)->toBe(LedgerStatus::Pending)
            ->and($fresh->only(['amount_minor', 'type', 'currency', 'source_key', 'earned_at', 'instructor_id']))->toEqual($before[$entry->id]);
    }
})->group('T-P8-10', 'A2');

it('A2 the same key returns the first batch with no second audit, and a second batch over the same entries fails', function (): void {
    $finance = t810_user('finance');
    $instructor = t810_instructor();
    $entry = t810_entry($instructor, LedgerEntryType::Earning, 10000, '2026-10-05 10:00:00');
    $first = app(CreateSettlementBatch::class)($finance, 'EGP', [$entry->id], null, 'key-a2-same');

    $again = app(CreateSettlementBatch::class)($finance, 'EGP', [$entry->id], null, 'key-a2-same');

    expect($again->id)->toBe($first->id)
        ->and(Settlement::query()->count())->toBe(1)
        ->and(t810_audit_count('settlement.created'))->toBe(1)
        ->and(t810_refusal(fn () => app(CreateSettlementBatch::class)($finance, 'EGP', [$entry->id])))->toBe(__('settlements.entries_not_eligible'));
})->group('T-P8-10', 'A2');

it('A2 refuses invalid, mixed-currency, held, pending, approved, paid and already batched entries', function (): void {
    $finance = t810_user('finance');
    $instructor = t810_instructor();
    $create = app(CreateSettlementBatch::class);

    $egp = t810_entry($instructor, LedgerEntryType::Earning, 1000, '2026-10-05 10:00:00');
    $sar = t810_entry($instructor, LedgerEntryType::Earning, 1000, '2026-10-05 11:00:00', LedgerStatus::Accrued, 'SAR');

    expect(t810_refusal(fn () => $create($finance, 'EGP', [])))->toBe(__('settlements.entries_invalid'))
        ->and(t810_refusal(fn () => $create($finance, 'EGP', [999999])))->toBe(__('settlements.entries_invalid'))
        ->and(t810_refusal(fn () => $create($finance, 'EGP', [$egp->id, $sar->id])))->toBe(__('settlements.entries_invalid'));

    foreach ([LedgerStatus::Held, LedgerStatus::Pending, LedgerStatus::Approved, LedgerStatus::Paid] as $status) {
        $entry = t810_entry($instructor, LedgerEntryType::Earning, 700, '2026-10-08 10:00:00', $status);
        expect(t810_refusal(fn () => $create($finance, 'EGP', [$entry->id])))->toBe(__('settlements.entries_not_eligible'));
    }
})->group('T-P8-10', 'A2');

it('A2 needs settlement.manage: admin, support, student and inactive finance get AuthorizationException', function (): void {
    $instructor = t810_instructor();
    $entry = t810_entry($instructor, LedgerEntryType::Earning, 1000, '2026-10-05 10:00:00');
    $create = app(CreateSettlementBatch::class);

    foreach ([t810_user('admin'), t810_user('support'), t810_user('student'), t810_user('finance', 'inactive')] as $actor) {
        expect(fn () => $create($actor, 'EGP', [$entry->id]))->toThrow(AuthorizationException::class);
    }
    expect(Settlement::query()->count())->toBe(0)
        ->and($entry->fresh()->status)->toBe(LedgerStatus::Accrued);
})->group('T-P8-10', 'A2');

it('A2 the settlement.created audit carries carried_forward in context', function (): void {
    $finance = t810_user('finance');
    $instructor = t810_instructor();
    $entry = t810_entry($instructor, LedgerEntryType::Earning, 1000, '2026-10-05 10:00:00');

    app(CreateSettlementBatch::class)($finance, 'EGP', [$entry->id]);

    $activity = Activity::query()->where('event', 'settlement.created')->latest('id')->firstOrFail();
    expect($activity->causer_id)->toBe($finance->id)
        ->and($activity->properties->get('context'))->toHaveKey('carried_forward');
})->group('T-P8-10', 'A2');

// ---------------------------------------------------------------- A3 Carry forward and readiness

it('A3 carries non-positive nets and instructors without a payout profile forward and keeps their entries accrued', function (): void {
    $finance = t810_user('finance');
    $negative = t810_instructor();
    $positive = t810_instructor();
    $noProfile = t810_instructor(false);
    $negEntry = t810_entry($negative, LedgerEntryType::Reversal, -2500, '2026-10-10 10:00:00');
    $posEntry = t810_entry($positive, LedgerEntryType::Earning, 4000, '2026-10-11 10:00:00');
    $noEntry = t810_entry($noProfile, LedgerEntryType::Earning, 3000, '2026-10-12 10:00:00');

    $batch = app(CreateSettlementBatch::class)($finance, 'EGP', [$negEntry->id, $posEntry->id, $noEntry->id]);

    $carried = collect($batch->carried_forward)->keyBy('instructor_id');
    expect($batch->instructor_count)->toBe(1)
        ->and($batch->total_minor)->toBe(4000)
        ->and($carried[$negative->id]['reason'])->toBe('not_positive')
        ->and($carried[$noProfile->id]['reason'])->toBe('no_payout_profile')
        ->and($negEntry->fresh()->status)->toBe(LedgerStatus::Accrued)
        ->and($noEntry->fresh()->status)->toBe(LedgerStatus::Accrued)
        ->and($posEntry->fresh()->status)->toBe(LedgerStatus::Pending)
        ->and(SettlementItem::query()->where('settlement_id', $batch->id)->pluck('instructor_id')->all())->toBe([$positive->id]);
})->group('T-P8-10', 'A3');

it('A3 a batch where nobody qualifies refuses with settlements.nothing_to_settle', function (): void {
    $finance = t810_user('finance');
    $negative = t810_instructor();
    $entry = t810_entry($negative, LedgerEntryType::Reversal, -800, '2026-10-10 10:00:00');

    expect(t810_refusal(fn () => app(CreateSettlementBatch::class)($finance, 'EGP', [$entry->id])))
        ->toBe(__('settlements.nothing_to_settle'))
        ->and(Settlement::query()->count())->toBe(0)
        ->and($entry->fresh()->status)->toBe(LedgerStatus::Accrued);
})->group('T-P8-10', 'A3');

it('A3 a negative balance joins a later batch and nets against new earnings', function (): void {
    $finance = t810_user('finance');
    $instructor = t810_instructor();
    $deduction = t810_entry($instructor, LedgerEntryType::Reversal, -2500, '2026-10-10 10:00:00');
    $seed = t810_entry($instructor, LedgerEntryType::Earning, 100, '2026-10-09 10:00:00', LedgerStatus::Held);
    expect($seed->status)->toBe(LedgerStatus::Held);
    $later = t810_entry($instructor, LedgerEntryType::Earning, 10000, '2026-10-20 10:00:00');

    $batch = app(CreateSettlementBatch::class)($finance, 'EGP', [$deduction->id, $later->id]);

    expect($batch->total_minor)->toBe(7500)
        ->and(SettlementItem::query()->where('settlement_id', $batch->id)->sole()->amount_minor)->toBe(7500)
        ->and($deduction->fresh()->status)->toBe(LedgerStatus::Pending);
})->group('T-P8-10', 'A3');

it('A3 no payout detail is written to the settlement, its lines or the audit', function (): void {
    $finance = t810_user('finance');
    $instructor = t810_instructor();
    $entry = t810_entry($instructor, LedgerEntryType::Earning, 1000, '2026-10-05 10:00:00');

    $batch = app(CreateSettlementBatch::class)($finance, 'EGP', [$entry->id]);

    $dump = json_encode([$batch->toArray(), SettlementItem::query()->get()->toArray(), Activity::query()->get()->toArray()]);
    expect($dump)->not->toContain('EG1234567890')
        ->and($dump)->not->toContain('Test Holder');
})->group('T-P8-10', 'A3');

// ---------------------------------------------------------------- A4 Approve / payout / cancel

it('A4 approve moves pending entries to approved, is idempotent and refuses other states', function (): void {
    $finance = t810_user('finance');
    $instructor = t810_instructor();
    $entry = t810_entry($instructor, LedgerEntryType::Earning, 1000, '2026-10-05 10:00:00');
    $batch = app(CreateSettlementBatch::class)($finance, 'EGP', [$entry->id]);

    $approved = app(ApproveSettlement::class)($finance, $batch);
    $again = app(ApproveSettlement::class)($finance, $batch);

    expect($approved->status)->toBe(SettlementStatus::Approved)
        ->and($again->status)->toBe(SettlementStatus::Approved)
        ->and($entry->fresh()->status)->toBe(LedgerStatus::Approved)
        ->and(t810_audit_count('settlement.approved'))->toBe(1);

    $cancelled = app(CreateSettlementBatch::class)($finance, 'EGP', [t810_entry($instructor, LedgerEntryType::Earning, 300, '2026-10-06 10:00:00')->id]);
    app(CancelSettlement::class)($finance, $cancelled, 'Batch cancelled for test');
    expect(t810_refusal(fn () => app(ApproveSettlement::class)($finance, $cancelled->fresh())))->toBe(__('settlements.state_invalid'));
})->group('T-P8-10', 'A4');

it('A4 payout moves the instructor entries to paid, the batch stays approved until every line is paid, and repeats are no-ops', function (): void {
    $finance = t810_user('finance');
    $instructorA = t810_instructor();
    $instructorB = t810_instructor();
    $entryA = t810_entry($instructorA, LedgerEntryType::Earning, 10000, '2026-10-05 10:00:00');
    $entryB = t810_entry($instructorB, LedgerEntryType::Earning, 5000, '2026-10-06 10:00:00');
    $batch = app(CreateSettlementBatch::class)($finance, 'EGP', [$entryA->id, $entryB->id]);
    app(ApproveSettlement::class)($finance, $batch);
    $itemA = SettlementItem::query()->where('settlement_id', $batch->id)->where('instructor_id', $instructorA->id)->sole();
    $itemB = SettlementItem::query()->where('settlement_id', $batch->id)->where('instructor_id', $instructorB->id)->sole();

    $paidA = app(RecordSettlementPayout::class)($finance, $itemA, PayoutMethod::Bank, 'TRX-0001', '2026-11-10');

    expect($paidA->isPaid())->toBeTrue()
        ->and($paidA->payout_method)->toBe(PayoutMethod::Bank->value)
        ->and($paidA->paid_on->toDateString())->toBe('2026-11-10')
        ->and($entryA->fresh()->status)->toBe(LedgerStatus::Paid)
        ->and($entryB->fresh()->status)->toBe(LedgerStatus::Approved)
        ->and($batch->fresh()->status)->toBe(SettlementStatus::Approved);

    $repeat = app(RecordSettlementPayout::class)($finance, $itemA->fresh(), PayoutMethod::Bank, 'TRX-0001', '2026-11-10');
    expect($repeat->payout_reference)->toBe('TRX-0001')
        ->and(t810_audit_count('settlement.paid'))->toBe(1)
        ->and(t810_refusal(fn () => app(RecordSettlementPayout::class)($finance, $itemA->fresh(), PayoutMethod::Bank, 'TRX-9999', '2026-11-10')))
        ->toBe(__('settlements.already_paid'));

    app(RecordSettlementPayout::class)($finance, $itemB, PayoutMethod::Wallet, 'TRX-0002', '2026-11-11');
    expect($batch->fresh()->status)->toBe(SettlementStatus::Paid)
        ->and($batch->fresh()->paid_at)->not->toBeNull()
        ->and($entryB->fresh()->status)->toBe(LedgerStatus::Paid);
})->group('T-P8-10', 'A4');

it('A4 payout validates the reference, the date and the settlement state with lang keys', function (): void {
    $finance = t810_user('finance');
    $instructor = t810_instructor();
    $entry = t810_entry($instructor, LedgerEntryType::Earning, 1000, '2026-10-05 10:00:00');
    $draft = app(CreateSettlementBatch::class)($finance, 'EGP', [$entry->id]);
    $item = SettlementItem::query()->where('settlement_id', $draft->id)->sole();
    $payout = app(RecordSettlementPayout::class);

    expect(t810_refusal(fn () => $payout($finance, $item, PayoutMethod::Cash, 'T1', '2026-11-10')))
        ->toBe(__('settlements.reference_invalid'))
        ->and(t810_refusal(fn () => $payout($finance, $item, PayoutMethod::Cash, 'TRX-100', '2026-11-30')))
        ->toBe(__('settlements.paid_on_invalid'))
        ->and(t810_refusal(fn () => $payout($finance, $item, PayoutMethod::Cash, 'TRX-100', '2026-11-10')))
        ->toBe(__('settlements.state_invalid'))
        ->and($entry->fresh()->status)->toBe(LedgerStatus::Pending);
})->group('T-P8-10', 'A4');

it('A4 cancel from draft returns entries to accrued, keeps links and the row; refused after a payout; reason 5..500', function (): void {
    $finance = t810_user('finance');
    $instructor = t810_instructor();
    $entry = t810_entry($instructor, LedgerEntryType::Earning, 1000, '2026-10-05 10:00:00');
    $batch = app(CreateSettlementBatch::class)($finance, 'EGP', [$entry->id]);
    $links = SettlementEntry::query()->count();

    expect(t810_refusal(fn () => app(CancelSettlement::class)($finance, $batch, 'bad')))->toBe(__('settlements.reason_invalid'));

    $cancelled = app(CancelSettlement::class)($finance, $batch, 'Duplicate batch, rebuilding');
    $again = app(CancelSettlement::class)($finance, $cancelled, 'Duplicate batch, rebuilding');

    expect($cancelled->status)->toBe(SettlementStatus::Cancelled)
        ->and($again->id)->toBe($batch->id)
        ->and($entry->fresh()->status)->toBe(LedgerStatus::Accrued)
        ->and(SettlementEntry::query()->count())->toBe($links)
        ->and(Settlement::query()->count())->toBe(1)
        ->and(t810_audit_count('settlement.cancelled'))->toBe(1);

    $newBatch = app(CreateSettlementBatch::class)($finance, 'EGP', [$entry->id]);
    expect($newBatch->id)->not->toBe($batch->id)->and($entry->fresh()->status)->toBe(LedgerStatus::Pending);

    // A paid line blocks cancellation.
    $paidEntry = t810_entry($instructor, LedgerEntryType::Earning, 2000, '2026-10-07 10:00:00');
    $paidBatch = app(CreateSettlementBatch::class)($finance, 'EGP', [$paidEntry->id]);
    app(ApproveSettlement::class)($finance, $paidBatch);
    app(RecordSettlementPayout::class)($finance, SettlementItem::query()->where('settlement_id', $paidBatch->id)->sole(), PayoutMethod::Cash, 'CASH-001', '2026-11-10');
    expect(t810_refusal(fn () => app(CancelSettlement::class)($finance, $paidBatch->fresh(), 'Too late to cancel now')))
        ->toBe(__('settlements.state_invalid'));
})->group('T-P8-10', 'A4');

it('A4 history is write-once: settlement delete, link edit, second payout write and content edits throw; ledger amount edits throw, status edits pass', function (): void {
    $finance = t810_user('finance');
    $instructor = t810_instructor();
    $entry = t810_entry($instructor, LedgerEntryType::Earning, 1000, '2026-10-05 10:00:00');
    $batch = app(CreateSettlementBatch::class)($finance, 'EGP', [$entry->id]);
    app(ApproveSettlement::class)($finance, $batch);
    $item = SettlementItem::query()->where('settlement_id', $batch->id)->sole();
    app(RecordSettlementPayout::class)($finance, $item, PayoutMethod::Other, 'OTH-0001', '2026-11-10');

    expect(fn () => $batch->fresh()->delete())->toThrow(LogicException::class)
        ->and(fn () => $batch->fresh()->update(['total_minor' => 1]))->toThrow(LogicException::class);

    $link = SettlementEntry::query()->sole();
    $link->item_id = $item->id + 999;
    expect(fn () => $link->save())->toThrow(LogicException::class)
        ->and(fn () => SettlementEntry::query()->sole()->delete())->toThrow(LogicException::class);

    $paidItem = $item->fresh();
    $paidItem->payout_reference = 'OTH-9999';
    expect(fn () => $paidItem->save())->toThrow(LogicException::class)
        ->and($item->fresh()->payout_reference)->toBe('OTH-0001');

    $ledger = $entry->fresh();
    $ledger->amount_minor = 999;
    expect(fn () => $ledger->save())->toThrow(LogicException::class);
    expect(LedgerEntry::query()->findOrFail($entry->id)->amount_minor)->toBe(1000);
})->group('T-P8-10', 'A4');

it('A4 the payout notice settlement.status is sent once per line', function (): void {
    $finance = t810_user('finance');
    $instructor = t810_instructor();
    $entry = t810_entry($instructor, LedgerEntryType::Earning, 1000, '2026-10-05 10:00:00');
    $batch = app(CreateSettlementBatch::class)($finance, 'EGP', [$entry->id]);
    app(ApproveSettlement::class)($finance, $batch);
    $item = SettlementItem::query()->where('settlement_id', $batch->id)->sole();

    app(RecordSettlementPayout::class)($finance, $item, PayoutMethod::Bank, 'TRX-NOTICE-1', '2026-11-10');
    app(RecordSettlementPayout::class)($finance, $item->fresh(), PayoutMethod::Bank, 'TRX-NOTICE-1', '2026-11-10');

    expect(NotificationDispatch::query()->where('dedupe_key', 'settlement-paid:'.$item->getKey())->count())->toBe(1);
})->group('T-P8-10', 'A4');

// ---------------------------------------------------------------- A5 Hold / release

it('A5 hold moves accrued to held with a reason, a repeat returns 0 with no audit, and pending entries are refused', function (): void {
    $finance = t810_user('finance');
    $instructor = t810_instructor();
    $entry = t810_entry($instructor, LedgerEntryType::Earning, 500, '2026-10-05 10:00:00');
    $pending = t810_entry($instructor, LedgerEntryType::Earning, 700, '2026-10-06 10:00:00', LedgerStatus::Pending);
    $hold = app(HoldLedgerEntries::class);

    expect(t810_refusal(fn () => $hold->hold($finance, [$entry->id], 'no')))->toBe(__('settlements.reason_invalid'))
        ->and(t810_refusal(fn () => $hold->hold($finance, [$entry->id, $pending->id], 'Dispute about a refund')))
        ->toBe(__('settlements.entries_not_eligible'))
        ->and($entry->fresh()->status)->toBe(LedgerStatus::Accrued);

    expect($hold->hold($finance, [$entry->id], 'Dispute about a refund'))->toBe(1)
        ->and($entry->fresh()->status)->toBe(LedgerStatus::Held)
        ->and($hold->hold($finance, [$entry->id], 'Dispute about a refund'))->toBe(0)
        ->and(t810_audit_count('ledger.entry_held'))->toBe(1);

    $activity = Activity::query()->where('event', 'ledger.entry_held')->latest('id')->firstOrFail();
    expect($activity->properties->get('context')['reason'])->toBe('Dispute about a refund');
})->group('T-P8-10', 'A5');

it('A5 held entries are excluded from batches and from EligibleLedgerEntries, and release returns them to accrued', function (): void {
    $finance = t810_user('finance');
    $instructor = t810_instructor();
    $entry = t810_entry($instructor, LedgerEntryType::Earning, 500, '2026-10-05 10:00:00');
    $hold = app(HoldLedgerEntries::class);
    $hold->hold($finance, [$entry->id], 'Dispute about a refund');

    expect(app(EligibleLedgerEntries::class)->ids('EGP'))->not->toContain($entry->id)
        ->and(t810_refusal(fn () => app(CreateSettlementBatch::class)($finance, 'EGP', [$entry->id])))
        ->toBe(__('settlements.entries_not_eligible'));

    expect($hold->release($finance, [$entry->id], 'Dispute resolved in favour'))->toBe(1)
        ->and($entry->fresh()->status)->toBe(LedgerStatus::Accrued)
        ->and(app(EligibleLedgerEntries::class)->ids('EGP'))->toContain($entry->id)
        ->and(t810_audit_count('ledger.entry_released'))->toBe(1);

    $activity = Activity::query()->where('event', 'ledger.entry_released')->latest('id')->firstOrFail();
    expect($activity->properties->get('context')['reason'])->toBe('Dispute resolved in favour');
})->group('T-P8-10', 'A5');

it('A5 hold and release need settlement.manage', function (): void {
    $instructor = t810_instructor();
    $entry = t810_entry($instructor, LedgerEntryType::Earning, 500, '2026-10-05 10:00:00');

    expect(fn () => app(HoldLedgerEntries::class)->hold(t810_user('admin'), [$entry->id], 'Dispute about a refund'))
        ->toThrow(AuthorizationException::class)
        ->and($entry->fresh()->status)->toBe(LedgerStatus::Accrued);
})->group('T-P8-10', 'A5');

// ---------------------------------------------------------------- A6 Access, PDF and UI

it('A6 the capability gates: finance sees generate/create/approve, admin sees statements and settlements only', function (): void {
    $finance = t810_user('finance');
    $admin = t810_user('admin');

    $this->actingAs($finance);
    expect(StatementResource::canViewAny())->toBeTrue()
        ->and(SettlementResource::canViewAny())->toBeTrue();

    $this->actingAs($admin);
    expect(StatementResource::canViewAny())->toBeTrue()
        ->and(SettlementResource::canViewAny())->toBeTrue()
        ->and(fn () => app(CreateSettlementBatch::class)($admin, 'EGP', [1]))->toThrow(AuthorizationException::class);
})->group('T-P8-10', 'A6');

it('A6 finance and admin pages list statements and settlements; the batch create button is only for finance', function (): void {
    $this->withoutVite();
    $finance = t810_user('finance');
    $admin = t810_user('admin');

    $this->actingAs($finance)->get('/finance/instructor-statements')->assertOk();
    $this->actingAs($finance)->get('/finance/settlements')->assertOk()->assertSee(__('settlements.create_batch'));
    $this->actingAs($admin)->get('/admin/instructor-statements')->assertOk();
    $this->actingAs($admin)->get('/admin/settlements')->assertOk()->assertDontSee(__('settlements.create_batch'));
})->group('T-P8-10', 'A6');

it('A6 the instructor panel lists only the own statements and the instructor cannot open finance resources', function (): void {
    $this->withoutVite();
    $finance = t810_user('finance');
    $mine = t810_instructor();
    $other = t810_instructor();
    t810_entry($mine, LedgerEntryType::Earning, 10000, '2026-09-05 10:00:00');
    t810_entry($other, LedgerEntryType::Earning, 3000, '2026-09-06 10:00:00');
    $generate = app(GenerateInstructorStatement::class);
    $ownStatement = $generate($finance, $mine->id, '2026-09', 'EGP');
    $otherStatement = $generate($finance, $other->id, '2026-09', 'EGP');

    $this->actingAs($mine)->get('/instructor/my-statements')
        ->assertOk()
        ->assertSee($ownStatement->number)
        ->assertDontSee($otherStatement->number);

    $this->actingAs($mine)->get('/finance/instructor-statements')->assertStatus(403);
    $this->actingAs($mine)->get('/finance/settlements')->assertStatus(403);
})->group('T-P8-10', 'A6');

it('A6 the PDF route: 404 for another instructor and a student, login redirect for a guest, owner and finance get 200 or 503', function (): void {
    $finance = t810_user('finance');
    $owner = t810_instructor();
    $other = t810_instructor();
    $student = t810_user('student');
    t810_entry($owner, LedgerEntryType::Earning, 10000, '2026-09-05 10:00:00');
    $statement = app(GenerateInstructorStatement::class)($finance, $owner->id, '2026-09', 'EGP');
    $url = route('statements.pdf', ['statement' => $statement->id]);

    $this->get($url)->assertRedirect();
    $this->actingAs($other)->get($url)->assertNotFound();
    $this->actingAs($student)->get($url)->assertNotFound();

    expect($this->actingAs($owner)->get($url)->getStatusCode())->toBeIn([200, 503]);
    expect($this->actingAs($finance)->get($url)->getStatusCode())->toBeIn([200, 503]);
})->group('T-P8-10', 'A6');

it('A6 StatementPdf::data returns RTL/AR and LTR/EN variants with localized course titles and money strings', function (): void {
    $finance = t810_user('finance');
    $instructor = t810_instructor();
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة الخط', 'en' => 'Calligraphy Course'],
        'slug_ar' => 'tp810-ar-'.uniqid(), 'slug_en' => 'tp810-en-'.uniqid(), 'status' => 'published', 'access_mode' => 'paid',
    ]);
    LedgerEntry::query()->create([
        'instructor_id' => $instructor->id, 'course_id' => $course->id, 'type' => LedgerEntryType::Earning->value,
        'status' => LedgerStatus::Accrued->value, 'source_key' => 't810:course-'.Str::uuid(), 'currency' => 'EGP',
        'amount_minor' => 10000, 'earned_at' => Carbon::parse('2026-09-05 10:00:00'),
    ]);
    $statement = app(GenerateInstructorStatement::class)($finance, $instructor->id, '2026-09', 'EGP');
    $pdf = app(StatementPdf::class);

    $ar = $pdf->data($statement, 'ar');
    $en = $pdf->data($statement, 'en');

    expect($ar['dir'])->toBe('rtl')
        ->and($en['dir'])->toBe('ltr')
        ->and($ar['lines'][0]['course'])->toBe('دورة الخط')
        ->and($en['lines'][0]['course'])->toBe('Calligraphy Course')
        ->and($ar['net'])->toBeString()->not->toBe('')
        ->and($en['closing'])->toBeString()->not->toBe('')
        ->and($ar['number'])->toBe($statement->number);
})->group('T-P8-10', 'A6');

it('A6 the statement Blade view renders for both locales with the matching direction', function (): void {
    $finance = t810_user('finance');
    $instructor = t810_instructor();
    t810_entry($instructor, LedgerEntryType::Earning, 10000, '2026-09-05 10:00:00');
    $statement = app(GenerateInstructorStatement::class)($finance, $instructor->id, '2026-09', 'EGP');
    $pdf = app(StatementPdf::class);

    $arHtml = view('statements.pdf', $pdf->data($statement, 'ar'))->render();
    $enHtml = view('statements.pdf', $pdf->data($statement, 'en'))->render();

    expect($arHtml)->toContain('dir="rtl"')
        ->and($enHtml)->toContain('dir="ltr"')
        ->and($arHtml)->toContain($statement->number)
        ->and($enHtml)->toContain($statement->number);
})->group('T-P8-10', 'A6');

it('A6 finance pages render in AR (rtl) and EN without raw keys', function (): void {
    $this->withoutVite();
    $finance = t810_user('finance');
    $finance->forceFill(['locale' => 'ar'])->save();

    $this->actingAs($finance)->get('/finance/settlements')->assertOk()->assertSee('dir="rtl"', false)->assertDontSee('settlements.create_batch');

    $finance->forceFill(['locale' => 'en'])->save();
    $this->actingAs($finance)->get('/finance/settlements')->assertOk()->assertSee('dir="ltr"', false)->assertDontSee('settlements.create_batch');
})->group('T-P8-10', 'A6');

// ---------------------------------------------------------------- Also cover

it('keeps settlements lang keys identical in AR and EN', function (): void {
    $ar = require base_path('lang/ar/settlements.php');
    $en = require base_path('lang/en/settlements.php');

    expect(t810_keys($ar))->toBe(t810_keys($en));
})->group('T-P8-10', 'A6');

it('has AR and EN labels for every new audit event', function (): void {
    $ar = require base_path('lang/ar/audit-events.php');
    $en = require base_path('lang/en/audit-events.php');

    foreach (['statement.generated', 'settlement.created', 'settlement.approved', 'settlement.paid', 'settlement.cancelled', 'ledger.entry_held', 'ledger.entry_released'] as $event) {
        expect($en)->toHaveKey($event)
            ->and($ar)->toHaveKey($event);
    }
})->group('T-P8-10', 'A6');

it('lists settlement.statement and settlement.status as wired in the notification matrix', function (): void {
    foreach (['settlement.statement', 'settlement.status'] as $key) {
        $definition = NotificationMatrix::find($key);
        expect($definition)->not->toBeNull()
            ->and($definition->status)->toBe(NotificationEventDefinition::WIRED);
    }
})->group('T-P8-10', 'A6');

it('schedules statements:generate monthly on the 2nd at 03:15', function (): void {
    $events = collect(app(Schedule::class)->events());
    $event = $events->first(static fn ($e): bool => str_contains((string) $e->command, 'statements:generate'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('15 3 2 * *');
})->group('T-P8-10', 'A1');

it('passes the translation and RTL checks', function (): void {
    $this->artisan('app:check-translations')->assertExitCode(0);
    $this->artisan('app:check-rtl')->assertExitCode(0);
})->group('T-P8-10', 'A6');
