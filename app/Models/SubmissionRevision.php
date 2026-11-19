<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $submission_id
 * @property int $number
 * @property string|null $text_body
 * @property Carbon|null $submitted_at
 * @property bool $is_late
 * @property int|null $late_penalty_percent
 */
final class SubmissionRevision extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime', 'is_late' => 'boolean', 'late_penalty_percent' => 'integer', 'number' => 'integer'];
    }

    /** @return BelongsTo<AssignmentSubmission, $this> */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(AssignmentSubmission::class, 'submission_id');
    }

    /** @return HasMany<SubmissionFile, $this> */
    public function files(): HasMany
    {
        return $this->hasMany(SubmissionFile::class, 'revision_id')->where('path', '!=', '')->orderBy('id');
    }

    public function isDraft(): bool
    {
        return $this->submitted_at === null;
    }
}
