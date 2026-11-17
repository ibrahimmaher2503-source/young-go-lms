<?php

declare(strict_types=1);

namespace App\Domain\Live;

use App\Models\LiveSession;
use Illuminate\Validation\ValidationException;

/** The manual driver: the link is typed in by staff and must be a plain https address. */
final class ManualMeetingProvider implements MeetingProvider
{
    public function keys(): array
    {
        return LiveSession::PROVIDERS;
    }

    public function validateUrl(string $provider, ?string $url): ?string
    {
        if (! in_array($provider, $this->keys(), true)) {
            throw ValidationException::withMessages(['provider' => __('live.provider_invalid')]);
        }
        $url = $url === null ? null : trim($url);
        if ($url === null || $url === '') {
            return null; // a link can be added later, before the session opens
        }
        $parts = parse_url($url);
        if (strlen($url) > 2048 || ! is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || blank($parts['host'] ?? null) || isset($parts['user']) || isset($parts['pass'])) {
            throw ValidationException::withMessages(['meeting_url' => __('live.url_invalid')]);
        }

        return $url;
    }
}
