<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\ObservabilityServiceProvider;
use App\Providers\StaffPanelsServiceProvider;

return [
    AppServiceProvider::class,
    ObservabilityServiceProvider::class,
    StaffPanelsServiceProvider::class,
];
