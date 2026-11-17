<?php

declare(strict_types=1);

namespace App\Domain\Cohorts;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $course_id
 * @property string $name
 * @property int|null $capacity
 * @property string $status
 */
final class Cohort extends Model
{
    public const string OPEN = 'open';

    public const string CLOSED = 'closed';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['capacity' => 'integer', 'starts_on' => 'date', 'ends_on' => 'date'];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return HasMany<CohortMember, $this> */
    public function members(): HasMany
    {
        return $this->hasMany(CohortMember::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function instructors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'cohort_instructors', 'cohort_id', 'user_id')->withTimestamps();
    }

    public function seatedCount(): int
    {
        return $this->members()->where('status', CohortMember::SEATED)->count();
    }

    public function isFull(): bool
    {
        return $this->capacity !== null && $this->seatedCount() >= $this->capacity;
    }

    public function seatsLeft(): ?int
    {
        return $this->capacity === null ? null : max(0, $this->capacity - $this->seatedCount());
    }
}
