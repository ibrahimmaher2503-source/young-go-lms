<?php

declare(strict_types=1);

require __DIR__.'/telescope_tool.php';
require __DIR__.'/LmsClient.php';

$client = new LmsClient;
$client->login('instructor@younggo.test', 'password123');

$res = $client->get('/instructor/courses/1/curriculum');
echo "GET /instructor/courses/1/curriculum: Status {$res['status']} in {$res['time_ms']}ms (length {$res['content_length']})\n";

$t = getLatestRequestDetails('/instructor/courses/1/curriculum');
if ($t) {
    echo "Telescope UUID: {$t['uuid']}\n";
    echo "Duration: {$t['duration_ms']}ms | Queries: {$t['query_count']} | SQL Time: {$t['total_sql_time_ms']}ms\n";
    echo 'Slowest Query: '.($t['slowest_query']['sql'] ?? 'none').' ('.($t['slowest_query']['time_ms'] ?? 0)."ms)\n";
    echo "Writes Count: {$t['writes_count']}\n";
    echo "Telescope URL: {$t['telescope_url']}\n";
}
