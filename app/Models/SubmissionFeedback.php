<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One reviewer comment in a submission feedback thread, optionally tied to the revision it is about and to the decision it came with.
 *
 * @property int $id
 * @property int $submission_id
 * @property int|null $revision_number
 * @property int|null $decision_id
 * @property int|null $author_id
 * @property string $body
 * @property Carbon $created_at
 */
final class SubmissionFeedback extends Model
{
    protected $table = 'submission_feedback';

    protected $guarded = ['id'];

    /** @return BelongsTo<AssignmentSubmission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(AssignmentSubmission::class, 'submission_id');
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsTo<SubmissionDecision, $this> */
    public function decision(): BelongsTo
    {
        return $this->belongsTo(SubmissionDecision::class, 'decision_id');
    }
}
