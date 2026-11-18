<?php

declare(strict_types=1);

require __DIR__.'/telescope_tool.php';

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$admin = User::where('email', 'admin@younggo.test')->firstOrFail();
$admin->password = Hash::make('password123');
$admin->save();
echo "Admin password updated to password123.\n";

require_once __DIR__.'/LmsClient.php';

$client = new LmsClient;
$ok = $client->login('admin@younggo.test', 'password123');
echo 'Admin login via LmsClient: '.($ok ? 'SUCCESS' : 'FAIL')."\n";

$routes = [
    '/admin/courses',
    '/admin/audit/audit-logs',
    '/admin/submission-review',
];

foreach ($routes as $route) {
    echo "\nFetching {$route}...\n";
    $res = $client->get($route);
    echo "  Status: {$res['status']} in {$res['time_ms']}ms (length {$res['content_length']})\n";

    $t = getLatestRequestDetails($route);
    if ($t && $t['uri'] === $route) {
        echo "  Telescope UUID: {$t['uuid']}\n";
        echo "  Duration: {$t['duration_ms']}ms | Queries: {$t['query_count']} | SQL Time: {$t['total_sql_time_ms']}ms\n";
        echo '  Slowest Query: '.($t['slowest_query']['sql'] ?? 'none').' ('.($t['slowest_query']['time_ms'] ?? 0)."ms)\n";
        echo "  Writes Count: {$t['writes_count']}\n";
        echo "  Telescope URL: {$t['telescope_url']}\n";
    }
}
