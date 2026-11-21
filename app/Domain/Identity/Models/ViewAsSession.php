<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One support "view as learner" session (ED-027). The staff member stays signed in as themselves; the session only says
 * which learner they may read, until when, and why. It never holds credentials.
 *
 * @property int $id
 * @property int $staff_id
 * @property int $learner_id
 * @property string $reason
 * @property Carbon $started_at
 * @property Carbon $expires_at
 * @property Carbon|null $ended_at
 * @property string|null $end_cause
 * @property int $page_views
 */
final class ViewAsSession extends Model
{
    public const string END_MANUAL = 'manual';

    public const string END_EXPIRED = 'expired';

    public const string END_REPLACED = 'replaced';

    public const string END_REVOKED = 'revoked';

    protected $table = 'view_as_sessions';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'expires_at' => 'datetime', 'ended_at' => 'datetime', 'page_views' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /** @return BelongsTo<User, $this> */
    public function learner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'learner_id');
    }

    /**
     * @param  Builder<ViewAsSession>  $query
     * @return Builder<ViewAsSession>
     */
    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
