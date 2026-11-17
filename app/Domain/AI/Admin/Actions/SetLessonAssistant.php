<?php

declare(strict_types=1);

namespace App\Domain\AI\Admin\Actions;

use App\Domain\AI\Admin\AssistantAdminAccess;
use App\Domain\Audit\AuditLogger;
use App\Models\CurriculumItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/** Per-lesson assistant switch (AI spec 14): the curriculum item of a lesson carries the flag eligibility reads. */
final class SetLessonAssistant
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(User $actor, CurriculumItem $item, bool $enabled): void
    {
        $course = $item->course;
        if ($course === null || ! AssistantAdminAccess::canManageCourse($actor, $course)) {
            throw new AuthorizationException;
        }

        if ($item->itemable_type !== 'lesson') {
            throw ValidationException::withMessages(['item' => __('ai_admin.errors.not_a_lesson')]);
        }

        if ($item->ai_enabled === $enabled) {
            return;
        }

        DB::transaction(function () use ($actor, $item, $enabled): void {
            $before = $item->ai_enabled;
            $item->forceFill(['ai_enabled' => $enabled])->save();

            if ($this->audit->record('ai.lesson_switched', $item, (int) $actor->getKey(), ['enabled' => $before], ['enabled' => $enabled], ['course_id' => $item->course_id]) === null) {
                throw new RuntimeException('Unable to record assistant audit.');
            }
        });
    }
}
