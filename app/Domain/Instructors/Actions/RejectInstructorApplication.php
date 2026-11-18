<?php

declare(strict_types=1);

namespace App\Domain\Instructors\Actions;

use App\Domain\Instructors\ApplicationStatus;
use App\Domain\Instructors\Events\InstructorApplicationReviewed;
use App\Domain\Instructors\Models\InstructorApplication;
use App\Domain\Shared\Actions\RecordReviewDecision;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Admin rejects a pending application with a written reason (needs `instructor.manage`). The pending slot is released so the
 * applicant may apply again. Rejecting a rejected application is a no-op; an approved one cannot be rejected. The generic review
 * audit entry (with the reason) is written by RecordReviewDecision.
 */
final class RejectInstructorApplication
{
    public function __invoke(User $actor, InstructorApplication $application, string $reason): InstructorApplication
    {
        if ($actor->getAttribute('status') !== 'active' || ! $actor->can('instructor.manage')) {
            throw new AuthorizationException;
        }
        $reason = trim($reason);
        if (mb_strlen($reason) < RecordReviewDecision::REASON_MIN || mb_strlen($reason) > RecordReviewDecision::REASON_MAX) {
            throw ValidationException::withMessages(['reason' => __('instructor_onboarding.errors.reason_invalid', ['min' => RecordReviewDecision::REASON_MIN, 'max' => RecordReviewDecision::REASON_MAX])]);
        }

        $reviewed = DB::transaction(function () use ($actor, $application, $reason): ?InstructorApplication {
            $locked = InstructorApplication::query()->lockForUpdate()->findOrFail($application->getKey());
            if ($locked->status === ApplicationStatus::Rejected) {
                return null;
            }
            if ($locked->status !== ApplicationStatus::Pending) {
                throw ValidationException::withMessages(['application' => __('instructor_onboarding.errors.not_pending')]);
            }

            $locked->forceFill([
                'status' => ApplicationStatus::Rejected->value, 'pending_user_id' => null,
                'reviewed_by' => $actor->getKey(), 'reviewed_at' => now(), 'review_note' => $reason,
            ])->save();

            return $locked;
        });

        if ($reviewed === null) {
            return InstructorApplication::query()->findOrFail($application->getKey());
        }

        DB::afterCommit(static fn () => event(new InstructorApplicationReviewed($reviewed)));

        return $reviewed;
    }
}
