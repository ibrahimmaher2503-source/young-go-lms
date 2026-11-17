<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One learner work on one assignment. The status moves draft, submitted, returned (by a reviewer), resubmitted, graded.
 * Text and files live in revisions so earlier handed-in work is never overwritten.
 *
 * @property int $id
 * @property int $assignment_id
 * @property int $learner_id
 * @property string $status
 * @property int $current_revision
 */
final class AssignmentSubmission extends Model
{
    public const string DRAFT = 'draft';

    public const string SUBMITTED = 'submitted';

    public const string RETURNED = 'returned';

    public const string RESUBMITTED = 'resubmitted';

    public const string GRADED = 'graded';

    /** Reviewer decisions (T-P6-04): accepted and rejected are final for the learner; graded carries a score. */
    public const string ACCEPTED = 'accepted';

    public const string REJECTED = 'rejected';

    protected $guarded = ['id'];

    /** @return BelongsTo<Assignment, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class);
    }

    /** @return BelongsTo<User, $this> */
    public function learner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'learner_id');
    }

    /** @return HasMany<SubmissionRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(SubmissionRevision::class, 'submission_id')->orderBy('number');
    }

    /** @return HasMany<SubmissionDecision, $this> */
    public function decisions(): HasMany
    {
        return $this->hasMany(SubmissionDecision::class, 'submission_id')->orderBy('id');
    }

    /** @return HasMany<SubmissionFeedback, $this> */
    public function feedback(): HasMany
    {
        return $this->hasMany(SubmissionFeedback::class, 'submission_id')->orderBy('id');
    }

    public function currentRevision(): ?SubmissionRevision
    {
        return $this->revisions()->where('number', $this->current_revision)->first();
    }
}
