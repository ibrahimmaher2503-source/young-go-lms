<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Audit\AuditLogger;
use App\Models\NewsletterSubscriber;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Config;

/**
 * Subscribes an address with the consent wording version in force. The caller has already checked the consent box.
 * Idempotent: an active subscriber is left untouched, an unsubscribed address is re-subscribed with fresh consent. The
 * outcome is the same for the caller either way, so the form cannot be used to find out who is subscribed.
 */
final class SubscribeToNewsletter
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function __invoke(string $email, string $locale, string $source): void
    {
        $email = mb_strtolower(trim($email));
        $sources = array_values(array_map('strval', Config::array('contact.newsletter_sources')));
        $source = in_array($source, $sources, true) ? $source : ($sources[0] ?? 'page');

        $subscriber = NewsletterSubscriber::query()->where('email', $email)->first();

        if ($subscriber?->isSubscribed() === true) {
            return;
        }

        $values = [
            'locale' => $locale,
            'source' => $source,
            'consent_version' => Config::string('contact.newsletter_consent_version'),
            'consented_at' => now(),
            'unsubscribed_at' => null,
        ];

        try {
            if ($subscriber === null) {
                $subscriber = NewsletterSubscriber::query()->create(['email' => $email, ...$values]);
            } else {
                $subscriber->update($values);
            }
        } catch (UniqueConstraintViolationException) {
            return; // a concurrent request subscribed the same address
        }

        $this->audit->record('newsletter.subscribed', $subscriber, null, null, null, [
            'source' => $source,
            'consent_version' => $values['consent_version'],
        ]);
    }
}
