<?php

declare(strict_types=1);

use App\Http\Middleware\EnforceSessionLimits;
use App\Http\Middleware\EnforceViewAsReadOnly;
use App\Http\Middleware\ResolveActingLearner;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: [__DIR__.'/../routes/web.php', __DIR__.'/../routes/localization.php', __DIR__.'/../routes/design-components.php', __DIR__.'/../routes/auth.php', __DIR__.'/../routes/profile.php', __DIR__.'/../routes/guardian.php', __DIR__.'/../routes/seo.php', __DIR__.'/../routes/pages.php', __DIR__.'/../routes/two-factor.php', __DIR__.'/../routes/assistant.php'],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: static function (): void {
            // T-P8-03: gateway callbacks live outside the web group (no session, CSRF or auth); see routes/webhooks.php.
            Route::group([], __DIR__.'/../routes/webhooks.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [SetLocale::class, ResolveActingLearner::class, EnforceSessionLimits::class, EnforceViewAsReadOnly::class]);
        $middleware->api(append: [SetLocale::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['child_password', 'child_password_confirmation', 'code']);
        // Throttle middleware runs before SetLocale (framework priority), so the 429 page would fall back to the default locale: render it inside SetLocale.
        $exceptions->render(static function (ThrottleRequestsException $e, Request $request) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return null;
            }

            return (new SetLocale)->handle($request, static fn (): Response => response()->view('errors.429', [], 429, $e->getHeaders()));
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
