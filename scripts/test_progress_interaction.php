<?php

declare(strict_types=1);

require __DIR__.'/LmsClient.php';
require __DIR__.'/telescope_tool.php';

$client = new LmsClient;
$client->login('student@younggo.test', 'password123');

// 1. Test POST /learn/1/1/progress (Heartbeat)
echo "Sending POST /learn/1/1/progress (position: 30s)...\n";
$resHeartbeat = $client->post('/learn/1/1/progress', ['position' => 30]);
echo "Status: {$resHeartbeat['status']} in {$resHeartbeat['time_ms']}ms\n";
echo "Body: {$resHeartbeat['content']}\n";

$tHeartbeat = getLatestRequestDetails('/progress');
echo "\nTelescope Entry for Heartbeat:\n";
echo "  UUID: {$tHeartbeat['uuid']}\n";
echo "  Status: {$tHeartbeat['status']}\n";
echo "  Duration: {$tHeartbeat['duration_ms']}ms\n";
echo "  Query Count: {$tHeartbeat['query_count']}\n";
echo "  SQL Time: {$tHeartbeat['total_sql_time_ms']}ms\n";
echo "  Writes Count: {$tHeartbeat['writes_count']}\n";
echo "  Slowest Query: {$tHeartbeat['slowest_query']['sql']} ({$tHeartbeat['slowest_query']['time_ms']}ms)\n";
echo "  Telescope URL: {$tHeartbeat['telescope_url']}\n";

echo "  All Queries in Heartbeat:\n";
foreach ($tHeartbeat['queries'] as $i => $q) {
    echo '    '.($i + 1).". [{$q['time_ms']}ms] {$q['sql']}\n";
}

// 2. Test POST /learn/1/1/complete (Mark Complete)
echo "\nSending POST /learn/1/1/complete...\n";
$resComplete = $client->post('/learn/1/1/complete', []);
echo "Status: {$resComplete['status']} in {$resComplete['time_ms']}ms\n";
echo "Body: {$resComplete['content']}\n";

$tComplete = getLatestRequestDetails('/complete');
echo "\nTelescope Entry for Complete:\n";
echo "  UUID: {$tComplete['uuid']}\n";
echo "  Status: {$tComplete['status']}\n";
echo "  Duration: {$tComplete['duration_ms']}ms\n";
echo "  Query Count: {$tComplete['query_count']}\n";
echo "  SQL Time: {$tComplete['total_sql_time_ms']}ms\n";
echo "  Writes Count: {$tComplete['writes_count']}\n";
echo "  Slowest Query: {$tComplete['slowest_query']['sql']} ({$tComplete['slowest_query']['time_ms']}ms)\n";
echo "  Telescope URL: {$tComplete['telescope_url']}\n";

echo "  All Queries in Complete:\n";
foreach ($tComplete['queries'] as $i => $q) {
    echo '    '.($i + 1).". [{$q['time_ms']}ms] {$q['sql']}\n";
}
