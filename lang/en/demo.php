<?php

declare(strict_types=1);

return [
    'refused_production' => 'The demo commands never run in production.',
    'refused_environment' => 'The demo commands only run in these environments: :environments.',
    'already_seeded' => 'Demo data already exists. Run demo:reset to rebuild it.',
    'seeded' => 'Demo data created: :users accounts, :courses courses.',
    'removed' => 'Demo data removed (:rows rows).',
    'nothing_to_remove' => 'No demo data found.',
    'real_data_blocks_reset' => 'Reset stopped: real data points at demo records (:table). Nothing was removed.',
];
