<?php

declare(strict_types=1);

namespace App\Policies;

use App\Domain\Catalog\Queries\CourseDirectory;
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
        return $this->allows($user, 'course.create') && $user->can('panel.admin.access');
    }

    public function view(User $user, Course $course): Response
    {
        return CourseDirectory::for($user)->whereKey($course->getKey())->exists()
            ? Response::allow()
            : Response::denyAsNotFound();
    }

    public function update(User $user, Course $course): bool
    {
        return $this->view($user, $course)->allowed() && $this->allows($user, 'course.update')
            && ($user->can('panel.admin.access')
                || $course->instructors()->whereKey($user->getKey())->wherePivot('scope', Course::INSTRUCTOR_SCOPE_MANAGE)->exists());
    }

    public function publish(User $user, Course $course): bool
    {
        return $this->update($user, $course)
            && ! $user->hasRole('instructor_independent')
            && $user->can('panel.admin.access')
            && $this->allows($user, 'course.publish')
            && ($course->owner_type !== Course::OWNER_INDEPENDENT || $this->allows($user, 'course.approve'));
    }

    public function feature(User $user, Course $course): bool
    {
        return $this->update($user, $course) && $user->can('panel.admin.access') && $this->allows($user, 'course.publish');
    }
}
