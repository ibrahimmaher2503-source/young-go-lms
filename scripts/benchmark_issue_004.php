<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Identity\TwoFactorSession;
use App\Models\Course;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;

Config::set('security.sessions.enabled', false);
Config::set('identity.two_factor_enforced', false);

// Baseline seeder
Artisan::call('db:seed', ['--class' => 'DemoStudentSeeder']);

$course = Course::findOrFail(1);
$instructor = User::where('email', 'instructor@younggo.test')->firstOrFail();
Auth::guard('web')->login($instructor);

function setupLearners(Course $course, int $targetCount): void
{
    $currentCount = User::role('student')->count();
    for ($i = $currentCount + 1; $i <= $targetCount; $i++) {
        $u = User::firstOrCreate(
            ['email' => "learner_{$i}@younggo.test"],
            ['name' => "Student {$i} ".str_pad((string) $i, 4, '0', STR_PAD_LEFT), 'password' => bcrypt('password123'), 'status' => 'active']
        );
        if (! $u->hasRole('student')) {
            $u->assignRole('student');
        }
        // Create an entitlement
        app(GrantEntitlement::class)($u, $course, EntitlementSource::Free);
    }
}

function run3Trials(Application $app, User $instructor, Course $course): array
{
    $latencies = [];
    $sqlTimes = [];
    $subqueryTimes = [];
    $slowestQueries = [];
    $totalQueryCount = 0;

    for ($trial = 1; $trial <= 3; $trial++) {
        Auth::guard('web')->login($instructor);
        $session = app('session.store');
        $session->start();
        $session->put(TwoFactorSession::KEY, (int) $instructor->id);
        $session->put(Auth::guard('web')->getName(), $instructor->id);

        $request = Request::create("/instructor/courses/{$course->id}/learners", 'GET');
        $request->setLaravelSession($session);
        $request->setUserResolver(fn () => $instructor);
        app()->instance('request', $request);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $start = microtime(true);
        $response = $app->handle($request);
        $latency = (microtime(true) - $start) * 1000;

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $latencies[] = $latency;
        $totalSql = array_sum(array_column($queries, 'time'));
        $sqlTimes[] = $totalSql;

        // Find the correlated subquery time
        $subqueryTime = 0.0;
        $slowest = null;
        $maxT = 0.0;
        foreach ($queries as $q) {
            if ($q['time'] > $maxT) {
                $maxT = $q['time'];
                $slowest = $q;
            }
            if (str_contains($q['query'], 'access_status')) {
                $subqueryTime = $q['time'];
            }
        }
        $subqueryTimes[] = $subqueryTime;
        $slowestQueries[] = $slowest;

        if ($trial === 1) {
            $totalQueryCount = count($queries);
        }
    }

    sort($latencies);
    sort($sqlTimes);
    sort($subqueryTimes);

    return [
        'median_latency_ms' => round($latencies[1], 2),
        'median_sql_time_ms' => round($sqlTimes[1], 2),
        'median_subquery_time_ms' => round($subqueryTimes[1], 2),
        'query_count' => $totalQueryCount,
        'slowest_query' => $slowestQueries[1],
    ];
}

$results = [];

// 1. SMALL: 5 learners
echo "Testing Scale 1: 5 learners...\n";
setupLearners($course, 5);
$results['5_learners'] = run3Trials($app, $instructor, $course);
$results['5_learners']['total_db_records'] = User::role('student')->count();
$results['5_learners']['displayed_records'] = 5;

// 2. MEDIUM / LARGE: 50 learners
echo "Testing Scale 2: 50 learners...\n";
setupLearners($course, 50);
$results['50_learners'] = run3Trials($app, $instructor, $course);
$results['50_learners']['total_db_records'] = User::role('student')->count();
$results['50_learners']['displayed_records'] = 15;

// 3. STRESSED SCALE: 200 learners
echo "Testing Scale 3: 200 learners...\n";
setupLearners($course, 200);
$results['200_learners'] = run3Trials($app, $instructor, $course);
$results['200_learners']['total_db_records'] = User::role('student')->count();
$results['200_learners']['displayed_records'] = 15;

echo "\n=========================================================================================\n";
echo "ISSUE-004 DEEP DIVE: /instructor/courses/{id}/learners (5 vs 50 vs 200 learners)\n";
echo "=========================================================================================\n";
printf("%-15s | %-12s | %-12s | %-15s | %-15s | %-15s\n", 'Scale', 'Total In DB', 'Displayed', 'Total Queries', 'Subquery Time', 'Total SQL Time');
echo str_repeat('-', 90)."\n";

foreach ($results as $k => $r) {
    printf(
        "%-15s | %-12s | %-12s | %-15s | %-15s | %-15s\n",
        $k,
        $r['total_db_records'],
        $r['displayed_records'],
        $r['query_count'],
        "{$r['median_subquery_time_ms']} ms",
        "{$r['median_sql_time_ms']} ms"
    );
}

file_put_contents(__DIR__.'/issue_004_results.json', json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "\nSaved results to scripts/issue_004_results.json\n";

// Cleanup extra learners
echo "Cleaning up extra learners...\n";
DB::statement('PRAGMA foreign_keys = OFF;');
User::where('email', 'like', 'learner_%@younggo.test')->delete();
Entitlement::where('course_id', 1)->where('learner_id', '!=', 3)->delete();
DB::statement('PRAGMA foreign_keys = ON;');
Artisan::call('db:seed', ['--class' => 'DemoStudentSeeder']);
echo "Cleanup complete.\n";
