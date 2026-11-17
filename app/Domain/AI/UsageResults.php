<?php

declare(strict_types=1);

namespace App\Domain\AI;

use App\Domain\AI\Guards\GuardKind;
use App\Domain\AI\Queries\UsageReport;

/** Which ai_usage.result values are normal outcomes or guard blocks; everything else is a provider or output error. */
final class UsageResults
{
    /** @return list<string> */
    public static function notErrors(): array
    {
        return array_merge(UsageReport::OUTCOMES, array_map(static fn (GuardKind $kind): string => $kind->value, GuardKind::cases()));
    }
}
