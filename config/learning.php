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
        // Tolerate two missed 15-second heartbeats without awarding time across an idle session.
        'max_heartbeat_gap_seconds' => 45,
        // Per-learner, per-lesson heartbeat limit per minute.
        'heartbeats_per_minute' => 30,
    ],
    // O-020 (PO decision 2026-11-18): a course without saved rules completes when every lesson is done and every required quiz is passed
    // (default pass mark 60%, config/assessment.php). A course may save its own rules. Unsaved defaults treat "no required quiz" as met.
    'completion' => [
        'defaults' => ['lesson_percent' => 100, 'require_quizzes' => true, 'require_assignments' => false, 'attendance_percent' => null, 'manual_approval' => false],
        // PRD §14 requires assignment acceptance; a grade alone does not satisfy that rule.
        'graded_counts_as_accepted' => false,
    ],
];
