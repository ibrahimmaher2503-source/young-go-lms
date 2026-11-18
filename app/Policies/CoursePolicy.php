<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Instructors\Queries\InstructorAgreements;
use App\Models\Course;
use App\Models\User;
use Illuminate\Auth\Access\Response;

final class CoursePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->allows($user, 'course.view')
            && ($user->can('panel.admin.access') || $user->can('panel.instructor.access'));
    }

    public function create(User $user): bool
    {
        // T-P9-01 A2: an independent instructor must accept the newest published agreement before creating a course.
        return $this->allows($user, 'course.create') && $this->viewAny($user) && ! InstructorAgreements::requiresAcceptance($user);
    }

    public function view(User $user, Course $course): Response
    {
        return $this->canAccess($user, $course)
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, Course $course): bool
    {
        return $this->allows($user, 'course.update') && $this->canAccess($user, $course, manage: true);
    }

    private function canAccess(User $user, Course $course, bool $manage = false): bool
    {
        if (! $this->viewAny($user) || ! $course->exists) {
            return false;
        }

        if ($user->can('panel.admin.access')) {
            return true;
        }

        if ($course->relationLoaded('instructors')) {
            return $course->instructors->contains(static fn (User $instructor): bool => $instructor->is($user)
                && (! $manage || $instructor->getRelation('pivot')->getAttribute('scope') === Course::INSTRUCTOR_SCOPE_MANAGE));
        }

        $instructors = $course->instructors()->whereKey($user->getKey());

        return $manage ? $instructors->wherePivot('scope', Course::INSTRUCTOR_SCOPE_MANAGE)->exists() : $instructors->exists();
    }

    public function publish(User $user, Course $course): bool
    {
        return $this->update($user, $course)
            && ! $user->hasRole('instructor_independent')
            && $user->can('panel.admin.access')
            && $this->allows($user, 'course.publish')
            && ($course->owner_type !== Course::OWNER_INDEPENDENT || $this->allows($user, 'course.approve'));
    }

    public function duplicate(User $user, Course $course): bool
    {
        return $user->can('panel.admin.access') && $this->create($user) && $this->view($user, $course)->allowed();
    }

    public function archive(User $user, Course $course): bool
    {
        return $this->update($user, $course) && $user->can('panel.admin.access');
    }

    public function delete(User $user, Course $course): bool
    {
        return $this->view($user, $course)->allowed() && $user->can('panel.admin.access') && $this->allows($user, 'course.delete');
    }

    public function feature(User $user, Course $course): bool
    {
        return $this->update($user, $course) && $user->can('panel.admin.access') && $this->allows($user, 'course.publish');
    }
}
