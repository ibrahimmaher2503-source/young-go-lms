<?php

declare(strict_types=1);

namespace App\Domain\InstructorFinance\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\InstructorFinance\CommissionRule;
use App\Domain\InstructorFinance\SaleType;
use App\Domain\Instructors\Queries\PublicInstructors;
use App\Domain\Shared\Markets\Market;
use App\Models\Course;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Adds one commission rule (T-P8-09; rates are OPEN, O-008/O-010, so nothing is seeded). The actor needs `settlement.manage`.
 * Scope is an instructor, a course, both, or neither (the editable default). A percentage (basis points 0..10000) applies to
 * individual and bundle sales; a subscription rule is a fixed amount in the sale currency, earned per student-course-period.
 * A rule never changes after it is saved (ledger entries reference it): a new rate is a new rule, and the old one is closed
 * with CloseCommissionRule. Two rules of the same scope and sale type may not overlap in time (a fixed amount in another
 * currency may). Audited as `commission_rule.created`.
 */
final class SaveCommissionRule
{
    private const int MAX_FIXED_MINOR = 100_000_000_000;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data  instructor_id, course_id, sale_type, rate_bps, fixed_amount_minor, currency, effective_from (Y-m-d), effective_to (Y-m-d), note
     */
    public function __invoke(User $actor, array $data): CommissionRule
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('settlement.manage')) {
            throw new AuthorizationException;
        }

        $type = SaleType::tryFrom((string) ($data['sale_type'] ?? '')) ?? $this->refuse('sale_type', 'sale_type_invalid');
        $rate = isset($data['rate_bps']) ? (int) $data['rate_bps'] : null;
        $fixed = isset($data['fixed_amount_minor']) ? (int) $data['fixed_amount_minor'] : null;
        $currency = isset($data['currency']) && $data['currency'] !== '' ? strtoupper(trim((string) $data['currency'])) : null;

        if (($rate === null) === ($fixed === null)) {
            $this->refuse('rate_bps', 'rate_required');
        }
        if ($rate !== null && ($type === SaleType::Subscription || $rate < 0 || $rate > 10000)) {
            $this->refuse('rate_bps', $type === SaleType::Subscription ? 'subscription_fixed_only' : 'rate_invalid');
        }
        if ($fixed !== null) {
            if ($fixed < 0 || $fixed > self::MAX_FIXED_MINOR) {
                $this->refuse('fixed_amount_minor', 'amount_invalid');
            }
            if ($currency === null || ! in_array($currency, array_map(static fn (Market $m): string => $m->currency(), Market::cases()), true)) {
                $this->refuse('currency', 'currency_invalid');
            }
        } else {
            $currency = null;
        }

        $instructorId = isset($data['instructor_id']) ? (int) $data['instructor_id'] : null;
        $courseId = isset($data['course_id']) ? (int) $data['course_id'] : null;
        if ($instructorId !== null) {
            $instructor = User::query()->find($instructorId);
            if (! $instructor instanceof User || ! $instructor->hasAnyRole(PublicInstructors::ROLES)) {
                $this->refuse('instructor_id', 'instructor_invalid');
            }
        }
        if ($courseId !== null && ! Course::query()->whereKey($courseId)->exists()) {
            $this->refuse('course_id', 'course_invalid');
        }

        $from = $this->date($data['effective_from'] ?? null) ?? $this->refuse('effective_from', 'date_invalid');
        $to = ! isset($data['effective_to']) || $data['effective_to'] === '' ? null : ($this->date($data['effective_to']) ?? $this->refuse('effective_to', 'date_invalid'));
        if ($to !== null && $to->lt($from)) {
            $this->refuse('effective_to', 'dates_order');
        }

        $note = isset($data['note']) ? trim((string) $data['note']) : null;
        if ($note !== null && mb_strlen($note) > 255) {
            $this->refuse('note', 'note_invalid');
        }

        return DB::transaction(function () use ($actor, $type, $rate, $fixed, $currency, $instructorId, $courseId, $from, $to, $note): CommissionRule {
            $overlaps = CommissionRule::query()
                ->where('sale_type', $type->value)
                ->when($instructorId === null, static fn ($q) => $q->whereNull('instructor_id'), static fn ($q) => $q->where('instructor_id', $instructorId))
                ->when($courseId === null, static fn ($q) => $q->whereNull('course_id'), static fn ($q) => $q->where('course_id', $courseId))
                ->whereDate('effective_from', '<=', ($to ?? CarbonImmutable::create(9999, 12, 31))->format('Y-m-d'))
                ->where(static fn ($q) => $q->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from->format('Y-m-d')))
                ->get()
                ->contains(static fn (CommissionRule $other): bool => $rate !== null || ! $other->isFixed() || $other->currency === $currency);
            if ($overlaps) {
                $this->refuse('effective_from', 'overlap');
            }

            $rule = CommissionRule::query()->create([
                'instructor_id' => $instructorId, 'course_id' => $courseId, 'sale_type' => $type->value, 'rate_bps' => $rate,
                'fixed_amount_minor' => $fixed, 'currency' => $currency, 'effective_from' => $from->format('Y-m-d'),
                'effective_to' => $to?->format('Y-m-d'), 'note' => $note === '' ? null : $note, 'created_by' => $actor->getKey(),
            ]);

            $recorded = $this->audit->record('commission_rule.created', $rule, (int) $actor->getKey(), null, [
                'instructor_id' => $instructorId, 'course_id' => $courseId, 'sale_type' => $type->value, 'rate_bps' => $rate,
                'fixed_amount_minor' => $fixed, 'currency' => $currency, 'effective_from' => $from->format('Y-m-d'), 'effective_to' => $to?->format('Y-m-d'),
            ]);
            if ($recorded === null) {
                throw new RuntimeException('Unable to record commission rule audit.');
            }

            return $rule;
        });
    }

    private function date(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }
        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (Throwable) {
            return null;
        }

        return $date !== null && $date->format('Y-m-d') === $value ? $date : null;
    }

    private function refuse(string $field, string $key): never
    {
        throw ValidationException::withMessages([$field => __('ledger.'.$key)]);
    }
}
