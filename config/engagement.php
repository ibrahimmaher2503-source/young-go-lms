<?php

declare(strict_types=1);

return [
    'reviews' => [
        'body_min' => 10,
        'body_max' => 1000,
        // PRD YG-GAP-ENG-002: child accounts may review; they are always shown as first name + initial and always moderated.
        'children_may_review' => true,
        'public_per_page' => 10,
        'rating_cache_seconds' => 86400,
    ],
];
