<?php

declare(strict_types=1);

require __DIR__.'/telescope_tool.php';

$d = getRequestDetailsByUuid('a34015fa-7d31-4637-8f35-04f33b599c71');
echo "Queries for /instructor/courses/1/curriculum (Total: {$d['query_count']}):\n";
$grouped = [];
foreach ($d['queries'] as $q) {
    $sql = preg_replace('/\s+/', ' ', trim($q['sql']));
    $grouped[$sql] = ($grouped[$sql] ?? 0) + 1;
}
arsort($grouped);
foreach ($grouped as $sql => $cnt) {
    echo "  - [x{$cnt}]: {$sql}\n";
}
