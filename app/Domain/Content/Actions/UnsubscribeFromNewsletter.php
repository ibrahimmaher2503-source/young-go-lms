<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Audit\AuditLogger;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Ends a subscription. Public callers prove ownership through the signed link (checked before the action runs, actor
 * null); staff pass their account and are checked against the lead permission. Repeating it changes nothing.
 */
final class UnsubscribeFromNewsletter
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(NewsletterSubscriber $subscriber, ?User $actor = null): void
    {
        if ($actor !== null) {
            Gate::forUser($actor)->authorize('marketing.lead.view');
        }

        if (! $subscriber->isSubscribed()) {
            return;
        }

        $subscriber->update(['unsubscribed_at' => now()]);
        $this->audit->record('newsletter.unsubscribed', $subscriber, $actor?->getKey(), null, null, ['by' => $actor === null ? 'subscriber' : 'staff']);
    }
}
