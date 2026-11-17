<?php

declare(strict_types=1);

namespace App\Http\Consent;

use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Privacy\CookieConsent;
use App\Domain\Privacy\Queries\ActiveMarketingTags;
use App\Domain\Privacy\Queries\MarketingSurface;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Throwable;

/**
 * Gathers the request facts for the layout and answers three things: is this page a marketing surface with at least one tag
 * configured (otherwise no banner and no markup at all), should the banner show (no decision for this wording version), and
 * which tags to print (only after an accept). The result is memoised per request.
 */
final class ConsentSurface
{
    public function __construct(private readonly Request $request, private readonly MarketingSurface $surface) {}

    /** @return array{active: bool, banner: bool, tags: array{ga4: ?string, meta_pixel: ?string}} */
    public function resolve(string $variant, bool $minimal, bool $parentContext): array
    {
        /** @var array{active: bool, banner: bool, tags: array{ga4: ?string, meta_pixel: ?string}}|null $cached */
        $cached = $this->request->attributes->get('consent.surface');
        if ($cached !== null) {
            return $cached;
        }

        $none = ['ga4' => null, 'meta_pixel' => null];
        $route = $this->request->route()?->getName();

        $tags = $none;
        $active = false;
        if ($variant === 'public' && ! $minimal) { // cheap checks first: error and auth pages never touch the database here
            try {
                $user = Auth::user();
                $child = $user instanceof User && GuardianChildLink::query()->where('child_id', $user->getKey())->exists();
                $active = ($this->surface)($variant, $minimal, is_string($route) ? $route : null, $child, $parentContext);
            } catch (Throwable) {
                $active = false;
            }
        }

        if ($active) {
            try {
                $configured = app(ActiveMarketingTags::class);
                $active = $configured->configured();
                $tags = $active ? $configured() : $none;
            } catch (Throwable) {
                $active = false; // settings unavailable (for example no database): no banner, no tags
            }
        }

        $consent = self::current($this->request);
        $result = [
            'active' => $active,
            'banner' => $active && ! $consent->decided && $route !== 'consent.show',
            'tags' => $active && $consent->accepted ? $tags : $none,
        ];

        $this->request->attributes->set('consent.surface', $result);

        return $result;
    }

    public static function current(Request $request): CookieConsent
    {
        $raw = $request->cookie(Config::string('consent.cookie'));

        return CookieConsent::fromCookie(is_string($raw) ? $raw : null);
    }
}
