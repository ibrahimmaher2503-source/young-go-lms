<?php

declare(strict_types=1);

if (! isset($app) || ! ($app instanceof Application)) {
    require __DIR__.'/../vendor/autoload.php';
    $app = require __DIR__.'/../bootstrap/app.php';
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();
}

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\DB;

function getLatestRequestDetails(?string $filterUri = null): ?array
{
    $query = DB::table('telescope_entries')
        ->where('type', 'request')
        ->orderByDesc('sequence');

    if ($filterUri !== null) {
        $escaped = str_replace('/', '\/', $filterUri);
        $query->where(function ($q) use ($filterUri, $escaped) {
            $q->where('content', 'like', "%{$filterUri}%")
                ->orWhere('content', 'like', "%{$escaped}%");
        });
    }

    $req = $query->first();
    if (! $req) {
        return null;
    }

    return getRequestDetailsFromRow($req);
}

function getRequestDetailsByUuid(string $uuid): ?array
{
    $req = DB::table('telescope_entries')
        ->where('uuid', $uuid)
        ->first();
    if (! $req) {
        return null;
    }

    return getRequestDetailsFromRow($req);
}

function getRequestDetailsFromRow(object $req): array
{
    $reqContent = json_decode($req->content, true);
    $batchId = $req->batch_id;

    // Get all queries for this request batch
    $queries = DB::table('telescope_entries')
        ->where('batch_id', $batchId)
        ->where('type', 'query')
        ->orderBy('sequence')
        ->get();

    $queryDetails = [];
    $writes = [];
    $slowestQuery = null;
    $maxQueryTime = 0.0;
    $totalQueryTime = 0.0;

    foreach ($queries as $q) {
        $qData = json_decode($q->content, true);
        $sql = $qData['sql'] ?? '';
        $time = (float) ($qData['time'] ?? 0.0);
        $bindings = $qData['bindings'] ?? [];

        $totalQueryTime += $time;
        if ($time > $maxQueryTime) {
            $maxQueryTime = $time;
            $slowestQuery = [
                'sql' => $sql,
                'time_ms' => $time,
                'bindings' => $bindings,
            ];
        }

        $queryDetails[] = [
            'sql' => $sql,
            'time_ms' => $time,
            'bindings' => $bindings,
        ];

        if (preg_match('/^(insert|update|delete)\b/i', trim($sql))) {
            $writes[] = [
                'sql' => $sql,
                'time_ms' => $time,
                'bindings' => $bindings,
            ];
        }
    }

    return [
        'uuid' => $req->uuid,
        'batch_id' => $req->batch_id,
        'telescope_url' => "http://127.0.0.1:8080/telescope/requests/{$req->uuid}",
        'uri' => $reqContent['uri'] ?? '',
        'method' => $reqContent['method'] ?? 'GET',
        'status' => $reqContent['response_status'] ?? 200,
        'duration_ms' => $reqContent['duration'] ?? 0,
        'memory_mb' => isset($reqContent['memory']) ? round($reqContent['memory'] / 1024 / 1024, 2) : 0,
        'query_count' => count($queries),
        'total_sql_time_ms' => round($totalQueryTime, 2),
        'slowest_query' => $slowestQuery,
        'writes_count' => count($writes),
        'writes' => $writes,
        'queries' => $queryDetails,
    ];
}

if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    $uriFilter = $argv[1] ?? null;
    $details = getLatestRequestDetails($uriFilter);
    echo json_encode($details, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
}
