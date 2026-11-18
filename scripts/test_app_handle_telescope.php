<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

$before = DB::table('telescope_entries')->where('type', 'request')->count();
$req = Request::create('/courses', 'GET');
$app->handle($req);
$after = DB::table('telescope_entries')->where('type', 'request')->count();

echo 'Recorded via app->handle: '.($after - $before)."\n";
require __DIR__.'/telescope_tool.php';
$latest = getLatestRequestDetails('/courses');
echo "Latest UUID: {$latest['uuid']} (queries: {$latest['query_count']})\n";
