<?php

declare(strict_types=1);

require __DIR__.'/telescope_tool.php';

use Illuminate\Support\Facades\DB;

echo "=== TELESCOPE DEEP INSPECTION ACROSS ALL WATCHER GROUPS ===\n\n";

// 1. GATES
echo "1. GATES SUMMARY:\n";
$gates = DB::table('telescope_entries')->where('type', 'gate')->get();
$abilities = [];
foreach ($gates as $g) {
    $c = json_decode($g->content, true);
    $ab = $c['ability'] ?? 'unknown';
    $res = $c['result'] ?? 'unknown';
    $key = "{$ab} ({$res})";
    $abilities[$key] = ($abilities[$key] ?? 0) + 1;
}
arsort($abilities);
echo '  Total Gate Checks: '.count($gates)."\n";
echo "  Top Gate Checks:\n";
foreach (array_slice($abilities, 0, 10, true) as $k => $cnt) {
    echo "    - {$k}: {$cnt} times\n";
}

// 2. VIEWS
echo "\n2. VIEWS SUMMARY:\n";
$views = DB::table('telescope_entries')->where('type', 'view')->get();
$viewNames = [];
foreach ($views as $v) {
    $c = json_decode($v->content, true);
    $name = $c['name'] ?? 'unknown';
    $viewNames[$name] = ($viewNames[$name] ?? 0) + 1;
}
arsort($viewNames);
echo '  Total View Render Events: '.count($views)."\n";
echo "  Top Rendered Views:\n";
foreach (array_slice($viewNames, 0, 10, true) as $k => $cnt) {
    echo "    - {$k}: {$cnt} times\n";
}

// 3. MODELS
echo "\n3. MODELS SUMMARY:\n";
$models = DB::table('telescope_entries')->where('type', 'model')->get();
$modelActions = [];
foreach ($models as $m) {
    $c = json_decode($m->content, true);
    $action = $c['action'] ?? 'unknown';
    $model = class_basename($c['model'] ?? 'unknown');
    $key = "{$model}::{$action}";
    $modelActions[$key] = ($modelActions[$key] ?? 0) + 1;
}
arsort($modelActions);
echo '  Total Model Events: '.count($models)."\n";
foreach (array_slice($modelActions, 0, 10, true) as $k => $cnt) {
    echo "    - {$k}: {$cnt} times\n";
}

// 4. CACHE
echo "\n4. CACHE SUMMARY:\n";
$cache = DB::table('telescope_entries')->where('type', 'cache')->get();
$cacheTypes = [];
$cacheKeys = [];
foreach ($cache as $ca) {
    $c = json_decode($ca->content, true);
    $type = $c['type'] ?? 'unknown';
    $key = $c['key'] ?? 'unknown';
    $cacheTypes[$type] = ($cacheTypes[$type] ?? 0) + 1;
    $cacheKeys[$key] = ($cacheKeys[$key] ?? 0) + 1;
}
echo '  Total Cache Events: '.count($cache)."\n";
foreach ($cacheTypes as $t => $cnt) {
    echo "    - Type '{$t}': {$cnt}\n";
}
echo "  Top Cache Keys:\n";
foreach (array_slice($cacheKeys, 0, 5, true) as $k => $cnt) {
    echo "    - {$k}: {$cnt} times\n";
}

// 5. EVENTS
echo "\n5. EVENTS SUMMARY:\n";
$events = DB::table('telescope_entries')->where('type', 'event')->get();
$eventNames = [];
foreach ($events as $e) {
    $c = json_decode($e->content, true);
    $name = $c['name'] ?? 'unknown';
    $eventNames[$name] = ($eventNames[$name] ?? 0) + 1;
}
arsort($eventNames);
echo '  Total Event Entries: '.count($events)."\n";
foreach ($eventNames as $k => $cnt) {
    echo "    - {$k}: {$cnt} times\n";
}

// 6. JOBS & BATCHES
echo "\n6. JOBS & BATCHES SUMMARY:\n";
$jobs = DB::table('telescope_entries')->where('type', 'job')->count();
$batches = DB::table('telescope_entries')->where('type', 'batch')->count();
echo "  Job Entries: {$jobs}\n";
echo "  Batch Entries: {$batches}\n";

// 7. EXCEPTIONS & LOGS
echo "\n7. EXCEPTIONS & LOGS SUMMARY:\n";
$exceptions = DB::table('telescope_entries')->where('type', 'exception')->get();
echo '  Exception Entries: '.count($exceptions)."\n";
foreach ($exceptions as $ex) {
    $c = json_decode($ex->content, true);
    echo '    - '.($c['class'] ?? 'Exception').': '.($c['message'] ?? '').' at '.($c['file'] ?? '').':'.($c['line'] ?? '')."\n";
}

$logs = DB::table('telescope_entries')->where('type', 'log')->get();
echo '  Log Entries (level >= error): '.count($logs)."\n";
foreach ($logs as $l) {
    $c = json_decode($l->content, true);
    echo '    - ['.($c['level'] ?? 'info').'] '.($c['message'] ?? '')."\n";
    if (isset($c['context'])) {
        echo '      Context: '.json_encode(array_slice($c['context'], 0, 3))."\n";
    }
}

// 8. MAIL & NOTIFICATIONS & CLIENT REQUESTS
echo "\n8. MAIL / NOTIFICATIONS / HTTP CLIENT:\n";
$mail = DB::table('telescope_entries')->where('type', 'mail')->count();
$notifications = DB::table('telescope_entries')->where('type', 'notification')->count();
$clientReqs = DB::table('telescope_entries')->where('type', 'client_request')->count();
echo "  Mail Entries: {$mail}\n";
echo "  Notification Entries: {$notifications}\n";
echo "  Client Request (External HTTP) Entries: {$clientReqs}\n";
