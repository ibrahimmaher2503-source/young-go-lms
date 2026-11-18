<?php

declare(strict_types=1);

namespace App\Domain\Shared\Observability;

use Throwable;

/**
 * Sentry adapter for ED-021 (O-029). Always logs locally first; forwards to Sentry only when a DSN is configured and the
 * Sentry SDK function exists. Inert otherwise. Reporting never throws into the request.
 */
final class SentryErrorReporter implements ErrorReporter
{
    public function __construct(private readonly LogErrorReporter $log) {}

    public static function enabled(): bool
    {
        $dsn = config('observability.sentry.dsn');

        return config('observability.error_reporter') === 'sentry' && is_string($dsn) && trim($dsn) !== '';
    }

    public function report(Throwable $exception): void
    {
        $this->log->report($exception);

        if (! self::enabled() || ! function_exists('Sentry\captureException')) {
            return;
        }

        try {
            \Sentry\captureException($exception);
        } catch (Throwable) {
            // a failing reporter must never break the request
        }
    }
}
