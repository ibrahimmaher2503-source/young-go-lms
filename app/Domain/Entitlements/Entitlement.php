<?php

declare(strict_types=1);

namespace App\Domain\Entitlements;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $learner_id
 * @property int $course_id
 * @property EntitlementSource $source_type
 * @property string $source_ref
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property string $status
 */
final class Entitlement extends Model
{
    public const string ACTIVE = 'active';

    public const string EXPIRED = 'expired';

    public const string REVOKED = 'revoked';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['source_type' => EntitlementSource::class, 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    /** Active means: not revoked or expired, already started, and not past its end. */
    public function isCurrent(): bool
    {
        return $this->status === self::ACTIVE && $this->starts_at->lte(now()) && ($this->ends_at === null || $this->ends_at->gt(now()));
    }

    /** @return BelongsTo<User, $this> */
    public function learner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'learner_id');
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
