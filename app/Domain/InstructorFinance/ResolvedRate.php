<?php

declare(strict_types=1);

namespace App\Domain\InstructorFinance;

/** The commission rule that applies to one sale. `rule` is null when the config fallback was used. */
final readonly class ResolvedRate
{
    public function __construct(
        public ?CommissionRule $rule,
        public ?int $rateBps,
        public ?int $fixedAmountMinor,
        public string $source,
    ) {}

    public const string SOURCE_RULE = 'rule';

    public const string SOURCE_DEFAULT = 'config_default';

    /** Commission on a commissionable base: percentage rounded half up in integer math, or the fixed amount. */
    public function amountFor(int $baseMinor): int
    {
        if ($this->fixedAmountMinor !== null) {
            return $this->fixedAmountMinor;
        }

        return intdiv(max(0, $baseMinor) * (int) $this->rateBps + 5000, 10000);
    }
}
