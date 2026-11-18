<?php

declare(strict_types=1);

namespace App\Domain\Entitlements\Actions;

use App\Domain\Entitlements\Entitlement;

/**
 * T-P9-11: the only way demo cleanup touches access rows, kept inside the Entitlements module (I01). It deletes rows of
 * demo learners only, and `strangers()` lets the caller refuse a reset when a real learner holds access to a demo course.
 */
final class PurgeDemoEntitlements
{
    /** Table name, so the generic demo purge can skip it by name without naming the model. */
    public const string TABLE = 'entitlements';

    /**
     * @param  list<int>  $learnerIds
     * @param  list<int>  $courseIds
     * @return int rows of other learners on those courses
     */
    public function strangers(array $learnerIds, array $courseIds): int
    {
        if ($courseIds === []) {
            return 0;
        }

        return Entitlement::query()->whereIn('course_id', $courseIds)->whereNotIn('learner_id', $learnerIds)->count();
    }

    /**
     * @param  list<int>  $learnerIds
     * @return int rows removed
     */
    public function __invoke(array $learnerIds): int
    {
        if ($learnerIds === []) {
            return 0;
        }

        return Entitlement::query()->whereIn('learner_id', $learnerIds)->delete();
    }
}
