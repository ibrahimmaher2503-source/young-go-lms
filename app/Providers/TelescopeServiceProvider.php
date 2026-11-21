<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Telescope::night();

        $this->hideSensitiveRequestDetails();

        $isLocal = $this->app->environment('local');

        Telescope::filter(function (IncomingEntry $entry) use ($isLocal) {
            if (! $isLocal) {
                $uri = $entry->content['uri'] ?? null;
                $ignorePaths = config('telescope.ignore_paths', []);
                if (is_string($uri) && is_array($ignorePaths) && $ignorePaths !== []) {
                    $path = ltrim((string) parse_url($uri, PHP_URL_PATH), '/');
                    if ($path !== '' && Request::create('/'.$path)->is($ignorePaths)) {
                        return false;
                    }
                }
            }

            return $isLocal ||
                   $entry->isReportableException() ||
                   $entry->isFailedRequest() ||
                   $entry->isFailedJob() ||
                   $entry->isScheduledTask() ||
                   $entry->hasMonitoredTag();
        });
    }

    /**
     * Prevent sensitive request details from being logged by Telescope.
     */
    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment('local')) {
            return;
        }

        Telescope::hideRequestParameters([
            '_token',
            'password',
            'password_confirmation',
            'current_password',
            'hmac',
            'signature',
            'obj',
            'billing_data',
            'source_data',
            'sender_reference',
            'payer_name',
            'payer_phone',
            'phone',
            'email',
            'token',
            'secret',
            'api_key',
            'client_secret',
            'child_password',
            'code',
            'recovery_code',
            'recovery_codes',
            'sender_account',
            'sender_name',
            'transfer_reference',
            'payout_details',
            'instapay_address',
        ]);

        Telescope::hideRequestHeaders([
            'authorization',
            'cookie',
            'set-cookie',
            'x-csrf-token',
            'x-xsrf-token',
            'x-payment-signature',
            'x-paymob-hmac',
        ]);
    }

    protected function authorization(): void
    {
        $this->gate();

        Telescope::auth(static fn (): bool => Gate::allows('viewTelescope'));
    }

    protected function gate(): void
    {
        Gate::define('viewTelescope', fn (User $user): bool => $this->app->environment('local')
            && $user->hasRole('super_admin'));
    }
}
