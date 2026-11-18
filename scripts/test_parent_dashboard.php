<?php

declare(strict_types=1);

require __DIR__.'/telescope_tool.php';

use App\Domain\Identity\Models\GuardianChildLink;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

$student = User::where('email', 'student@younggo.test')->firstOrFail();

$parent = User::firstOrCreate(['email' => 'parent@younggo.test'], [
    'name' => 'ولي أمر تجريبي',
    'status' => 'active',
    'email_verified_at' => now(),
    'password' => Hash::make('password123'),
]);

$parent->syncRoles(['parent']);
$parent->givePermissionTo('guardian.child.manage');

$link = GuardianChildLink::firstOrCreate([
    'guardian_id' => $parent->id,
    'child_id' => $student->id,
], [
    'status' => GuardianChildLink::STATUS_VERIFIED,
    'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT,
    'verified_at' => now(),
]);

echo "Parent user ready: ID {$parent->id}, Email {$parent->email}\n";
echo "GuardianChildLink ready: ID {$link->id}, status {$link->status}\n";

require_once __DIR__.'/LmsClient.php';

$client = new LmsClient;
$ok = $client->login('parent@younggo.test', 'password123');
echo 'Parent login: '.($ok ? 'SUCCESS' : 'FAIL')."\n";

$res = $client->get('/parent');
echo "GET /parent: Status {$res['status']} in {$res['time_ms']}ms (length {$res['content_length']})\n";

$t = getLatestRequestDetails('/parent');
if ($t && $t['uri'] === '/parent') {
    echo "Telescope UUID: {$t['uuid']}\n";
    echo "Duration: {$t['duration_ms']}ms | Queries: {$t['query_count']} | SQL Time: {$t['total_sql_time_ms']}ms\n";
    echo 'Slowest Query: '.($t['slowest_query']['sql'] ?? 'none').' ('.($t['slowest_query']['time_ms'] ?? 0)."ms)\n";
    echo "Writes Count: {$t['writes_count']}\n";
    echo "Telescope URL: {$t['telescope_url']}\n";
} else {
    echo "No matching Telescope entry found.\n";
}
