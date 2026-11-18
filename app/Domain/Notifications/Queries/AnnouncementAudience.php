<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Queries;

use App\Domain\Entitlements\CourseLearners;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Notifications\Announcement;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Who an announcement is for: active accounts with current access to the course (EntitlementService decides), and for a
 * cohort announcement only those holding a seat in that cohort. Nobody outside the course is ever included.
 */
final class AnnouncementAudience
{
    public function __construct(private readonly EntitlementService $access) {}

    /** @return Collection<int, User> */
    public function for(Announcement $announcement): Collection
    {
        $course = $announcement->course;
        $cohort = $announcement->cohort_id === null ? null : $announcement->cohort;

        return CourseLearners::query($course)->get()
            ->filter(fn (User $learner): bool => $learner->getAttribute('status') === 'active')
            ->filter(fn (User $learner): bool => $this->access->canAccessCourse($learner, $course)->allowed)
            ->filter(fn (User $learner): bool => $announcement->cohort_id === null || ($cohort?->hasSeat($learner) ?? false))
            ->values();
    }
}
