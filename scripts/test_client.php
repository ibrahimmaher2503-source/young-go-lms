<?php

declare(strict_types=1);

require __DIR__.'/LmsClient.php';
require __DIR__.'/telescope_tool.php';

$client = new LmsClient;
$ok = $client->login('student@younggo.test', 'password123');
echo 'Login result: '.($ok ? 'SUCCESS' : 'FAIL')."\n";

$res = $client->get('/my');
echo "GET /my response: status {$res['status']} in {$res['time_ms']}ms (length {$res['content_length']})\n";

$t = getLatestRequestDetails('/my');
echo "Telescope Match:\n";
echo "  UUID: {$t['uuid']}\n";
echo "  URI: {$t['uri']}\n";
echo "  Status: {$t['status']}\n";
echo "  Duration: {$t['duration_ms']}ms\n";
echo "  Query Count: {$t['query_count']}\n";
echo "  SQL Time: {$t['total_sql_time_ms']}ms\n";
echo "  Writes Count: {$t['writes_count']}\n";
echo "  Telescope URL: {$t['telescope_url']}\n";
if (! empty($t['writes'])) {
    echo "  Writes detected during GET /my:\n";
    foreach ($t['writes'] as $w) {
        echo "    - [{$w['action']}] {$w['sql']} ({$w['time_ms']}ms)\n";
    }
}
