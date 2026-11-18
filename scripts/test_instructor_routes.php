<?php

declare(strict_types=1);

require __DIR__.'/LmsClient.php';
require __DIR__.'/telescope_tool.php';

$client = new LmsClient;
$ok = $client->login('instructor@younggo.test', 'password123');
echo 'Instructor login: '.($ok ? 'SUCCESS' : 'FAIL')."\n";

$routes = [
    '/instructor/courses',
    '/instructor/submission-review',
    '/instructor/essay-review',
    '/instructor/library',
    '/instructor/courses/1/learners',
];

foreach ($routes as $route) {
    echo "\nFetching {$route}...\n";
    $res = $client->get($route);
    echo "  Status: {$res['status']} in {$res['time_ms']}ms (length {$res['content_length']})\n";

    $t = getLatestRequestDetails($route);
    if ($t && $t['uri'] === $route) {
        echo "  Telescope UUID: {$t['uuid']}\n";
        echo "  Duration: {$t['duration_ms']}ms | Queries: {$t['query_count']} | SQL Time: {$t['total_sql_time_ms']}ms\n";
        echo '  Slowest Query: '.($t['slowest_query']['sql'] ?? 'none').' ('.($t['slowest_query']['time_ms'] ?? 0)."ms)\n";
        echo "  Writes Count: {$t['writes_count']}\n";
        echo "  Telescope URL: {$t['telescope_url']}\n";
    } else {
        echo "  Telescope match not found or different URI.\n";
    }
}
