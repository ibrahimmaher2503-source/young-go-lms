<?php

declare(strict_types=1);

namespace App\Domain\Assessment;

use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\Quiz;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/** Who may author a quiz: active staff with the assessment permission who can edit the course the quiz sits in. */
final class QuizAccess
{
    public static function courseOf(Quiz $quiz): Course
    {
        $item = $quiz->item()->first();
        abort_unless($item instanceof CurriculumItem, 404);

        return $item->course;
    }

    public static function authorize(User $actor, Quiz $quiz): Course
    {
        $course = self::courseOf($quiz);
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('assessment.manage') || ! Gate::forUser($actor)->allows('update', $course)) {
            throw new AuthorizationException;
        }

        return $course;
    }
}
