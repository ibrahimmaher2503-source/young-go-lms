<?php

declare(strict_types=1);

namespace App\Domain\Engagement;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One saved course for one learner (YG-GAP-ENG-001). The pair is unique.
 *
 * @property int $id
 * @property int $user_id
 * @property int $course_id
 * @property Carbon $created_at
 */
final class Wishlist extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'wishlists';

    protected $guarded = ['id'];

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
