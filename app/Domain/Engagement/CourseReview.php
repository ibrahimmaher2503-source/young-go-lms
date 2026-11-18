<?php

declare(strict_types=1);

namespace App\Domain\Engagement;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A learner's rating and text for a course (YG-GAP-ENG-002). Only `published` reviews are public. `author_name` is the
 * only identity ever shown: a snapshot written at submit time (see ReviewerName), never the account's full name.
 *
 * @property int $id
 * @property int $course_id
 * @property int $learner_id
 * @property int $rating
 * @property string $body
 * @property string $author_name
 * @property ReviewStatus $status
 * @property int|null $moderated_by
 * @property Carbon|null $moderated_at
 * @property string|null $moderation_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
final class CourseReview extends Model
{
    protected $table = 'course_reviews';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['status' => ReviewStatus::class, 'rating' => 'integer', 'moderated_at' => 'datetime'];
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

    /** @return BelongsTo<User, $this> */
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderated_by');
    }
}
