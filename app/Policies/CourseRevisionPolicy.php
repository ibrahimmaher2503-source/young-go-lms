<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\CourseRevision;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/** T-P3-07: a revision is managed like its course; reviewing needs the course's `publish` grant (the author never reviews their own). */
final class CourseRevisionPolicy extends BasePolicy
{
    public function view(User $user, CourseRevision $revision): bool
    {
        return Gate::forUser($user)->allows('view', $revision->course);
    }

    public function update(User $user, CourseRevision $revision): bool
    {
        return Gate::forUser($user)->allows('update', $revision->course);
    }

    public function publish(User $user, CourseRevision $revision): bool
    {
        return (int) $revision->author_id !== (int) $user->getKey() && Gate::forUser($user)->allows('publish', $revision->course);
    }
}
