<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Identity\ActingLearner;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Privacy\Actions\RecordCookieConsent;
use App\Domain\Privacy\Queries\ActiveMarketingTags;
use App\Http\Consent\ConsentSurface;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Cookie;

/** Cookie preferences (YG-GAP-MKT-001): accept and decline are the same one-step form, and the choice can be changed at any time. */
final class ConsentController
{
    public function show(Request $request, ActiveMarketingTags $tags): View
    {
        return view('consent.show', ['consent' => ConsentSurface::current($request), 'configured' => $tags->configured()]);
    }

    public function store(Request $request, RecordCookieConsent $record, ActingLearner $acting): Response|RedirectResponse
    {
        $decision = $request->validate(['decision' => ['required', 'in:accept,decline']])['decision'];

        $user = Auth::user();
        $child = $user instanceof User && GuardianChildLink::query()->where('child_id', $user->getKey())->exists();
        abort_if($child || $acting->isParentContext(), 403);

        $consent = $record($decision === 'accept', $user instanceof User ? $user : null);

        $cookies = [cookie(Config::string('consent.cookie'), $consent->toCookie(), Config::integer('consent.lifetime_days') * 1440, '/', null, $request->isSecure(), true, false, 'Lax')];
        if (! $consent->accepted) {
            array_push($cookies, ...$this->expiredTagCookies($request));
        }

        $response = $request->expectsJson() ? response()->noContent() : redirect($this->returnUrl($request));

        return $response->withCookies($cookies);
    }

    /** @return list<Cookie> */
    private function expiredTagCookies(Request $request): array
    {
        $names = array_values(array_map('strval', Config::array('consent.tag_cookies')));
        $prefixes = array_values(array_map('strval', Config::array('consent.tag_cookie_prefixes')));
        foreach (array_keys($request->cookies->all()) as $name) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with((string) $name, $prefix)) {
                    $names[] = (string) $name;
                }
            }
        }

        $domains = array_values(array_unique([null, $request->getHost(), '.'.$request->getHost()]));
        $expired = [];
        foreach (array_unique($names) as $name) {
            foreach ($domains as $domain) {
                $expired[] = cookie($name, '', -1, '/', $domain);
            }
        }

        return $expired;
    }

    /** Back to the page the visitor came from, never to another site. */
    private function returnUrl(Request $request): string
    {
        $previous = url()->previous();

        return parse_url($previous, PHP_URL_HOST) === $request->getHost() ? $previous : route('consent.show');
    }
}
