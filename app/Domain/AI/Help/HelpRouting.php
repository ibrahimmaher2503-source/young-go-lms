<?php

declare(strict_types=1);

namespace App\Domain\AI\Help;

use App\Models\Course;
use App\Models\HelpRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;
use Spatie\Permission\Exceptions\RoleDoesNotExist;

/** Who gets a help request: the responsible instructor of the course, otherwise the admin queue (managers). */
final class HelpRouting
{
    /** The first active instructor with manage scope on the course who may reply to help requests; null sends it to the admin queue. */
    public static function instructorFor(Course $course): ?User
    {
        return $course->instructors()->wherePivot('scope', Course::INSTRUCTOR_SCOPE_MANAGE)->where('users.status', 'active')
            ->orderBy('course_instructors.id')->get()
            ->first(static fn (User $user): bool => $user->can('help_request.reply'));
    }

    /** @return Collection<int, User> active staff who manage every help request (admins, support, super admins) */
    public static function managers(): Collection
    {
        try {
            /** @var Collection<int, User> $holders */
            $holders = User::query()->permission('help_request.manage')->where('status', 'active')->get();
        } catch (PermissionDoesNotExist) {
            // Permissions not seeded yet: only super admins remain.
            $holders = new Collection;
        }
        try {
            $holders = $holders->merge(User::query()->role('super_admin')->where('status', 'active')->get());
        } catch (RoleDoesNotExist) {
            // Roles not seeded yet.
        }
        /** @var Collection<int, User> $managers */
        $managers = $holders
            ->unique(static fn (User $user): int => (int) $user->getKey())->values();

        return $managers;
    }

    /** @return Collection<int, User> the assigned instructor, or the managers when nobody is assigned */
    public static function responders(HelpRequest $request): Collection
    {
        $instructor = $request->instructor;
        if ($instructor !== null && $instructor->getAttribute('status') === 'active') {
            return new Collection([$instructor]);
        }

        return self::managers();
    }
}
