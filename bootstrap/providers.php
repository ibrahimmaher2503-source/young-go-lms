<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\ObservabilityServiceProvider;
use App\Providers\SeoServiceProvider;
use App\Providers\StaffPanelsServiceProvider;
use App\Providers\TelescopeServiceProvider;

return [
    AppServiceProvider::class,
    ObservabilityServiceProvider::class,
    SeoServiceProvider::class,
    StaffPanelsServiceProvider::class,
    TelescopeServiceProvider::class,
];
