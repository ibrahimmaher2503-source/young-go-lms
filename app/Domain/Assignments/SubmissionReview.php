<?php

declare(strict_types=1);

namespace App\Domain\Assignments;

use App\Models\AssignmentSubmission;
use App\Models\SubmissionDecision;

/** The one place that says which decision leads to which status, and when a reviewer may decide. */
final class SubmissionReview
{
    /** Handed-in work waits for a reviewer in these statuses. */
    public const array AWAITING = [AssignmentSubmission::SUBMITTED, AssignmentSubmission::RESUBMITTED];

    /** A reviewer may decide on waiting work, or change an earlier decision. Drafts and work sent back to the learner are not theirs to decide. */
    public const array DECIDABLE = [
        AssignmentSubmission::SUBMITTED, AssignmentSubmission::RESUBMITTED,
        AssignmentSubmission::GRADED, AssignmentSubmission::ACCEPTED, AssignmentSubmission::REJECTED,
    ];

    public static function canDecide(string $status): bool
    {
        return in_array($status, self::DECIDABLE, true);
    }

    public static function statusFor(string $decision): string
    {
        return match ($decision) {
            SubmissionDecision::GRADED => AssignmentSubmission::GRADED,
            SubmissionDecision::ACCEPTED => AssignmentSubmission::ACCEPTED,
            SubmissionDecision::REJECTED => AssignmentSubmission::REJECTED,
            default => AssignmentSubmission::RETURNED,
        };
    }

    /** Score after the late penalty (score times the remaining share), two decimals. */
    public static function finalScore(float $score, ?int $penaltyPercent): float
    {
        return round($score * (100 - min(100, max(0, (int) $penaltyPercent))) / 100, 2);
    }
}
