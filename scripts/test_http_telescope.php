<?php

declare(strict_types=1);

$ch = curl_init('http://127.0.0.1:8080/courses');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Request to /courses: status {$status}, length ".strlen((string) $res)."\n";

require __DIR__.'/telescope_tool.php';
$details = getLatestRequestDetails('/courses');
echo "Telescope Entry Found: UUID = {$details['uuid']}\n";
echo "URI: {$details['uri']}, Status: {$details['status']}, Queries: {$details['query_count']}, SQL Time: {$details['total_sql_time_ms']}ms\n";
echo "Telescope URL: {$details['telescope_url']}\n";
