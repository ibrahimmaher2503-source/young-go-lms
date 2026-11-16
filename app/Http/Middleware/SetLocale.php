<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = Config::array('app.supported_locales');
        $default = App::currentLocale();
        $locale = $default;
        $user = $request->user();

        foreach ([
            $request->route('publicLocale'),
            $user instanceof User ? $user->locale : null,
            $request->hasSession() ? $request->session()->get('locale') : null,
            $request->cookie('locale'),
            $request->getPreferredLanguage(array_unique([$default, ...$supported])),
        ] as $candidate) {
            if (is_string($candidate) && in_array($candidate, $supported, true)) {
                $locale = $candidate;
                break;
            }
        }

        App::setLocale($locale);

        try {
            return $next($request);
        } finally {
            App::setLocale($default);
        }
    }
}
