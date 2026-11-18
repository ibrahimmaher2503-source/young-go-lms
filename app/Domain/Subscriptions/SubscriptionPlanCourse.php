<?php

declare(strict_types=1);

namespace App\Domain\Subscriptions;

use App\Models\Course;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One course a plan includes. A plan chosen "by category" is expanded into these rows when it is saved.
 *
 * @property int $id
 * @property int $plan_id
 * @property int $course_id
 * @property int $position
 */
final class SubscriptionPlanCourse extends Model
{
    public $timestamps = false;

    protected $table = 'subscription_plan_courses';

    protected $guarded = ['id'];

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
