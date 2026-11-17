<?php

declare(strict_types=1);

namespace App\Domain\Entitlements\Actions;

use App\Domain\Catalog\CourseStatus;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Models\Course;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * A learner, or a verified parent for their child, enrolls in a free published course. The entitlement always belongs to
 * the learner (the child), never the parent. Enrolling twice returns the same entitlement.
 */
final class EnrollInFreeCourse
{
    public function __construct(private readonly GrantEntitlement $grant) {}

    public function __invoke(User $actor, User $learner, Course $course): Entitlement
    {
        if ($actor->getAttribute('status') !== 'active' || $learner->getAttribute('status') !== 'active') {
            throw new AuthorizationException;
        }

        if (! $actor->is($learner)) {
            // A parent may enroll only a child linked and verified to them; anyone else gets the same refusal.
            Gate::forUser($actor)->authorize('guardian.child.access', $learner);
        }

        if ($course->access_mode !== Course::ACCESS_FREE) {
            throw ValidationException::withMessages(['course' => __('entitlements.not_free')]);
        }
        if ($course->status !== CourseStatus::Published) {
            throw ValidationException::withMessages(['course' => __('entitlements.not_open')]);
        }

        return ($this->grant)($learner, $course, EntitlementSource::Free, '', null, null, $actor);
    }
}
