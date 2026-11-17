<?php

declare(strict_types=1);

namespace App\Domain\AI\Admin;

use App\Domain\AI\Console\TestConsoleAccess;
use App\Models\Course;
use App\Models\User;

/**
 * Who may operate the assistant (AI spec 14). Provider, model, prompt, quotas, budgets, retention and the global switch need
 * ai.configure (super admin by default). Usage and cost reports need ai.view_usage. Per-course and per-lesson switches and the
 * lesson insights follow the test-console rule: lesson-document staff who may update the course (admins, and the course's own
 * manage-scope instructors). Nothing here reads a learner conversation.
 */
final class AssistantAdminAccess
{
    public static function canConfigure(User $user): bool
    {
        return $user->getAttribute('status') === 'active' && $user->can('ai.configure');
    }

    public static function canViewUsage(User $user): bool
    {
        return $user->getAttribute('status') === 'active' && $user->can('ai.view_usage');
    }

    public static function canManageCourse(User $user, Course $course): bool
    {
        return TestConsoleAccess::allows($user, $course);
    }

    /** Any assistant page that is about courses and lessons (switches, insights). */
    public static function canOpenCoursePages(User $user): bool
    {
        return $user->getAttribute('status') === 'active' && $user->can('lesson_document.upload');
    }
}
