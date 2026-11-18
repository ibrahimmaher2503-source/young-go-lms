<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Models\Course;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require_once __DIR__.'/LmsClient.php';
require_once __DIR__.'/telescope_tool.php';

$instructor = User::where('email', 'instructor@younggo.test')->firstOrFail();

function ensureInstructorCourses(User $instructor, int $count): void
{
    $current = Course::whereHas('instructors', fn ($q) => $q->where('users.id', $instructor->id))->count();
    echo "Current courses for instructor: {$current}, target: {$count}\n";
    for ($i = $current + 1; $i <= $count; $i++) {
        $c = Course::create([
            'title' => ['ar' => "دورة مراجعة {$i}", 'en' => "Review Course {$i}"],
            'slug_ar' => "review-course-scale-{$i}",
            'slug_en' => "review-course-scale-{$i}-en",
            'status' => 'published',
            'access_mode' => 'free',
        ]);
        $c->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    }
}

function runMeasurement(string $route, int $count): array
{
    $client = new LmsClient;
    $ok = $client->login('instructor@younggo.test', 'password123');
    if (! $ok) {
        throw new RuntimeException('Instructor login failed');
    }

    $latencies = [];
    $telescopeEntries = [];

    for ($trial = 1; $trial <= 3; $trial++) {
        $res = $client->get($route);
        $latencies[] = $res['time_ms'];
        $t = getLatestRequestDetails($route);
        if ($t) {
            $telescopeEntries[] = $t;
        }
        usleep(100000); // 100ms
    }

    sort($latencies);
    $medianLatency = $latencies[1];
    $lastEntry = end($telescopeEntries);

    return [
        'median_latency_ms' => round($medianLatency, 2),
        'query_count' => $lastEntry['query_count'] ?? 0,
        'total_sql_time_ms' => $lastEntry['total_sql_time_ms'] ?? 0,
        'slowest_query' => $lastEntry['slowest_query'] ?? null,
        'writes_count' => $lastEntry['writes_count'] ?? 0,
        'uuid' => $lastEntry['uuid'] ?? '',
        'telescope_url' => $lastEntry['telescope_url'] ?? '',
    ];
}

echo "=== BENCHMARK: /instructor/submission-review at 5 vs 50 courses ===\n";

// Scale 1: 5 courses
ensureInstructorCourses($instructor, 5);
$res5 = runMeasurement('/instructor/submission-review', 5);
echo "Result at 5 courses:\n";
echo "  Median Latency: {$res5['median_latency_ms']} ms\n";
echo "  Query Count: {$res5['query_count']}\n";
echo "  Total SQL Time: {$res5['total_sql_time_ms']} ms\n";
echo '  Slowest Query: '.($res5['slowest_query']['sql'] ?? 'none').' ('.($res5['slowest_query']['time_ms'] ?? 0)." ms)\n";
echo "  Telescope URL: {$res5['telescope_url']}\n";

// Scale 2: 50 courses
ensureInstructorCourses($instructor, 50);
$res50 = runMeasurement('/instructor/submission-review', 50);
echo "\nResult at 50 courses:\n";
echo "  Median Latency: {$res50['median_latency_ms']} ms\n";
echo "  Query Count: {$res50['query_count']}\n";
echo "  Total SQL Time: {$res50['total_sql_time_ms']} ms\n";
echo '  Slowest Query: '.($res50['slowest_query']['sql'] ?? 'none').' ('.($res50['slowest_query']['time_ms'] ?? 0)." ms)\n";
echo "  Telescope URL: {$res50['telescope_url']}\n";

echo "\n--- Summary ---\n";
echo "Queries: {$res5['query_count']} -> {$res50['query_count']} (Delta: ".($res50['query_count'] - $res5['query_count']).")\n";
echo "SQL Time: {$res5['total_sql_time_ms']} ms -> {$res50['total_sql_time_ms']} ms\n";
echo "Latency: {$res5['median_latency_ms']} ms -> {$res50['median_latency_ms']} ms\n";

// Cleanup: remove created courses
echo "\nCleaning up created courses...\n";
DB::statement('PRAGMA foreign_keys = OFF;');
Course::where('slug_ar', 'like', 'review-course-scale-%')->delete();
DB::statement('PRAGMA foreign_keys = ON;');
echo "Cleanup completed.\n";
