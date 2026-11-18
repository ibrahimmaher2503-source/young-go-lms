<?php

declare(strict_types=1);

namespace App\Domain\Catalog\Actions;

use App\Domain\Shared\Actions\RecordReviewDecision;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Programmatic entry to the course review decision (the queue buttons use ApproveRejectAction with the same pieces). */
final class ReviewCourse
{
    public const string ABILITY = 'publish';

    public const string EVENT_PREFIX = 'course.review';

    public function __construct(private readonly RecordReviewDecision $decisions, private readonly ApproveCourse $approve, private readonly RejectCourse $reject) {}

    public function __invoke(User $actor, Course $course, string $decision, ?string $reason = null): void
    {
        $this->decisions->handle($actor, $course, self::ABILITY, self::EVENT_PREFIX, $decision, $reason, $decision === RecordReviewDecision::APPROVE
            ? function (Model $subject, User $by, ?string $note) use ($course): void {
                ($this->approve)($subject instanceof Course ? $subject : $course, $by, $note);
            }
            : function (Model $subject, User $by, ?string $why) use ($course): void {
                ($this->reject)($subject instanceof Course ? $subject : $course, $by, $why);
            });
    }
}
