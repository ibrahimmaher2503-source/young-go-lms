<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Shared\Observability\ErrorReporter;
use App\Domain\Shared\Observability\LogErrorReporter;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\ServiceProvider;
use Laravel\Pulse\Pulse;
use Throwable;

final class ObservabilityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ErrorReporter::class, LogErrorReporter::class);
        $this->app->make(Pulse::class)->ignoreRoutes();
    }

    public function boot(): void
    {
        $this->app->booted(function (): void {
            $this->callAfterResolving(ExceptionHandler::class, function (ExceptionHandler $handler): void {
                if (! method_exists($handler, 'reportable')) {
                    return;
                }

                $handler->reportable(function (Throwable $exception): bool {
                    $this->app->make(ErrorReporter::class)->report($exception);

                    return false;
                });
            });
        });
    }
}
