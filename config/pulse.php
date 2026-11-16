<?php

declare(strict_types=1);

use Laravel\Pulse\Recorders;

return [
    'enabled' => env('PULSE_ENABLED', false),

    'recorders' => [
        Recorders\Exceptions::class => [
            'sample_rate' => 1,
            'location' => false,
            'ignore' => [],
        ],
        Recorders\Queues::class => [
            'sample_rate' => 1,
            'ignore' => [],
        ],
    ],
];
