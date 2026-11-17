<?php

declare(strict_types=1);

namespace App\Domain\Entitlements;

use App\Domain\Entitlements\Contracts\LessonAccessChecker;
use App\Models\Lesson;
use App\Models\User;

/** Placeholder until the EntitlementService exists (T-P4-01): no learner is entitled, so protected files stay closed. */
final class DenyLessonAccess implements LessonAccessChecker
{
    public function canAccessLessonFiles(User $user, Lesson $lesson): bool
    {
        return false;
    }
}
