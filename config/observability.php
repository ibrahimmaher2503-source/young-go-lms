<?php

declare(strict_types=1);

/**
 * ED-021 error reporting. O-029 (decided 2026-11-18): Sentry SaaS. The reporter stays local-log only while SENTRY_DSN is empty
 * or the Sentry SDK is not installed (no composer package is required by the app); set the DSN to turn it on.
 */
return [
    'error_reporter' => env('ERROR_REPORTER', 'sentry'),

    'sentry' => [
        'dsn' => env('SENTRY_DSN'),
        'environment' => env('SENTRY_ENVIRONMENT', env('APP_ENV', 'production')),
    ],
];
