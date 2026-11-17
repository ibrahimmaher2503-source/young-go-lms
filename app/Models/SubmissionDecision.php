<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One reviewer decision on a submission revision. Kept forever: a later decision adds a row instead of replacing this one.
 *
 * @property int $id
 * @property int $submission_id
 * @property int $revision_number
 * @property string $decision
 * @property int|null $reviewer_id
 * @property float|string|null $score
 * @property int|null $late_penalty_percent
 * @property float|string|null $final_score
 * @property Carbon $created_at
 */
final class SubmissionDecision extends Model
{
    public const string GRADED = 'graded';

    public const string ACCEPTED = 'accepted';

    public const string REJECTED = 'rejected';

    public const string REVISION_REQUESTED = 'revision_requested';

    public const array DECISIONS = [self::GRADED, self::ACCEPTED, self::REJECTED, self::REVISION_REQUESTED];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['revision_number' => 'integer', 'late_penalty_percent' => 'integer'];
    }

    /** @return BelongsTo<AssignmentSubmission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(AssignmentSubmission::class, 'submission_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
