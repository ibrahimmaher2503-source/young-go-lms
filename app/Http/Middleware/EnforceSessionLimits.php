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

/** Register devices at sign-in and enforce revocation and concurrent limits on signed-in requests. */
final class EnforceSessionLimits
{
    public function __construct(private readonly SessionLimiter $limiter) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession()) {
            return $next($request);
        }

        $user = $request->user();
        $sessionId = $request->session()->getId();
        $cookieName = (string) config('security.sessions.device_cookie');
        $token = (string) $request->cookie($cookieName, '');
        $ip = (string) $request->ip();
        $agent = (string) $request->userAgent();
        if ($user instanceof User && $this->limiter->applies($user)) {
            $verdict = $this->limiter->check($user, $sessionId, $token, $ip, $agent, register: false);
            if ($verdict !== SessionLimiter::OK) {
                return $this->reject($request, $verdict);
            }
        }

        $response = $next($request);
        $currentId = $request->session()->getId();
        if ($request->isMethodSafe() || $currentId === $sessionId) {
            return $response;
        }

        $current = $request->user();
        if (! $current instanceof User || ! $this->limiter->applies($current)) {
            return $response;
        }

        if ($user instanceof User && $current->is($user)) {
            return $this->limiter->rotate($current, $sessionId, $currentId)
                ? $response
                : $this->reject($request, SessionLimiter::SIGNED_OUT);
        }

        $issued = $token === '';
        $token = $issued ? $this->limiter->newDeviceToken() : $token;
        $verdict = $this->limiter->check($current, $currentId, $token, $ip, $agent);
        if ($verdict !== SessionLimiter::OK) {
            return $this->reject($request, $verdict);
        }

        if ($issued) {
            $response->headers->setCookie(Cookie::make($cookieName, $token, 60 * 24 * 730, '/', null, $request->isSecure(), true, false, 'lax'));
        }

        return $response;
    }

    private function reject(Request $request, string $verdict): Response
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $message = $verdict === SessionLimiter::DEVICE_LIMIT ? 'device_limit' : 'signed_out_elsewhere';

        return $request->expectsJson() ? response()->json(['message' => __('auth.'.$message)], 401) : redirect('/login')->with('session_notice', __('auth.'.$message));
    }
}
