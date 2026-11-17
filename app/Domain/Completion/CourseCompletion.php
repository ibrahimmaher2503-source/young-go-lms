<?php

declare(strict_types=1);

namespace App\Domain\Completion;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A learner finished a course. One row per learner and course (unique), never removed by a later rule change.
 *
 * @property int $id
 * @property int $course_id
 * @property int $learner_id
 * @property Carbon $completed_at
 * @property string $source
 * @property int|null $approved_by
 * @property string|null $reason
 * @property array<string, mixed>|null $snapshot
 */
final class CourseCompletion extends Model
{
    public const string AUTO = 'auto';

    public const string MANUAL = 'manual';

    protected $table = 'course_completions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime', 'snapshot' => 'array'];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<User, $this> */
    public function learner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'learner_id');
    }
}
