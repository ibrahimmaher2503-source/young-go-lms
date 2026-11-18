<?php

declare(strict_types=1);

namespace App\Domain\InstructorFinance\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\InstructorFinance\CommissionRule;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Ends a commission rule on a date (inclusive), the only change a rule allows. The date cannot be before the rule starts or
 * later than an end date it already has. Sales after that date fall back to the next rule or the config default. Needs
 * `settlement.manage`. Audited as `commission_rule.closed`.
 */
final class CloseCommissionRule
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, CommissionRule $rule, string $effectiveTo): CommissionRule
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('settlement.manage')) {
            throw new AuthorizationException;
        }

        try {
            $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $effectiveTo) === 1 ? CarbonImmutable::createFromFormat('!Y-m-d', $effectiveTo) : null;
        } catch (Throwable) {
            $to = null;
        }
        if ($to === null || $to->format('Y-m-d') !== $effectiveTo) {
            $this->refuse('date_invalid');
        }

        return DB::transaction(function () use ($actor, $rule, $to): CommissionRule {
            $locked = CommissionRule::query()->lockForUpdate()->findOrFail($rule->getKey());
            if ($to->lt($locked->effective_from) || ($locked->effective_to !== null && $to->gt($locked->effective_to))) {
                $this->refuse('close_date_invalid');
            }
            $before = $locked->effective_to?->format('Y-m-d');
            if ($before === $to->format('Y-m-d')) {
                return $locked;
            }

            $locked->forceFill(['effective_to' => $to->format('Y-m-d')])->save();

            $recorded = $this->audit->record('commission_rule.closed', $locked, (int) $actor->getKey(), ['effective_to' => $before], ['effective_to' => $to->format('Y-m-d')]);
            if ($recorded === null) {
                throw new RuntimeException('Unable to record commission rule audit.');
            }

            return $locked;
        });
    }

    private function refuse(string $key): never
    {
        throw ValidationException::withMessages(['effective_to' => __('ledger.'.$key)]);
    }
}
