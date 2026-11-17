<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * A newsletter address with the consent wording version it agreed to (YG-WEB-008). The address is stored lower-cased.
 *
 * @property int $id
 * @property string $email
 * @property string $locale
 * @property string $source
 * @property string $consent_version
 * @property Carbon $consented_at
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $unsubscribed_at
 */
final class NewsletterSubscriber extends Model
{
    protected $guarded = ['id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['consented_at' => 'datetime', 'confirmed_at' => 'datetime', 'unsubscribed_at' => 'datetime'];
    }

    public function isSubscribed(): bool
    {
        return $this->unsubscribed_at === null;
    }

    /** Non-expiring signed link for every newsletter email; it works without a login and cannot be forged for another id. */
    public function unsubscribeUrl(): string
    {
        return URL::signedRoute('newsletter.unsubscribe', ['subscriber' => $this->getKey()]);
    }
}
