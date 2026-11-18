<?php

declare(strict_types=1);

require __DIR__.'/telescope_tool.php';

use Illuminate\Support\Facades\DB;

$reqs = DB::table('telescope_entries')
    ->where('type', 'request')
    ->where(function ($q) {
        $q->where('content', 'like', '%quiz%')
            ->orWhere('content', 'like', '%100%');
    })
    ->orderByDesc('sequence')
    ->limit(10)
    ->get();

echo "Telescope Entries for Quiz Flow:\n";
foreach ($reqs as $r) {
    $c = json_decode($r->content, true);
    $uri = $c['uri'] ?? '';
    $method = $c['method'] ?? '';
    $status = $c['response_status'] ?? '';
    $duration = $c['duration'] ?? 0;

    $details = getRequestDetailsByUuid($r->uuid);
    echo "---------------------------------------------------------\n";
    echo "UUID: {$r->uuid}\n";
    echo "Endpoint: {$method} {$uri} (Status: {$status}, Duration: {$duration}ms)\n";
    echo "Queries: {$details['query_count']}, SQL Time: {$details['total_sql_time_ms']}ms, Writes: {$details['writes_count']}\n";
    echo 'Slowest Query: '.($details['slowest_query']['sql'] ?? 'none').' ('.($details['slowest_query']['time_ms'] ?? 0)."ms)\n";
    echo "Telescope URL: {$details['telescope_url']}\n";
}
