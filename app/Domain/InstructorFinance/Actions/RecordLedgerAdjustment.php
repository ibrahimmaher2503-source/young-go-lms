<?php

declare(strict_types=1);

namespace App\Domain\InstructorFinance\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\InstructorFinance\LedgerEntry;
use App\Domain\InstructorFinance\LedgerEntryType;
use App\Domain\Instructors\Queries\PublicInstructors;
use App\Domain\Shared\Markets\Market;
use App\Models\Course;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Manual correction of an instructor's ledger by finance (T-P8-09): a signed, non-zero amount with a mandatory reason (5..500
 * characters), for an instructor and optionally a course. Needs `settlement.manage` and an active actor. Append-only; a wrong
 * adjustment is fixed by another adjustment. `$key` makes the call idempotent (the same key returns the first entry); without
 * one every call is a new entry. Audited as `ledger.adjustment_recorded`.
 */
final class RecordLedgerAdjustment
{
    private const int MAX_MINOR = 100_000_000_000;

    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, int $instructorId, int $amountMinor, string $currency, string $reason, ?int $courseId = null, ?string $key = null): LedgerEntry
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('settlement.manage')) {
            throw new AuthorizationException;
        }

        $currency = strtoupper(trim($currency));
        $reason = trim($reason);
        $sourceKey = 'adjustment:'.($key === null || trim($key) === '' ? (string) Str::uuid() : mb_substr(trim($key), 0, 64));

        $existing = LedgerEntry::query()->where('source_key', $sourceKey)->first();
        if ($existing !== null) {
            return $existing;
        }

        $instructor = User::query()->find($instructorId);
        if (! $instructor instanceof User || ! $instructor->hasAnyRole(PublicInstructors::ROLES)) {
            $this->refuse('instructor_id', 'instructor_invalid');
        }
        if ($amountMinor === 0 || abs($amountMinor) > self::MAX_MINOR) {
            $this->refuse('amount', 'adjustment_amount_invalid');
        }
        if (! in_array($currency, array_map(static fn (Market $m): string => $m->currency(), Market::cases()), true)) {
            $this->refuse('currency', 'currency_invalid');
        }
        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            $this->refuse('reason', 'reason_invalid');
        }
        if ($courseId !== null && ! Course::query()->whereKey($courseId)->exists()) {
            $this->refuse('course_id', 'course_invalid');
        }

        try {
            return DB::transaction(function () use ($actor, $instructorId, $amountMinor, $currency, $reason, $courseId, $sourceKey): LedgerEntry {
                $entry = LedgerEntry::query()->create([
                    'instructor_id' => $instructorId, 'course_id' => $courseId, 'type' => LedgerEntryType::Adjustment->value, 'source_key' => $sourceKey,
                    'currency' => $currency, 'amount_minor' => $amountMinor, 'reason' => $reason, 'created_by' => $actor->getKey(), 'earned_at' => now(),
                ]);

                $recorded = $this->audit->record('ledger.adjustment_recorded', $entry, (int) $actor->getKey(), null, [
                    'instructor_id' => $instructorId, 'course_id' => $courseId, 'amount_minor' => $amountMinor, 'currency' => $currency,
                ], ['reason' => $reason]);
                if ($recorded === null) {
                    throw new RuntimeException('Unable to record ledger audit.');
                }

                return $entry;
            });
        } catch (UniqueConstraintViolationException) {
            return LedgerEntry::query()->where('source_key', $sourceKey)->firstOrFail();
        }
    }

    private function refuse(string $field, string $key): never
    {
        throw ValidationException::withMessages([$field => __('ledger.'.$key)]);
    }
}
