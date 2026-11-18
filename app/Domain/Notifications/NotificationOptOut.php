<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use Illuminate\Database\Eloquent\Model;

/**
 * A user switched an optional notice off.
 *
 * @property int $user_id
 * @property string $event_key
 */
final class NotificationOptOut extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'user_notification_opt_outs';

    protected $guarded = ['id'];
}
