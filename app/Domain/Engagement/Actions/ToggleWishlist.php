<?php

declare(strict_types=1);

namespace App\Domain\Engagement\Actions;

use App\Domain\Catalog\CourseStatus;
use App\Domain\Engagement\Wishlist;
use App\Models\Course;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * Saves or removes a course for a learner (YG-GAP-ENG-001). Only a published course can be saved; removing always works,
 * so a course that was later unpublished can still be taken off the list. The unique pair makes a double click harmless.
 */
final class ToggleWishlist
{
    /** @return bool true when the course is saved after the call */
    public function __invoke(User $learner, Course $course): bool
    {
        if ($learner->getAttribute('status') !== 'active') {
            throw new AuthorizationException;
        }

        $existing = Wishlist::query()->where('user_id', $learner->getKey())->where('course_id', $course->getKey());
        if ($existing->exists()) {
            $existing->delete();

            return false;
        }

        if ($course->status !== CourseStatus::Published) {
            throw ValidationException::withMessages(['course' => __('engagement.wishlist_unavailable')]);
        }

        try {
            Wishlist::query()->create(['user_id' => $learner->getKey(), 'course_id' => $course->getKey()]);
        } catch (UniqueConstraintViolationException) {
            // A parallel request saved it first; the result is the same.
        }

        return true;
    }
}
