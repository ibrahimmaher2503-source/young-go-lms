<?php

declare(strict_types=1);

namespace App\Domain\Instructors\Queries;

use App\Domain\Instructors\Models\InstructorAgreement;
use App\Domain\Instructors\Models\InstructorAgreementAcceptance;
use App\Models\User;

/**
 * The one rule for agreement re-acceptance (YG-GAP-INS-002, A2): an independent instructor must accept the newest published
 * version before creating a course. No published version means nothing to accept. Academy instructors are not asked
 * (they are covered by their staff contract).
 */
final class InstructorAgreements
{
    public static function current(): ?InstructorAgreement
    {
        return InstructorAgreement::query()->where('status', InstructorAgreement::PUBLISHED)
            ->orderByDesc('published_at')->orderByDesc('id')->first();
    }

    public static function hasAccepted(User $user, InstructorAgreement $agreement): bool
    {
        return InstructorAgreementAcceptance::query()->where('user_id', $user->getKey())->where('agreement_id', $agreement->getKey())->exists();
    }

    /** True when the user must accept the current published version before creating new courses. */
    public static function requiresAcceptance(User $user): bool
    {
        if (! $user->hasRole('instructor_independent')) {
            return false;
        }
        $current = self::current();

        return $current !== null && ! self::hasAccepted($user, $current);
    }
}
