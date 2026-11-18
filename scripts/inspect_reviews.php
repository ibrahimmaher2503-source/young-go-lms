<?php

declare(strict_types=1);

require __DIR__.'/telescope_tool.php';

echo "=== QUERIES FOR /instructor/submission-review ===\n";
$sub = getLatestRequestDetails('/instructor/submission-review');
foreach ($sub['queries'] as $i => $q) {
    echo ($i + 1).". [{$q['time_ms']}ms] {$q['sql']}\n";
}

echo "\n=== QUERIES FOR /instructor/essay-review ===\n";
$essay = getLatestRequestDetails('/instructor/essay-review');
foreach ($essay['queries'] as $i => $q) {
    echo ($i + 1).". [{$q['time_ms']}ms] {$q['sql']}\n";
}
