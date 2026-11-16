<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Settings\PlatformFlag;
use Illuminate\Support\ServiceProvider;
use Laravel\Pennant\Feature;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (PlatformFlag::cases() as $flag) {
            Feature::define($flag->value, false);
        }
    }
}
