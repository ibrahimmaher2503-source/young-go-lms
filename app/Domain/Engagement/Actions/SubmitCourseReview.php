<?php

declare(strict_types=1);

namespace App\Domain\Engagement\Actions;

use App\Domain\Audit\AuditLogger;
use App\Domain\Engagement\CourseReview;
use App\Domain\Engagement\ReviewerName;
use App\Domain\Engagement\ReviewStatus;
use App\Domain\Engagement\ReviewSummary;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Identity\ActingLearner;
use App\Models\Course;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * An entitled learner rates a course and writes a short text (YG-GAP-ENG-002). One review per learner and course: sending
 * again edits it and puts it back in the moderation queue. Nothing is public until a moderator approves it. A parent
 * acting inside a child context cannot review as the child. A review a moderator hid cannot be edited back into the queue.
 */
final class SubmitCourseReview
{
    public function __construct(
        private readonly EntitlementService $access,
        private readonly ActingLearner $acting,
        private readonly AuditLogger $audit,
    ) {}

    public function __invoke(User $learner, Course $course, int $rating, string $body): CourseReview
    {
        if ($learner->getAttribute('status') !== 'active' || $this->acting->isParentContext()) {
            throw new AuthorizationException;
        }
        if (! config('engagement.reviews.children_may_review') && ReviewerName::isChild($learner)) {
            throw new AuthorizationException;
        }
        if (! $this->access->canAccessCourse($learner, $course)->allowed) {
            throw new AuthorizationException(__('engagement.not_entitled'));
        }

        $body = trim($body);
        $min = (int) config('engagement.reviews.body_min');
        $max = (int) config('engagement.reviews.body_max');
        if ($rating < 1 || $rating > 5) {
            throw ValidationException::withMessages(['rating' => __('engagement.rating_invalid')]);
        }
        if (mb_strlen($body) < $min || mb_strlen($body) > $max) {
            throw ValidationException::withMessages(['body' => __('engagement.body_invalid', ['min' => $min, 'max' => $max])]);
        }

        $review = $this->save($learner, $course, $rating, $body);
        ReviewSummary::forget((int) $course->getKey());

        return $review;
    }

    private function save(User $learner, Course $course, int $rating, string $body): CourseReview
    {
        $attempt = fn (): CourseReview => DB::transaction(function () use ($learner, $course, $rating, $body): CourseReview {
            $review = CourseReview::query()->where('course_id', $course->getKey())->where('learner_id', $learner->getKey())->lockForUpdate()->first();
            if ($review?->status === ReviewStatus::Hidden) {
                throw ValidationException::withMessages(['review' => __('engagement.review_hidden')]);
            }

            $before = $review?->status->value;
            $review ??= new CourseReview(['course_id' => $course->getKey(), 'learner_id' => $learner->getKey()]);
            $review->forceFill([
                'rating' => $rating, 'body' => $body, 'author_name' => ReviewerName::for($learner), 'status' => ReviewStatus::Pending->value,
                'moderated_by' => null, 'moderated_at' => null, 'moderation_reason' => null,
            ])->save();

            if ($this->audit->record('review.submitted', $review, (int) $learner->getKey(), $before === null ? null : ['status' => $before], ['status' => ReviewStatus::Pending->value], ['course_id' => $course->getKey(), 'rating' => $rating]) === null) {
                throw new RuntimeException('Unable to record review audit.');
            }

            return $review;
        });

        try {
            return $attempt();
        } catch (UniqueConstraintViolationException) {
            return $attempt(); // two submits raced on the unique pair; the second now finds the row and edits it
        }
    }
}
