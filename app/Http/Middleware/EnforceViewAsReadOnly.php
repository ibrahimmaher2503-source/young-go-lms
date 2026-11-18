<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Support\ViewAsState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * While a staff member is viewing as a learner (ED-027, YG-GAP-OPS-002) the whole session is read-only: every write
 * method is refused, and the lesson assistant is closed for any method. Only "Stop" (a plain form post) gets through.
 * Staff panel actions are Livewire posts, so the panels are read-only too until the session ends.
 */
final class EnforceViewAsReadOnly
{
    private const array ALLOWED_WRITES = ['support.view-as.stop'];

    public function __construct(private readonly ViewAsState $state) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || ! $request->session()->has(ViewAsState::SESSION_KEY) || $this->state->current() === null) {
            return $next($request);
        }

        $assistant = $request->routeIs('assistant.*') || $request->is('api/v1/lessons/*/assistant*');
        $write = ! $request->isMethodSafe() && ! $request->routeIs(...self::ALLOWED_WRITES);

        if ($assistant || $write) {
            $message = __($assistant ? 'support.blocked_ai' : 'support.blocked_write');

            return $request->expectsJson() || $assistant
                ? response()->json(['message' => $message], 403)
                : abort(403, $message);
        }

        return $next($request);
    }
}
