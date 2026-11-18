<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Domain\Audit\AuditLogger;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Identity\TwoFactorSession;
use App\Domain\Progress\CourseProgress;
use App\Domain\Progress\LessonProgress;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

Config::set('security.sessions.enabled', false);
Config::set('identity.two_factor_enforced', false);

/**
 * Reset database to clean 1-course baseline
 */
function resetToBaseline(): void
{
    DB::statement('PRAGMA foreign_keys = OFF;');

    // Remove extra courses (> 1) and their relations
    $extraCourses = Course::where('id', '>', 1)->get();
    foreach ($extraCourses as $c) {
        $sections = CourseSection::where('course_id', $c->id)->get();
        foreach ($sections as $s) {
            $items = CurriculumItem::where('section_id', $s->id)->get();
            foreach ($items as $item) {
                if ($item->itemable_type === 'lesson') {
                    Lesson::where('id', $item->itemable_id)->delete();
                }
                $item->delete();
            }
            $s->delete();
        }
        $c->instructors()->detach();
        $c->delete();
    }

    // Remove extra learners
    $extraLearners = User::where('email', 'like', 'learner_%@younggo.test')->get();
    foreach ($extraLearners as $l) {
        $l->delete();
    }

    // Clear progress and entitlements for non-course-1
    Entitlement::where('course_id', '>', 1)->delete();
    CourseProgress::where('course_id', '>', 1)->delete();
    LessonProgress::whereNotIn('curriculum_item_id', CurriculumItem::where('course_id', 1)->select('id'))->delete();

    // Remove benchmark audit logs
    Activity::where('description', 'like', 'audit.test.event.%')->delete();

    DB::statement('PRAGMA foreign_keys = ON;');

    // Run DemoStudentSeeder to ensure baseline integrity
    Artisan::call('db:seed', ['--class' => 'DemoStudentSeeder']);
}

/**
 * Seed data up to target courses, learners, and audit logs
 */
function seedToCounts(User $instructor, User $student, User $admin, int $targetCourses, int $targetLearners, int $targetAuditLogs): Course
{
    $firstCourse = Course::findOrFail(1);

    // 1. Seed courses
    $currentCourses = Course::count();
    for ($i = $currentCourses + 1; $i <= $targetCourses; $i++) {
        $c = Course::create([
            'title' => ['ar' => "دورة تجريبية رقم {$i}", 'en' => "Demo Course #{$i}"],
            'slug_ar' => "demo-course-{$i}",
            'slug_en' => "demo-course-{$i}-en",
            'status' => 'published',
            'access_mode' => 'free',
            'search_text' => "دورة تجريبية رقم {$i} demo course",
            'level' => 'beginner',
            'content_language' => 'ar',
        ]);
        $c->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);

        $sec = app(ManageSections::class)->add($instructor, $c, ['title' => ['ar' => "قسم {$i}", 'en' => "Section {$i}"]]);
        $sec->update(['published' => true]);

        $les = app(ManageLessons::class)->add($instructor, $sec, [
            'title' => ['ar' => "درس {$i}", 'en' => "Lesson {$i}"],
            'type' => 'text',
            'content' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => "محتوى الدرس {$i}"]]]]],
            'duration_seconds' => 300,
        ]);
        app(SetCurriculumStatus::class)($instructor, $c, [$les->id], 'published');
        app(GrantEntitlement::class)($student, $c, EntitlementSource::Free);
    }

    // 2. Seed learners and enroll into course 1
    $currentLearners = User::role('student')->count();
    for ($j = $currentLearners + 1; $j <= $targetLearners; $j++) {
        $extraLearner = User::firstOrCreate(
            ['email' => "learner_{$j}@younggo.test"],
            ['name' => "طالب تجريبي {$j}", 'password' => bcrypt('password123'), 'status' => 'active']
        );
        if (! $extraLearner->hasRole('student')) {
            $extraLearner->assignRole('student');
        }
        app(GrantEntitlement::class)($extraLearner, $firstCourse, EntitlementSource::Free);
    }

    // 3. Seed audit logs
    $currentLogs = Activity::where('log_name', AuditLogger::LOG_NAME)->count();
    for ($k = $currentLogs + 1; $k <= $targetAuditLogs; $k++) {
        app(AuditLogger::class)->record("audit.test.event.{$k}", $admin, $student->id);
    }

    return $firstCourse;
}

/**
 * Runner for a single page request with 3 trials
 */
