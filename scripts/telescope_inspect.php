<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

$requests = DB::table('telescope_entries')
    ->where('type', 'request')
    ->orderByDesc('sequence')
    ->limit(10)
    ->get();

foreach ($requests as $r) {
    $content = json_decode($r->content, true);
    $uri = $content['uri'] ?? 'N/A';
    $method = $content['method'] ?? 'GET';
    $status = $content['response_status'] ?? 200;
    $duration = $content['duration'] ?? 0;
    $memory = $content['memory'] ?? 0;

    $queriesCount = DB::table('telescope_entries')
        ->where('batch_id', $r->batch_id)
        ->where('type', 'query')
        ->count();

    echo "UUID: {$r->uuid} | {$method} {$uri} -> {$status} ({$duration}ms, {$queriesCount} queries) | Batch: {$r->batch_id}\n";
}
