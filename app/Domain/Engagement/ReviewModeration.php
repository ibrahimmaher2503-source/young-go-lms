<?php

declare(strict_types=1);

namespace App\Domain\Engagement;

use App\Domain\Audit\AuditLogger;
use App\Domain\Engagement\Notifications\ReviewDecisionNotice;
use App\Domain\Notifications\NotificationDispatcher;
use App\Domain\Shared\Actions\RecordReviewDecision;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * The one status transition for a review, shared by ApproveCourseReview, RejectCourseReview and HideCourseReview:
 * permission recheck, allowed-from states, reason rule, the row lock, the audit entry, the rating cache drop and one notice.
 */
final class ReviewModeration
{
    public const string PERMISSION = 'review.moderate';

    public function __construct(private readonly AuditLogger $audit, private readonly NotificationDispatcher $notices) {}

    /**
     * @param  list<ReviewStatus>  $from
     */
    public function transition(User $actor, CourseReview $review, ReviewStatus $to, array $from, ?string $reason, bool $reasonRequired): CourseReview
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can(self::PERMISSION)) {
            throw new AuthorizationException;
        }

        $reason = $reason === null ? null : trim($reason);
        $reason = $reason === '' ? null : $reason;
        $length = $reason === null ? 0 : mb_strlen($reason);
        if (($reasonRequired && $length < RecordReviewDecision::REASON_MIN) || $length > RecordReviewDecision::REASON_MAX) {
            throw ValidationException::withMessages(['reason' => __('engagement.reason_invalid', ['min' => RecordReviewDecision::REASON_MIN, 'max' => RecordReviewDecision::REASON_MAX])]);
        }

        $changed = false;
        $locked = DB::transaction(function () use ($actor, $review, $to, $from, $reason, &$changed): CourseReview {
            $locked = CourseReview::query()->lockForUpdate()->findOrFail($review->getKey());
            if ($locked->status === $to) {
                return $locked;
            }
            if (! in_array($locked->status, $from, true)) {
                throw ValidationException::withMessages(['review' => __('engagement.transition_refused')]);
            }

            $before = $locked->status;
            $locked->forceFill(['status' => $to->value, 'moderated_by' => $actor->getKey(), 'moderated_at' => now(), 'moderation_reason' => $reason])->save();
            if ($this->audit->record('review.'.$to->value, $locked, (int) $actor->getKey(), ['status' => $before->value], ['status' => $to->value], ['reason' => $reason, 'course_id' => $locked->course_id]) === null) {
                throw new RuntimeException('Unable to record review moderation audit.');
            }
            $changed = true;

            return $locked;
        });

        if ($changed) {
            ReviewSummary::forget($locked->course_id);
            $learner = User::query()->find($locked->learner_id);
            if ($learner instanceof User && $to !== ReviewStatus::Pending) {
                $this->notices->send($learner, new ReviewDecisionNotice($locked));
            }
        }

        return $locked;
    }
}
