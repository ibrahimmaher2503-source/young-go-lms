<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Identity\ActingLearner;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Revalidates any child context each request and exposes it as the `actingLearner` request attribute. */
final class ResolveActingLearner
{
    public function __construct(private readonly ActingLearner $acting) {}

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession() && $request->session()->has(ActingLearner::SESSION_KEY)) {
            $request->attributes->set('actingLearner', $this->acting->current());
        }

        return $next($request);
    }
}
