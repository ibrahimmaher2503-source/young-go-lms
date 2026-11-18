<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

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

Artisan::call('db:seed', ['--class' => 'DemoStudentSeeder']);

$instructor = User::where('email', 'instructor@younggo.test')->firstOrFail();

function createCoursesForInstructor(User $instructor, int $targetCount): void
{
    $current = Course::count();
    for ($i = $current + 1; $i <= $targetCount; $i++) {
        $c = Course::create([
            'title' => ['ar' => "دورة مراجعة {$i}", 'en' => "Review Course {$i}"],
            'slug_ar' => "review-course-{$i}",
            'slug_en' => "review-course-{$i}-en",
            'status' => 'published',
            'access_mode' => 'free',
        ]);
        $c->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    }
}

function measureSubmissionReview(Application $app, User $instructor): array
{
    $latencies = [];
    $sqlTimes = [];
    $queryCount = 0;
    $queriesSample = [];

    for ($trial = 1; $trial <= 3; $trial++) {
        Auth::guard('web')->login($instructor);
        $session = app('session.store');
        $session->start();
        $session->put(TwoFactorSession::KEY, (int) $instructor->id);
        $session->put(Auth::guard('web')->getName(), $instructor->id);

        $request = Request::create('/instructor/submission-review', 'GET');
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
        $sqlTimes[] = array_sum(array_column($queries, 'time'));

        if ($trial === 1) {
            $queryCount = count($queries);
            $queriesSample = $queries;
        }
    }

    sort($latencies);
    sort($sqlTimes);

    return [
        'median_latency_ms' => round($latencies[1], 2),
        'median_sql_time_ms' => round($sqlTimes[1], 2),
        'query_count' => $queryCount,
        'queries' => $queriesSample,
    ];
}

echo "Testing Submission Review at Scale 1: 5 courses...\n";
createCoursesForInstructor($instructor, 5);
$res5 = measureSubmissionReview($app, $instructor);
echo "  5 Courses: {$res5['query_count']} queries, SQL time: {$res5['median_sql_time_ms']}ms, Latency: {$res5['median_latency_ms']}ms\n";

echo "Testing Submission Review at Scale 2: 50 courses...\n";
createCoursesForInstructor($instructor, 50);
$res50 = measureSubmissionReview($app, $instructor);
echo "  50 Courses: {$res50['query_count']} queries, SQL time: {$res50['median_sql_time_ms']}ms, Latency: {$res50['median_latency_ms']}ms\n";

echo "\nDifference: ".($res50['query_count'] - $res5['query_count'])." additional queries!\n";

// Cleanup extra courses
DB::statement('PRAGMA foreign_keys = OFF;');
Course::where('id', '>', 1)->delete();
DB::statement('PRAGMA foreign_keys = ON;');
Artisan::call('db:seed', ['--class' => 'DemoStudentSeeder']);
