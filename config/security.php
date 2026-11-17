<?php

declare(strict_types=1);

return [
    // Account-sharing limits for learner accounts (YG-GAP-SEC-001, R-015). Staff accounts are not limited here.
    'sessions' => [
        'enabled' => (bool) env('SESSION_LIMITS_ENABLED', true),
        'roles' => ['student', 'parent'],
        'max_concurrent' => (int) env('SESSION_MAX_CONCURRENT', 2),
        'max_devices' => (int) env('SESSION_MAX_DEVICES', 3),
        // A session that has been quiet this long no longer counts toward the concurrent limit.
        'idle_minutes' => (int) env('SESSION_IDLE_MINUTES', 120),
        // last_active_at is refreshed at most this often.
        'touch_seconds' => 60,
        'device_cookie' => 'yg_device',
    ],
];
