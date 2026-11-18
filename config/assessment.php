<?php

declare(strict_types=1);

return [
    // Seconds of network slack after the deadline in which a save or submit from the learner is still accepted. Working default.
    'grace_seconds' => 5,

    // Longest free-text answer accepted per question (characters).
    'max_text_length' => 5000,

    // O-020 (PO decision 2026-11-18): a quiz with no explicit pass mark is judged by this percentage of the total marks.
    // An instructor who sets a pass mark on the quiz overrides it for that quiz. Never null: there is always a pass mark.
    'default_pass_mark_percent' => 60,
];
