<?php

declare(strict_types=1);

namespace App\Domain\Subscriptions\Actions;

use App\Domain\Notifications\NotificationDispatcher;
use App\Domain\Notifications\Notifications\TemplatedNotice;
use App\Domain\Subscriptions\Subscription;
use App\Domain\Subscriptions\SubscriptionReminder;
use App\Domain\Subscriptions\SubscriptionStatus;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Config;
use Throwable;

/**
 * Sends the renewal reminders that are due (YG-GAP-SUB-001: 7, 3 and 1 day before the period ends; `subscriptions.php`).
 * The reminder goes to the payer through the notification system (event `subscription.renewal_due`, optional) with a link
 * to the checkout page of a pending renewal order created here through RenewSubscription (the learner area if that fails). For each subscription only its latest period counts, and only
 * while it has not ended, was not cancelled, and has no later period (already renewed). When several offsets are already
 * reached (the job was down) only the tightest one is sent. An offset that had passed before the period began (a
 * short or freshly bought period) is skipped. A row in `subscription_reminders` (unique per period and offset) plus the
 * dispatcher dedupe key make every reminder go out once; a failed send removes the row so a later run retries.
 */
final class SendSubscriptionRenewalReminders
{
    public function __construct(private readonly NotificationDispatcher $dispatcher, private readonly RenewSubscription $renew) {}

    /** @return int reminders sent in this run */
    public function __invoke(): int
    {
        /** @var list<int> $offsets */
        $offsets = array_values(array_unique(array_filter(array_map('intval', (array) Config::get('subscriptions.reminder_offsets_days', [])), static fn (int $d): bool => $d > 0)));
        if ($offsets === []) {
            return 0;
        }
        sort($offsets);
        $horizon = now()->addDays(max($offsets));
        $sent = 0;

        Subscription::query()->where('status', SubscriptionStatus::Active->value)->whereNull('cancelled_at')
            ->whereHas('periods', static fn ($q) => $q->where('ends_at', '>', now())->where('ends_at', '<=', $horizon))
            ->with(['periods', 'plan', 'payer'])->chunkById(200, function ($rows) use ($offsets, &$sent): void {
                foreach ($rows as $subscription) {
                    /** @var Subscription $subscription */
                    $sent += $this->remind($subscription, $offsets) ? 1 : 0;
                }
            });

        return $sent;
    }

    private function link(User $payer, Subscription $subscription, int $offset): string
    {
        try {
            return route('checkout.order', ['order' => ($this->renew)($payer, $subscription, 'r'.$offset)->getKey()]);
        } catch (Throwable $exception) {
            report($exception);

            return route('learner.courses');
        }
    }

    /** @param  list<int>  $offsets ascending */
    private function remind(Subscription $subscription, array $offsets): bool
    {
        $period = $subscription->latestPeriod();
        $payer = $subscription->payer;
        if ($period === null || ! $payer instanceof User || $period->ends_at->lte(now())) {
            return false;
        }

        $due = null;
        foreach ($offsets as $offset) {
            $point = $period->ends_at->copy()->subDays($offset);
            if ($point->lte(now()) && $period->starts_at->lte($point)) {
                $due = $offset;
                break;
            }
        }
        if ($due === null) {
            return false;
        }

        try {
            $reminder = SubscriptionReminder::query()->create(['period_id' => $period->getKey(), 'offset_days' => $due]);
        } catch (UniqueConstraintViolationException) {
            return false; // already handled
        }

        $locale = $payer->preferredLocale() ?? (string) config('app.locale');
        $plan = (string) $subscription->plan?->getTranslation('name', $locale);
        $notice = new TemplatedNotice(
            'subscription.renewal_due',
            ['plan' => $plan, 'date' => $period->ends_at->copy()->locale($locale)->isoFormat('LL'), 'days' => $due],
            'subscription-reminder:'.$period->getKey().':'.$due,
            $this->link($payer, $subscription, $due),
        );

        try {
            $delivered = $this->dispatcher->send($payer, $notice);
        } catch (Throwable $exception) {
            $reminder->delete();
            report($exception);

            return false;
        }
        if (! $delivered) {
            $reminder->delete(); // opted out or already delivered under this key: nothing recorded as sent here, the dedupe key still blocks a repeat

            return false;
        }
        $reminder->forceFill(['sent_at' => now()])->save();

        return true;
    }
}
