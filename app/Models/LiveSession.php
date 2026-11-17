<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Cohorts\Cohort;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Carbon;
use Spatie\Translatable\HasTranslations;

/**
 * A live session placed in the curriculum. Times are UTC; the timezone is only how the scheduler meant to see them.
 * The meeting link is encrypted at rest and is never part of a public page (the protected join is T-P5-03).
 *
 * @property int $id
 * @property int $course_id
 * @property int|null $cohort_id
 * @property int|null $instructor_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string $timezone
 * @property string $provider
 * @property string|null $meeting_url
 * @property int $join_window_minutes
 * @property string $status
 * @property Carbon|null $archived_at
 */
final class LiveSession extends Model
{
    use HasTranslations;

    public const string SCHEDULED = 'scheduled';

    public const string OPEN = 'open';

    public const string COMPLETED = 'completed';

    public const string CANCELLED = 'cancelled';

    public const array PROVIDERS = ['manual_zoom', 'meet', 'bbb', 'custom'];

    /** @var list<string> */
    public array $translatable = ['title', 'description'];

    protected $guarded = ['id'];

    protected $hidden = ['meeting_url'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime', 'ends_at' => 'datetime', 'archived_at' => 'datetime',
            'meeting_url' => 'encrypted', 'join_window_minutes' => 'integer', 'cohort_id' => 'integer', 'instructor_id' => 'integer',
        ];
    }

    /** @return MorphOne<CurriculumItem, $this> */
    public function item(): MorphOne
    {
        return $this->morphOne(CurriculumItem::class, 'itemable');
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<Cohort, $this> */
    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    /** @return BelongsTo<User, $this> */
    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }
}
