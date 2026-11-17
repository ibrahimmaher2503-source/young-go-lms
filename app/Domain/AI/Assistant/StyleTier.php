<?php

declare(strict_types=1);

namespace App\Domain\AI\Assistant;

use App\Models\User;

/** Age band to wording tier (AI spec 4). Only the tier name leaves the platform, never a birth date. Cut-offs are OPEN (O-015). */
enum StyleTier: string
{
    case Kids = 'kids';
    case Teens = 'teens';
    case Adults = 'adults';

    /** Unknown age or unconfigured cut-offs fall to the simplest tier. */
    public static function forLearner(User $learner): self
    {
        $year = $learner->getAttribute('birth_year');
        $month = $learner->getAttribute('birth_month');
        if ($year === null || $month === null) {
            return self::Kids;
        }

        $now = now();
        $age = $now->year - (int) $year - ($now->month < (int) $month ? 1 : 0);
        $adult = config('ai.adult_age');
        $teen = config('ai.teen_age');

        return match (true) {
            is_numeric($adult) && $age >= (int) $adult => self::Adults,
            is_numeric($teen) && $age >= (int) $teen => self::Teens,
            default => self::Kids,
        };
    }
}
