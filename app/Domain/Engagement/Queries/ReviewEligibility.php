<?php

declare(strict_types=1);

namespace App\Domain\Engagement\Queries;

use App\Domain\Engagement\CourseReview;
use App\Domain\Engagement\ReviewerName;
use App\Domain\Engagement\ReviewStatus;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Identity\ActingLearner;
use App\Models\Course;
use App\Models\User;

/**
 * What the review area of a course page offers this viewer. The same rules SubmitCourseReview enforces, used only to
 * choose what to render: guest -> sign in; entitled -> form (or their own review); everyone else sees nothing to submit.
 */
final class ReviewEligibility
{
    public const string GUEST = 'guest';

    public const string NOT_ENTITLED = 'not_entitled';

    public const string CAN_REVIEW = 'can_review';

    public const string BLOCKED = 'blocked';

    public function __construct(private readonly EntitlementService $access, private readonly ActingLearner $acting) {}

    /** @return array{state: string, review: CourseReview|null} */
    public function for(?User $learner, Course $course): array
    {
        if (! $learner instanceof User) {
            return ['state' => self::GUEST, 'review' => null];
        }
        if ($this->acting->isParentContext() || (! config('engagement.reviews.children_may_review') && ReviewerName::isChild($learner))) {
            return ['state' => self::BLOCKED, 'review' => null];
        }
        if (! $this->access->canAccessCourse($learner, $course)->allowed) {
            return ['state' => self::NOT_ENTITLED, 'review' => null];
        }

        $review = CourseReview::query()->where('course_id', $course->getKey())->where('learner_id', $learner->getKey())->first();

        return ['state' => $review?->status === ReviewStatus::Hidden ? self::BLOCKED : self::CAN_REVIEW, 'review' => $review];
    }
}