function benchmarkRoute(
    Application $app,
    string $name,
    string $url,
    ?User $user,
    int $expectedTotalDbRecords,
    string $displayedDescription
): array {
    $latencies = [];
    $sqlTimes = [];
    $queryCount = 0;
    $duplicateQueries = [];
    $sampleQueries = [];
    $statusCode = 200;
    $contentLength = 0;

    for ($trial = 1; $trial <= 3; $trial++) {
        if ($user !== null) {
            Auth::guard('web')->login($user);
        } else {
            Auth::guard('web')->logout();
        }

        $session = app('session.store');
        $session->start();
        if ($user !== null) {
            $session->put(TwoFactorSession::KEY, (int) $user->id);
            $session->put(Auth::guard('web')->getName(), $user->id);
        }

        $request = Request::create($url, 'GET');
        $request->setLaravelSession($session);
        if ($user !== null) {
            $request->setUserResolver(fn () => $user);
        }
        app()->instance('request', $request);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $startTime = microtime(true);
        $response = $app->handle($request);
        $latencyMs = (microtime(true) - $startTime) * 1000;

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $statusCode = $response->getStatusCode();
        $content = (string) $response->getContent();
        $contentLength = strlen($content);

        $latencies[] = $latencyMs;
        $totalSqlTime = array_sum(array_column($queries, 'time'));
        $sqlTimes[] = $totalSqlTime;

        if ($trial === 1) {
            $queryCount = count($queries);
            $sampleQueries = $queries;

            $counts = [];
            foreach ($queries as $q) {
                $sql = $q['query'];
                $counts[$sql] = ($counts[$sql] ?? 0) + 1;
            }
            $duplicateQueries = array_filter($counts, fn ($c) => $c > 1);
        }
    }

    sort($latencies);
    sort($sqlTimes);
    $medianLatency = $latencies[1];
    $medianSqlTime = $sqlTimes[1];

    return [
        'name' => $name,
        'url' => $url,
        'user' => $user?->email ?? 'Guest',
        'status' => $statusCode,
        'content_length' => $contentLength,
        'median_latency_ms' => round($medianLatency, 2),
        'median_sql_time_ms' => round($medianSqlTime, 2),
        'query_count' => $queryCount,
        'duplicate_count' => count($duplicateQueries),
        'duplicates' => $duplicateQueries,
        'total_db_records' => $expectedTotalDbRecords,
        'displayed_records_description' => $displayedDescription,
        'sample_queries' => $sampleQueries,
    ];
}

// Ensure baseline first
echo ">>> Resetting DB to baseline...\n";
resetToBaseline();

$student = User::where('email', 'student@younggo.test')->firstOrFail();
$instructor = User::where('email', 'instructor@younggo.test')->firstOrFail();
$admin = User::firstOrCreate(
    ['email' => 'admin@younggo.test'],
    ['name' => 'System Admin', 'password' => bcrypt('password123'), 'status' => 'active', 'two_factor_confirmed_at' => now()]
);
if (! $admin->hasRole('super_admin')) {
    $admin->assignRole('super_admin');
}

// -------------------------------------------------------------
// BENCHMARK 1: SMALL DATASET (5 Courses, 5 Learners, 10 Audit Logs)
// -------------------------------------------------------------
echo ">>> Seeding SMALL dataset (5 Courses, 5 Learners, 10 Audit Logs)...\n";
$firstCourse = seedToCounts($instructor, $student, $admin, 5, 5, 10);

$routesToBenchmark = [
    [
        'name' => 'Student Dashboard (/my)',
        'url' => '/my',
        'user' => $student,
        'expected_total' => 5,
        'displayed' => '3 in-progress (limit 3) + 1 resume card',
    ],
    [
        'name' => 'Student My Courses (/my/courses)',
        'url' => '/my/courses',
        'user' => $student,
        'expected_total' => 5,
        'displayed' => '5 (All courses, NO pagination)',
    ],
    [
        'name' => 'Public Catalog (/courses)',
        'url' => '/courses',
        'user' => null,
        'expected_total' => 5,
        'displayed' => '5 (Paginated, 12 per page)',
    ],
    [
        'name' => 'Public Catalog Search (/courses?q=دورة)',
        'url' => '/courses?q=دورة',
        'user' => null,
        'expected_total' => 5,
        'displayed' => '5 matching (Paginated, 12 per page)',
    ],
    [
        'name' => 'Instructor Courses List (/instructor/courses)',
        'url' => '/instructor/courses',
        'user' => $instructor,
        'expected_total' => 5,
        'displayed' => '5 (Filament Table, default 10/25 per page)',
    ],
    [
        'name' => 'Instructor Course Learners (/instructor/courses/1/learners)',
        'url' => "/instructor/courses/{$firstCourse->id}/learners",
        'user' => $instructor,
        'expected_total' => 5,
        'displayed' => '5 (Paginated, 15 per page)',
    ],
    [
        'name' => 'Admin Courses List (/admin/courses)',
        'url' => '/admin/courses',
        'user' => $admin,
        'expected_total' => 5,
        'displayed' => '5 (Filament Table, default 10/25 per page)',
    ],
    [
        'name' => 'Admin Audit Logs (/admin/audit/audit-logs)',
        'url' => '/admin/audit/audit-logs',
        'user' => $admin,
        'expected_total' => 10,
        'displayed' => '10 (Filament Table, default 10/25 per page)',
    ],
];

echo "\n>>> Running 3-trial benchmark for SMALL dataset...\n";
$smallResults = [];
foreach ($routesToBenchmark as $routeDef) {
    echo "  Measuring {$routeDef['name']}...\n";
    $smallResults[$routeDef['name']] = benchmarkRoute(
        $app,
        $routeDef['name'],
        $routeDef['url'],
        $routeDef['user'],
        $routeDef['expected_total'],
        $routeDef['displayed']
    );
}

