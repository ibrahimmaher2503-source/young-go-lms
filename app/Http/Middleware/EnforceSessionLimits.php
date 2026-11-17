<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Identity\SessionLimiter;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * On every signed-in request of a learner account: record the session and device, and sign the person out when their
 * session was ended by the concurrent limit or the device limit is reached. Runs for the web group.
 */
final class EnforceSessionLimits
{
    public function __construct(private readonly SessionLimiter $limiter) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User || ! $request->hasSession() || ! $this->limiter->applies($user)) {
            return $next($request);
        }

        $cookieName = (string) config('security.sessions.device_cookie');
        $token = (string) $request->cookie($cookieName, '');
        $issued = $token === '';
        if ($issued) {
            $token = $this->limiter->newDeviceToken();
        }

        $verdict = $this->limiter->check($user, $request->session()->getId(), $token, (string) $request->ip(), (string) $request->userAgent());
        if ($verdict !== SessionLimiter::OK) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $message = $verdict === SessionLimiter::DEVICE_LIMIT ? 'device_limit' : 'signed_out_elsewhere';

            return $request->expectsJson() ? response()->json(['message' => __('auth.'.$message)], 401) : redirect('/login')->with('session_notice', __('auth.'.$message));
        }

        $response = $next($request);
        if ($issued) {
            $response->headers->setCookie(Cookie::make($cookieName, $token, 60 * 24 * 730, '/', null, $request->isSecure(), true, false, 'lax'));
        }

        return $response;
    }
}
