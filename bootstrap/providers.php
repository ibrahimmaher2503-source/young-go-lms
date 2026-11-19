<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\ObservabilityServiceProvider;
use App\Providers\SeoServiceProvider;
use App\Providers\StaffPanelsServiceProvider;
use App\Providers\TelescopeServiceProvider;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

$providers = [
    AppServiceProvider::class,
    ObservabilityServiceProvider::class,
    SeoServiceProvider::class,
    StaffPanelsServiceProvider::class,
];

if (class_exists(TelescopeApplicationServiceProvider::class)) {
    $providers[] = TelescopeServiceProvider::class;
}

return $providers;
