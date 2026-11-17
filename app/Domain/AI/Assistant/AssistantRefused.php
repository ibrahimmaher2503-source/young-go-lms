<?php

declare(strict_types=1);

namespace App\Domain\AI\Assistant;

use App\Domain\AI\Quota\QuotaLedger;
use App\Domain\AI\UnavailableReason;
use Illuminate\Support\Carbon;
use RuntimeException;

/** The request was refused before any provider call: not eligible, a thread limit, or a quota/budget. No content is returned. */
final class AssistantRefused extends RuntimeException
{
    public const string THREAD_LIMIT = 'thread_limit';

    public const string SIMPLER_LIMIT = 'simpler_limit';

    /** The monthly budget of the course or the platform is used up (T-P7-02): the assistant pauses, lessons are unaffected. */
    public const string BUDGET_EXHAUSTED = 'budget_exhausted';

    /** @param  Carbon|null  $resetAt  when the limit that refused the request opens again (daily quotas, monthly budgets) */
    public function __construct(public readonly string $kind, public readonly ?UnavailableReason $reason = null, public readonly ?Carbon $resetAt = null)
    {
        parent::__construct(match (true) {
            $reason !== null => __('ai.unavailable.'.$reason->value),
            $kind === self::BUDGET_EXHAUSTED => __('ai.quota.budget_exhausted'),
            default => __('ai.'.$kind),
        });
    }

    public static function unavailable(UnavailableReason $reason): self
    {
        return new self('unavailable', $reason, $reason === UnavailableReason::QuotaExhausted ? QuotaLedger::dailyResetAt() : null);
    }

    public static function budgetExhausted(Carbon $resetAt): self
    {
        return new self(self::BUDGET_EXHAUSTED, null, $resetAt);
    }
}
