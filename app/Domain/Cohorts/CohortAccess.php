<?php

declare(strict_types=1);

namespace App\Domain\Cohorts;

use App\Models\Course;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;

/** Who may run cohorts: staff with the live-session permission who can edit the course (admins, or its managing instructors). */
final class CohortAccess
{
    public static function authorize(User $actor, Course $course): void
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('live_session.manage') || ! Gate::forUser($actor)->allows('update', $course)) {
            throw new AuthorizationException;
        }
    }
}
