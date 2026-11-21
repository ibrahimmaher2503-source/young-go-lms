<?php

// SEC-11 | Medium: Telescope is off outside local and payment/PII routes and fields are never recorded | T-P1-11, T-P9-08, T-P11-01

declare(strict_types=1);

use App\Providers\TelescopeServiceProvider;
use Illuminate\Http\Request;
use Laravel\Telescope\Telescope;

/**
 * Loads config/telescope.php as the given environment would see it, without touching the running app config.
 * The env vars are set in every place Laravel's env() reads them and restored afterwards.
 */
function sec11TelescopeConfig(array $vars): array
{
    $names = ['APP_ENV', 'TELESCOPE_ENABLED'];
    $saved = [];
    foreach ($names as $name) {
        $saved[$name] = [$_ENV[$name] ?? null, $_SERVER[$name] ?? null, getenv($name)];
        unset($_ENV[$name], $_SERVER[$name]);
        putenv($name);
    }

    try {
        foreach ($vars as $name => $value) {
            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
            putenv("{$name}={$value}");
        }

        return require base_path('config/telescope.php');
    } finally {
        foreach ($saved as $name => [$env, $server, $process]) {
            if ($env === null) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $env;
            }
            if ($server === null) {
                unset($_SERVER[$name]);
            } else {
                $_SERVER[$name] = $server;
            }
            if ($process === false) {
                putenv($name);
            } else {
                putenv("{$name}={$process}");
            }
        }
    }
}

it('turns Telescope on by default only in local, and off in production and staging when TELESCOPE_ENABLED is unset', function (string $appEnv, bool $expected): void {
    $config = sec11TelescopeConfig(['APP_ENV' => $appEnv]);

    expect($config['enabled'])->toBe($expected);
})->with([
    'production' => ['production', false],
    'staging' => ['staging', false],
    'local' => ['local', true],
])->group('T-P1-11', 'T-P11-01');

it('does not record payment callbacks, manual proof, checkout or return routes', function (string $path): void {
    expect(Request::create($path)->is(config('telescope.ignore_paths')))->toBeTrue();
})->with([
    '/webhooks/paymob',
    '/orders/1/manual-proof',
    '/payments/manual-proofs/1',
    '/checkout/1',
    '/payments/return',
])->group('T-P9-08', 'T-P11-01');

it('hides payment and personal fields and provider headers from Telescope outside local', function (): void {
    // Register the provider the way a non-local environment does, then read what it hid.
    app()->detectEnvironment(fn () => 'production');
    $provider = new TelescopeServiceProvider(app());
    $method = new ReflectionMethod($provider, 'hideSensitiveRequestDetails');
    $method->invoke($provider);

    expect(Telescope::$hiddenRequestParameters)->toContain(
        'hmac', 'signature', 'obj', 'billing_data', 'sender_reference', 'payer_name', 'payer_phone',
    )->and(Telescope::$hiddenRequestHeaders)->toContain(
        'x-payment-signature', 'x-paymob-hmac', 'authorization',
    );
})->group('T-P1-11', 'T-P9-08', 'T-P11-01');
