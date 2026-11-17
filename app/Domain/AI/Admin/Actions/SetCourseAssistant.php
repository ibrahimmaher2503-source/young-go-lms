<?php

declare(strict_types=1);

namespace App\Domain\AI\Admin\Actions;

use App\Domain\AI\Admin\AssistantAdminAccess;
use App\Domain\Audit\AuditLogger;
use App\Models\Course;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Per-course assistant switch (AI spec 14). The course's own flag only; lessons keep their own switches. */
final class SetCourseAssistant
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, Course $course, bool $enabled): void
    {
        if (! AssistantAdminAccess::canManageCourse($actor, $course)) {
            throw new AuthorizationException;
        }

        if ($course->ai_enabled === $enabled) {
            return;
        }

        DB::transaction(function () use ($actor, $course, $enabled): void {
            $before = $course->ai_enabled;
            $course->forceFill(['ai_enabled' => $enabled])->save();

            if ($this->audit->record('ai.course_switched', $course, (int) $actor->getKey(), ['enabled' => $before], ['enabled' => $enabled]) === null) {
                throw new RuntimeException('Unable to record assistant audit.');
            }
        });
    }
}
