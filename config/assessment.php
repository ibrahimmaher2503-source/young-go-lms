<?php

declare(strict_types=1);

return [
    // Seconds of network slack after the deadline in which a save or submit from the learner is still accepted. Working default.
    'grace_seconds' => 5,

    // Longest free-text answer accepted per question (characters).
    'max_text_length' => 5000,
];
