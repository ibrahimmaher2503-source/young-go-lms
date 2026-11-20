<?php

declare(strict_types=1);

namespace App\Domain\AI\Assistant;

use App\Domain\Catalog\AgeBandResolver;
use App\Models\User;

/** Age band to wording tier (AI spec 4). Only the tier name leaves the platform. */
enum StyleTier: string
{
    case Kids = 'kids';
    case Teens = 'teens';
    case Adults = 'adults';

    /** Wording only. Eligibility is decided before the assistant selects a tier. */
    public static function forLearner(User $learner): self
    {
        $year = $learner->getAttribute('birth_year');
        $month = $learner->getAttribute('birth_month');
        if (! is_int($year) || ! is_int($month) || $month < 1 || $month > 12) {
            return self::Kids;
        }

        $tier = app(AgeBandResolver::class)->resolve($year, $month)?->style_tier;
        $resolved = $tier === null ? self::Kids : (self::tryFrom($tier) ?? self::Kids);
        if ($resolved === self::Adults && is_numeric($adultAge = config('ai.adult_age'))) {
            $now = now();
            $age = $now->year - $year - ($now->month < $month ? 1 : 0);
            if ($age < (int) $adultAge) {
                return self::Teens;
            }
        }

        return $resolved;
    }
}
