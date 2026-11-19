<?php

declare(strict_types=1);

namespace App\Domain\Cohorts;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $cohort_id
 * @property int $learner_id
 * @property int|null $seat_order_item_id
 * @property string $status
 */
final class CohortMember extends Model
{
    public const string SEATED = 'seated';

    public const string WAITLISTED = 'waitlisted';

    public const string REMOVED = 'removed';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['queued_at' => 'datetime', 'seated_at' => 'datetime', 'removed_at' => 'datetime'];
    }

    /** @return BelongsTo<Cohort, $this> */
    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    /** @return BelongsTo<User, $this> */
    public function learner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'learner_id');
    }
}
