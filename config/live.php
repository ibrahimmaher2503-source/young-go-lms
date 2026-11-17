<?php

declare(strict_types=1);

return [
    // Minutes before the start at which a reminder goes out. Working defaults (one day and one hour); change here, no code needed.
    'reminder_offsets_minutes' => [1440, 60],

    // A learner who uses the join link more than this many minutes after the start is recorded as late. Working default.
    'late_after_minutes' => 10,
];
