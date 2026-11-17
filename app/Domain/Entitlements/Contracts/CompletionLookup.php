<?php

declare(strict_types=1);

namespace App\Domain\Entitlements\Contracts;

use App\Models\Course;
use App\Models\User;

/** What a learner has finished in a course. The Progress module answers; the access decision only asks. */
interface CompletionLookup
{
    /** @return list<int> curriculum item ids the learner completed in this course */
    public function completedItemIds(User $learner, Course $course): array;
}
