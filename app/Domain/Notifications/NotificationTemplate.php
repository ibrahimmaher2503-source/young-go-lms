<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use Illuminate\Database\Eloquent\Model;

/**
 * Admin wording override for one matrix event. A null text for a locale falls back to the language file default.
 *
 * @property int $id
 * @property string $event_key
 * @property string|null $title_ar
 * @property string|null $title_en
 * @property string|null $body_ar
 * @property string|null $body_en
 * @property bool $is_active
 */
final class NotificationTemplate extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
