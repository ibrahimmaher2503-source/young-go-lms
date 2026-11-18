<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Identity\Actions\ManageLearnerSessions;
use App\Models\User;
use Closure;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class TrackLearnerSession
{
    public function __construct(private readonly ManageLearnerSessions $sessions) {}

    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('web');
        if (! $guard instanceof SessionGuard) {
            return $next($request);
        }

        $session = $request->session();
        $user = $guard->user();
        $token = $session->get(ManageLearnerSessions::SESSION_KEY);
        if ($user instanceof User && $user->status === 'active' && $user->hasRole('student') && $session->has($guard->getName())) {
            if (! is_string($token)) {
                $token = $this->sessions->start($user, $session->getId());
                $session->put(ManageLearnerSessions::SESSION_KEY, $token);
            }
            if (! $this->sessions->touch($user, $token)) {
                $guard->logoutCurrentDevice();
                $session->invalidate();
                $session->regenerateToken();
                $message = __('auth.learner_session_ended');

                return $request->expectsJson() ? response()->json(['message' => $message], 401)
                    : redirect('/login')->with(['learner_session_notice' => true, 'locale' => app()->getLocale()]);
            }
        }

        $response = $next($request);
        $current = $guard->user();
        if ($user instanceof User && is_string($token) && (! $current instanceof User || ! $user->is($current))) {
            $this->sessions->end($user, $token);
            $session->forget(ManageLearnerSessions::SESSION_KEY);
        }
        if ($current instanceof User && $current->status === 'active' && $current->hasRole('student') && $session->has($guard->getName())
            && ! $session->has(ManageLearnerSessions::SESSION_KEY)) {
            $session->put(ManageLearnerSessions::SESSION_KEY, $this->sessions->start($current, $session->getId()));
        }

        return $response;
    }
}
