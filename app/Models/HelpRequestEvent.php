<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Who did what to a help request and when (actor, state change, timestamp).
 *
 * @property int $help_request_id
 * @property int|null $actor_id
 * @property string $event
 * @property string|null $from_status
 * @property string|null $to_status
 * @property Carbon|null $created_at
 */
final class HelpRequestEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['help_request_id', 'actor_id', 'event', 'from_status', 'to_status'];

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
