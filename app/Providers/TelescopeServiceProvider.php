<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
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
