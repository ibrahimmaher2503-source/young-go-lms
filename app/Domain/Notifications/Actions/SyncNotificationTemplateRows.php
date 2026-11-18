<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Domain\Notifications\NotificationMatrix;
use App\Domain\Notifications\NotificationTemplate;

/** Makes sure every editable matrix event has a (blank) template row so the admin list shows the whole matrix. Idempotent, no audit: it changes no wording. */
final class SyncNotificationTemplateRows
{
    public function __invoke(): void
    {
        $have = NotificationTemplate::query()->pluck('event_key')->all();
        foreach (NotificationMatrix::all() as $row) {
            if ($row->editable() && ! in_array($row->key, $have, true)) {
                NotificationTemplate::query()->firstOrCreate(['event_key' => $row->key], ['is_active' => true]);
            }
        }
    }
}
