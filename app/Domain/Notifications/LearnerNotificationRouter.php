<?php

declare(strict_types=1);

namespace App\Domain\Notifications;

use App\Domain\Identity\Models\GuardianChildLink;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Throwable;

/**
 * ED-017 / YG-GAP-FAM-008. Every learner notification goes through here.
 * A learner with an email gets the notification's own channels. A child without an email gets it
 * in the app only, and each verified, active guardian with a verified email gets an email copy.
 */
final class LearnerNotificationRouter
{
    /** @return list<User> guardians that received an email copy */
    public function send(User $learner, Notification $notification): array
    {
        if ($learner->getAttribute('email') !== null) {
            $learner->notify($notification);

            return [];
        }

        $learner->notifyNow($notification, ['database']);

        $guardians = [];
        $links = GuardianChildLink::query()->with('guardian')
            ->where('child_id', $learner->getKey())->where('status', GuardianChildLink::STATUS_VERIFIED)->get();

        foreach ($links as $link) {
            $guardian = $link->guardian;
            if ($guardian->getAttribute('status') !== 'active' || $guardian->getAttribute('email') === null || ! $guardian->hasVerifiedEmail()) {
                continue;
            }

            try {
                $guardian->notifyNow((new GuardianCopyNotification($notification, $learner))->locale($guardian->preferredLocale()), ['mail']);
                $guardians[] = $guardian;
            } catch (Throwable $exception) {
                // One failing mailbox must not stop the other guardians.
                report($exception);
            }
        }

        return $guardians;
    }
}
