<?php

declare(strict_types=1);

namespace App\Domain\Commerce;

use App\Models\Course;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One member course of a bundle. `allocation_weight` is the admin-set share used to split the bundle price (null on every
 * member means the split follows each course list price in the order market).
 *
 * @property int $id
 * @property int $bundle_id
 * @property int $course_id
 * @property int|null $allocation_weight
 * @property int $position
 */
final class BundleItem extends Model
{
    public $timestamps = false;

    protected $table = 'bundle_items';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['allocation_weight' => 'integer', 'position' => 'integer'];
    }

    /** @return BelongsTo<Bundle, $this> */
    public function bundle(): BelongsTo
    {
        return $this->belongsTo(Bundle::class);
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
