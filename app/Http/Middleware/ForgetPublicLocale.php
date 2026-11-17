<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs after SetLocale (which reads the {publicLocale} prefix) and removes the parameter so the shared public controllers
 * receive the same arguments as on the unprefixed routes.
 */
final class ForgetPublicLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->route()?->forgetParameter('publicLocale');

        return $next($request);
    }
}
