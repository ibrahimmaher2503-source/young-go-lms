<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Identity\TwoFactorSession;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires a passed second factor for enrolled users, and enrollment for roles that must use 2FA.
 * Use `EnforceTwoFactor::class.':setup'` on the security pages so a not-yet-enrolled user can reach them.
 */
final class EnforceTwoFactor
{
    public function handle(Request $request, Closure $next, string $mode = ''): Response
    {
        $user = $request->user();
        if (! Config::boolean('identity.two_factor_enforced') || ! $user instanceof User) {
            return $next($request);
        }

        if ($user->hasTwoFactorEnabled()) {
            return TwoFactorSession::isVerified($user) ? $next($request) : $this->deny($request, 'challenge', '/two-factor-challenge');
        }

        if ($user->requiresTwoFactor() && $mode !== 'setup') {
            return $this->deny($request, 'setup', '/security/two-factor');
        }

        return $next($request);
    }

    private function deny(Request $request, string $kind, string $path): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => __('auth.two_factor_'.$kind.'_required'), 'two_factor' => $kind], 403);
        }

        return redirect($path);
    }
}
