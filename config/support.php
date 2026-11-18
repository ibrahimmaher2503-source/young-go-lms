<?php

declare(strict_types=1);

// T-P9-10 (ED-027): Learner 360 and support "view as learner".
return [
    'view_as' => [
        // A view-as session ends by itself after this many minutes; children's accounts get the shorter limit.
        'ttl_minutes' => 20,
        'minor_ttl_minutes' => 10,
        // A reason is required and kept in the audit trail.
        'reason_min' => 10,
        'reason_max' => 500,
    ],
    'learner_360' => [
        'search_limit' => 15,
        'list_rows' => 20,
        'audit_rows' => 25,
    ],
];
