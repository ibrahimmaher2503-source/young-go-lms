<?php

declare(strict_types=1);

namespace App\Domain\Assignments;

use App\Models\Assignment;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/** Who may author an assignment: active staff with the assessment permission who can edit the course it sits in. */
final class AssignmentAccess
{
    public static function courseOf(Assignment $assignment): Course
    {
        $item = $assignment->item()->first();
        abort_unless($item instanceof CurriculumItem, 404);

        return $item->course;
    }

    public static function authorize(User $actor, Assignment $assignment): Course
    {
        $course = self::courseOf($assignment);
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('assessment.manage') || ! Gate::forUser($actor)->allows('update', $course)) {
            throw new AuthorizationException;
        }

        return $course;
    }
}
