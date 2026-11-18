<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Catalog\Revisions\CourseRevisionStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A proposed change to a published course (T-P3-07). The live course is never touched until the revision is approved.
 *
 * @property int $course_id
 * @property int|null $author_id
 * @property int $number
 * @property CourseRevisionStatus $status
 * @property array<string, mixed> $base
 * @property array<string, mixed> $payload
 * @property int|null $open_course_id
 * @property CarbonInterface|null $submitted_at
 * @property CarbonInterface|null $reviewed_at
 * @property int|null $reviewer_id
 * @property string|null $reason
 * @property CarbonInterface|null $applied_at
 */
final class CourseRevision extends Model
{
    protected $fillable = ['course_id', 'author_id', 'number', 'status', 'base', 'payload', 'open_course_id', 'submitted_at', 'reviewed_at', 'reviewer_id', 'reason', 'applied_at'];

    protected function casts(): array
    {
        return [
            'status' => CourseRevisionStatus::class, 'base' => 'array', 'payload' => 'array', 'number' => 'integer',
            'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'applied_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }
}
