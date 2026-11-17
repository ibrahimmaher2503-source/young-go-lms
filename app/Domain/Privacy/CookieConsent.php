<?php

declare(strict_types=1);

namespace App\Domain\Privacy;

use Illuminate\Support\Facades\Config;

/**
 * A visitor's cookie decision as kept in the first-party consent cookie. A decision made for another wording version, or an
 * unreadable cookie, counts as "not decided" so the choice is asked again whenever the wording changes.
 */
final readonly class CookieConsent
{
    private function __construct(public bool $decided, public bool $accepted) {}

    public static function none(): self
    {
        return new self(false, false);
    }

    public static function decision(bool $accepted): self
    {
        return new self(true, $accepted);
    }

    public static function fromCookie(?string $raw): self
    {
        if ($raw === null || ! str_contains($raw, '|')) {
            return self::none();
        }

        [$version, $choice] = explode('|', $raw, 2);

        if ($version !== self::version() || ! in_array($choice, ['accepted', 'declined'], true)) {
            return self::none();
        }

        return new self(true, $choice === 'accepted');
    }

    public static function version(): string
    {
        return Config::string('consent.version');
    }

    public function toCookie(): string
    {
        return self::version().'|'.($this->accepted ? 'accepted' : 'declined');
    }
}
