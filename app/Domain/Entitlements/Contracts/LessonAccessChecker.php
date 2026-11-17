<?php

declare(strict_types=1);

namespace App\Domain\Entitlements\Contracts;

use App\Models\Lesson;
use App\Models\User;

/**
 * Decides whether a learner may use a lesson's protected files. T-P4-01 replaces the default binding with the real
 * EntitlementService; until then everything not explicitly public fails closed.
 */
interface LessonAccessChecker
{
    public function canAccessLessonFiles(User $user, Lesson $lesson): bool;
}
