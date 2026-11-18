<?php

declare(strict_types=1);

namespace App\Domain\Cohorts;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $course_id
 * @property string $name
 * @property array<string, string>|null $name_translations
 * @property Carbon|null $enrollment_opens_on
 * @property Carbon|null $enrollment_closes_on
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
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
        return [
            'capacity' => 'integer', 'starts_on' => 'date', 'ends_on' => 'date', 'name_translations' => 'array',
            'enrollment_opens_on' => 'date', 'enrollment_closes_on' => 'date',
        ];
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

    public function localizedName(?string $locale = null): string
    {
        return $this->name_translations[$locale ?? app()->getLocale()] ?? $this->name;
    }

    public function enrollmentIsOpen(): bool
    {
        $today = now('Africa/Cairo')->toDateString();

        return $this->status === self::OPEN
            && ($this->enrollment_opens_on === null || $today >= $this->enrollment_opens_on->toDateString())
            && ($this->enrollment_closes_on === null || $today <= $this->enrollment_closes_on->toDateString());
    }

    public function seatedCount(): int
    {
        return $this->members()->where('status', CohortMember::SEATED)->count();
    }

    /** Whether this learner currently holds a seat (a waitlist place or a removal does not count). */
    public function hasSeat(User $learner): bool
    {
        return $this->members()->where('learner_id', $learner->getKey())->where('status', CohortMember::SEATED)->exists();
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
