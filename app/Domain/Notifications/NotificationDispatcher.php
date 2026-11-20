<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use App\Domain\Notifications\Actions\SendPhoneReminder;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Ramsey\Uuid\Uuid;
use Throwable;

/**
 * Sends a LocalizedNotification at most once per dedupe key and recipient. The key is recorded first; if sending then
 * fails the record is removed, so a retry can still deliver. Routing (children without email, guardian copies) is the
 * LearnerNotificationRouter's job.
 */
final class NotificationDispatcher
{
    public function __construct(private readonly LearnerNotificationRouter $router, private readonly SendPhoneReminder $phoneReminder) {}

    /** @return bool true when it was sent now, false when this key was already sent to this recipient */
    public function send(User $recipient, LocalizedNotification $notification): bool
    {
        // T-P9-05: an optional notice the recipient turned off is not sent (and not recorded, so turning it on later still works).
        if (! NotificationPreferences::allows($recipient, $notification->event())) {
            return false;
        }

        try {
            $dispatch = NotificationDispatch::query()->create([
                'dedupe_key' => $notification->dedupeKey(), 'recipient_id' => $recipient->getKey(), 'notification' => class_basename($notification),
            ]);
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        $notification = clone $notification;
        $notification->id = Uuid::uuid5(Uuid::NAMESPACE_URL, 'young-go:notification:'.$recipient->getKey().':'.$notification->dedupeKey())->toString();

        try {
            $this->router->send($recipient, $notification);
        } catch (Throwable $exception) {
            $dispatch->delete();

            throw $exception;
        }

        // Best-effort secondary phone copy after email/inbox succeeded; never delete $dispatch or trigger duplicate email on phone failure.
        try {
            ($this->phoneReminder)($recipient, $notification);
        } catch (Throwable $exception) {
            report($exception);
        }

        return true;
    }
}
