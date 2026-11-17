<?php

declare(strict_types=1);

namespace App\Domain\Progress;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $learner_id
 * @property int $course_id
 * @property int $percent
 * @property int $completed_required
 * @property int $total_required
 * @property int|null $last_item_id
 * @property int $last_position_seconds
 * @property Carbon|null $last_activity_at
 * @property Carbon|null $completed_at
 */
final class CourseProgress extends Model
{
    protected $table = 'course_progress';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['last_activity_at' => 'datetime', 'completed_at' => 'datetime'];
    }
}
