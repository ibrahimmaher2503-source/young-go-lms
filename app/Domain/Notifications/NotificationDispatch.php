<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $dedupe_key
 * @property int $recipient_id
 * @property string $notification
 */
final class NotificationDispatch extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'notification_dispatches';

    protected $guarded = ['id'];
}
