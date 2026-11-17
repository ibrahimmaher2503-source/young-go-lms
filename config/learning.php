<?php

declare(strict_types=1);

return [
    // Course completion defaults are an OPEN owner decision (O-020). These are working defaults, changeable here.
    'progress' => [
        // A video or audio lesson completes on its own once this share of its length has really been played.
        'complete_at_percent' => 90,
        // Lesson types a learner may mark done by hand. Video and audio never can: the rule above decides.
        'manual_complete_types' => ['document', 'text'],
        // When a recorded lesson has no known length the server cannot judge watching, so a manual mark is allowed.
        'manual_when_duration_unknown' => true,
        // Heartbeats may not move the furthest position faster than playback at this speed plus a small slack (seconds).
        'max_speed' => 2.5,
        'slack_seconds' => 30,
        // Per-learner, per-lesson heartbeat limit per minute.
        'heartbeats_per_minute' => 30,
    ],
];
