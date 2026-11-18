<?php

declare(strict_types=1);

namespace App\Domain\InstructorFinance\Actions;

use App\Domain\InstructorFinance\InstructorStatement;
use App\Domain\InstructorFinance\LedgerEntry;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Generates the statements of one closed month for every instructor and currency that has ledger entries in it, or for one
 * instructor. Existing statements are left as they are (idempotent). Returns how many were newly created.
 */
final class GenerateMonthlyStatements
{
    public function __construct(private readonly GenerateInstructorStatement $generate) {}

    public function __invoke(User $actor, string $period, ?int $instructorId = null): int
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('settlement.manage')) {
            throw new AuthorizationException;
        }

        return $this->run($period, $instructorId, static fn (GenerateInstructorStatement $g, int $i, string $c): mixed => $g($actor, $i, $period, $c));
    }

    public function system(string $period): int
    {
        return $this->run($period, null, static fn (GenerateInstructorStatement $g, int $i, string $c): mixed => $g->system($i, $period, $c));
    }

    /** @param  callable(GenerateInstructorStatement, int, string): mixed  $make */
    private function run(string $period, ?int $instructorId, callable $make): int
    {
        if (preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $period) !== 1) {
            throw ValidationException::withMessages(['period' => __('settlements.period_invalid')]);
        }
        $start = Carbon::createFromFormat('!Y-m', $period);
        if ($start === null || $start->copy()->endOfMonth()->greaterThanOrEqualTo(now())) {
            throw ValidationException::withMessages(['period' => __('settlements.period_open')]);
        }

        $pairs = LedgerEntry::query()->whereBetween('earned_at', [$start, $start->copy()->endOfMonth()])
            ->when($instructorId !== null, static fn ($q) => $q->where('instructor_id', $instructorId))
            ->select('instructor_id', 'currency')->distinct()->orderBy('instructor_id')->get();

        $created = 0;
        foreach ($pairs as $pair) {
            $instructor = (int) $pair->getAttribute('instructor_id');
            $currency = (string) $pair->getAttribute('currency');
            $existed = InstructorStatement::query()->where('instructor_id', $instructor)->where('currency', $currency)->where('period', $period)->exists();
            $make($this->generate, $instructor, $currency);
            $created += $existed ? 0 : 1;
        }

        return $created;
    }
}
