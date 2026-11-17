<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * The only ordering source for everything a learner works through (ED-002).
 *
 * @property int $course_id
 * @property int $section_id
 * @property int $sort
 * @property string $status
 * @property bool $required
 * @property bool $ai_enabled
 */
final class CurriculumItem extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_PUBLISHED = 'published';

    protected $fillable = [
        'course_id', 'section_id', 'sort', 'itemable_type', 'itemable_id', 'status', 'visibility',
        'unlock_rule', 'unlock_at', 'unlock_days', 'required', 'ai_enabled',
    ];

    protected $attributes = ['status' => self::STATUS_DRAFT, 'visibility' => 'enrolled', 'unlock_rule' => 'none', 'required' => true, 'ai_enabled' => true];

    protected function casts(): array
    {
        return ['required' => 'boolean', 'ai_enabled' => 'boolean', 'sort' => 'integer', 'unlock_at' => 'datetime'];
    }

    /** @return MorphTo<Model, $this> */
    public function itemable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<CourseSection, $this> */
    public function section(): BelongsTo
    {
        return $this->belongsTo(CourseSection::class, 'section_id');
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
