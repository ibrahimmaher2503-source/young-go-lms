<?php

declare(strict_types=1);

namespace App\Domain\Assignments;

use RuntimeException;

/** Why a reviewer decision was refused. Messages are for staff, in review.refused. */
final class ReviewRefusedException extends RuntimeException
{
    public const string NOT_DECIDABLE = 'not_decidable';

    public const string SCORE_NEEDED = 'score_needed';

    public const string SCORE_RANGE = 'score_range';

    public const string FEEDBACK_NEEDED = 'feedback_needed';

    public const string NO_REVISIONS_LEFT = 'no_revisions_left';

    public const string NOT_ESSAY = 'not_essay';

    public const string NOT_REVIEWABLE = 'not_reviewable';

    public function __construct(public readonly string $reason, string $message = '')
    {
        parent::__construct($message !== '' ? $message : __('review.refused.'.$reason));
    }
}
