<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Revisions\Actions;

use App\Domain\Shared\Actions\RecordReviewDecision;
use App\Models\CourseRevision;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Programmatic entry to a revision review decision (the queue buttons use ApproveRejectAction with the same pieces). */
final class ReviewCourseRevision
{
    public const string ABILITY = 'publish';

    public const string EVENT_PREFIX = 'course.revision.review';

    public function __construct(private readonly RecordReviewDecision $decisions, private readonly ApproveCourseRevision $approve, private readonly RejectCourseRevision $reject) {}

    public function __invoke(User $actor, CourseRevision $revision, string $decision, ?string $reason = null): void
    {
        $this->decisions->handle($actor, $revision, self::ABILITY, self::EVENT_PREFIX, $decision, $reason, $decision === RecordReviewDecision::APPROVE
            ? function (Model $subject, User $by, ?string $note) use ($revision): void {
                ($this->approve)($subject instanceof CourseRevision ? $subject : $revision, $by, $note);
            }
            : function (Model $subject, User $by, ?string $why) use ($revision): void {
                ($this->reject)($subject instanceof CourseRevision ? $subject : $revision, $by, $why);
            });
    }
}
