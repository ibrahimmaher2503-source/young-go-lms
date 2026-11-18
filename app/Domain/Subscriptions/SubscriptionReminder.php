<?php

declare(strict_types=1);

namespace App\Domain\Subscriptions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A renewal reminder already handled for one period and day offset (unique per period and offset, so each goes out once).
 *
 * @property int $id
 * @property int $period_id
 * @property int $offset_days
 * @property Carbon|null $sent_at
 */
final class SubscriptionReminder extends Model
{
    protected $table = 'subscription_reminders';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sent_at' => 'datetime'];
    }
}