// -------------------------------------------------------------
// BENCHMARK 2: LARGE DATASET (50 Courses, 50 Learners, 100 Audit Logs)
// -------------------------------------------------------------
echo "\n>>> Seeding LARGE dataset (50 Courses, 50 Learners, 100 Audit Logs)...\n";
$firstCourse = seedToCounts($instructor, $student, $admin, 50, 50, 100);

$routesToBenchmarkLarge = [
    [
        'name' => 'Student Dashboard (/my)',
        'url' => '/my',
        'user' => $student,
        'expected_total' => 50,
        'displayed' => '3 in-progress (limit 3) + 1 resume card',
    ],
    [
        'name' => 'Student My Courses (/my/courses)',
        'url' => '/my/courses',
        'user' => $student,
        'expected_total' => 50,
        'displayed' => '50 (All courses, NO pagination)',
    ],
    [
        'name' => 'Public Catalog (/courses)',
        'url' => '/courses',
        'user' => null,
        'expected_total' => 50,
        'displayed' => '12 (Paginated, 12 per page)',
    ],
    [
        'name' => 'Public Catalog Search (/courses?q=دورة)',
        'url' => '/courses?q=دورة',
        'user' => null,
        'expected_total' => 50,
        'displayed' => '12 matching (Paginated, 12 per page)',
    ],
    [
        'name' => 'Instructor Courses List (/instructor/courses)',
        'url' => '/instructor/courses',
        'user' => $instructor,
        'expected_total' => 50,
        'displayed' => '10 (or 25) (Filament Table paginated)',
    ],
    [
        'name' => 'Instructor Course Learners (/instructor/courses/1/learners)',
        'url' => "/instructor/courses/{$firstCourse->id}/learners",
        'user' => $instructor,
        'expected_total' => 50,
        'displayed' => '15 (Paginated, 15 per page)',
    ],
    [
        'name' => 'Admin Courses List (/admin/courses)',
        'url' => '/admin/courses',
        'user' => $admin,
        'expected_total' => 50,
        'displayed' => '10 (or 25) (Filament Table paginated)',
    ],
    [
        'name' => 'Admin Audit Logs (/admin/audit/audit-logs)',
        'url' => '/admin/audit/audit-logs',
        'user' => $admin,
        'expected_total' => 100,
        'displayed' => '10 (or 25) (Filament Table paginated)',
    ],
];

echo "\n>>> Running 3-trial benchmark for LARGE dataset...\n";
$largeResults = [];
foreach ($routesToBenchmarkLarge as $routeDef) {
    echo "  Measuring {$routeDef['name']}...\n";
    $largeResults[$routeDef['name']] = benchmarkRoute(
        $app,
        $routeDef['name'],
        $routeDef['url'],
        $routeDef['user'],
        $routeDef['expected_total'],
        $routeDef['displayed']
    );
}

// -------------------------------------------------------------
// SUMMARY & COMPARISON
// -------------------------------------------------------------
$benchmarkSummary = [];
echo "\n=========================================================================================================\n";
echo "BENCHMARK RESULTS COMPARISON (Small [5/10] vs Large [50/100])\n";
echo "=========================================================================================================\n";
printf("%-35s | %-12s | %-16s | %-16s | %-14s\n", 'Page / Route', 'Status', 'Queries (S->L)', 'Latency Median', 'Issue Summary');
echo str_repeat('-', 105)."\n";

foreach ($smallResults as $name => $s) {
    $l = $largeResults[$name];
    $qDiff = $l['query_count'] - $s['query_count'];
    $sqlDiff = $l['median_sql_time_ms'] - $s['median_sql_time_ms'];
    $latDiff = $l['median_latency_ms'] - $s['median_latency_ms'];

    $hasNPlus1 = $qDiff > 10;
    $hasDupes = $l['duplicate_count'] > 0;
    $note = $hasNPlus1 ? "N+1 (+{$qDiff} queries)" : ($hasDupes ? "Dupes ({$l['duplicate_count']} patterns)" : 'Stable');

    printf(
        "%-35s | %-12s | %-16s | %-16s | %-14s\n",
        substr($name, 0, 35),
        "{$s['status']} / {$l['status']}",
        "{$s['query_count']} -> {$l['query_count']}",
        "{$s['median_latency_ms']}ms -> {$l['median_latency_ms']}ms",
        $note
    );

    $benchmarkSummary[$name] = [
        'url' => $s['url'],
        'role' => $s['user'],
        'small' => $s,
        'large' => $l,
        'query_growth' => $qDiff,
        'sql_time_growth' => $sqlDiff,
        'latency_growth' => $latDiff,
        'note' => $note,
    ];
}

file_put_contents(__DIR__.'/benchmark_results.json', json_encode($benchmarkSummary, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "\nDetailed benchmark results saved to scripts/benchmark_results.json\n";

// -------------------------------------------------------------
// CLEANUP & RESET TO BASELINE
// -------------------------------------------------------------
echo "\n>>> Resetting DB to baseline demo data...\n";
resetToBaseline();
echo ">>> Reset complete.\n";
