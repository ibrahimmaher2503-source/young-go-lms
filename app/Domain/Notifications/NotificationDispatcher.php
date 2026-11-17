<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Throwable;

/**
 * Sends a LocalizedNotification at most once per dedupe key and recipient. The key is recorded first; if sending then
 * fails the record is removed, so a retry can still deliver. Routing (children without email, guardian copies) is the
 * LearnerNotificationRouter's job.
 */
final class NotificationDispatcher
{
    public function __construct(private readonly LearnerNotificationRouter $router) {}

    /** @return bool true when it was sent now, false when this key was already sent to this recipient */
    public function send(User $recipient, LocalizedNotification $notification): bool
    {
        try {
            $dispatch = NotificationDispatch::query()->create([
                'dedupe_key' => $notification->dedupeKey(), 'recipient_id' => $recipient->getKey(), 'notification' => class_basename($notification),
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        try {
            $this->router->send($recipient, $notification);
        } catch (Throwable $exception) {
            $dispatch->delete();

            throw $exception;
        }

        return true;
    }
}
