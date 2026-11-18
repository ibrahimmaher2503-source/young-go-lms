<?php

declare(strict_types=1);

require __DIR__.'/LmsClient.php';
require __DIR__.'/telescope_tool.php';

$client = new LmsClient;
$res = $client->get('/courses');
echo "Fetched /courses, status: {$res['status']} in {$res['time_ms']}ms\n";

$t = getLatestRequestDetails('/courses');
if ($t) {
    echo "Latest /courses in Telescope:\n";
    echo "  UUID: {$t['uuid']}\n";
    echo "  Duration: {$t['duration_ms']}ms\n";
    echo "  Queries: {$t['query_count']}\n";
    echo "  Telescope URL: {$t['telescope_url']}\n";
} else {
    echo "Error: No entry found in Telescope.\n";
}
