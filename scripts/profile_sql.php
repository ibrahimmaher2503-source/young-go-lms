<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Http\Controllers\LearnController;
use App\Http\Controllers\LearnerHomeController;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

// 1. Reset baseline demo data
Artisan::call('db:seed', ['--class' => 'DemoStudentSeeder']);

$student = User::where('email', 'student@younggo.test')->firstOrFail();
Auth::guard('web')->login($student);

echo "======================================================\n";
echo "1. SQL PROFILING: Student Dashboard (/my) [1 course]\n";
echo "======================================================\n";

DB::flushQueryLog();
DB::enableQueryLog();

$request = Request::create('/my', 'GET');
$request->setUserResolver(fn () => $student);
app()->instance('request', $request);

$startTime = microtime(true);
$view = app(LearnerHomeController::class)->dashboard($request);
$renderedHtml = $view->render();
$durationMs = (microtime(true) - $startTime) * 1000;

$queries = DB::getQueryLog();
DB::disableQueryLog();

$totalSqlTime = array_sum(array_column($queries, 'time'));
echo 'Total Queries: '.count($queries)."\n";
echo 'Total SQL Time: '.round($totalSqlTime, 2)." ms\n";
echo 'Total Controller & View Render Time: '.round($durationMs, 2)." ms\n";

// Count duplicate queries (by raw SQL pattern)
$queryCounts = [];
foreach ($queries as $q) {
    $sql = $q['query'];
    $queryCounts[$sql] = ($queryCounts[$sql] ?? 0) + 1;
}
$duplicates = array_filter($queryCounts, fn ($c) => $c > 1);
echo 'Duplicate Queries Count: '.count($duplicates)."\n";
if (count($duplicates) > 0) {
    foreach ($duplicates as $sql => $count) {
        echo "  - [{$count}x]: {$sql}\n";
    }
}
echo "\nDetailed Queries List for /my (1 Course):\n";
foreach ($queries as $i => $q) {
    echo '  '.($i + 1).'. ['.$q['time'].' ms] '.$q['query'].' (bindings: '.json_encode($q['bindings']).")\n";
}

echo "\n======================================================\n";
echo "2. SQL PROFILING: Course Player (/learn/{course}/{item})\n";
echo "======================================================\n";

$course = Course::firstOrFail();
$item = CurriculumItem::where('course_id', $course->id)->firstOrFail();

DB::flushQueryLog();
DB::enableQueryLog();

$learnRequest = Request::create("/learn/{$course->id}/{$item->id}", 'GET');
$learnRequest->setUserResolver(fn () => $student);
app()->instance('request', $learnRequest);

$startTime = microtime(true);
$learnView = app(LearnController::class)->show($learnRequest, $course, $item);
$learnHtml = $learnView->render();
$learnDurationMs = (microtime(true) - $startTime) * 1000;

$learnQueries = DB::getQueryLog();
DB::disableQueryLog();

$totalLearnSqlTime = array_sum(array_column($learnQueries, 'time'));
echo 'Total Queries: '.count($learnQueries)."\n";
echo 'Total SQL Time: '.round($totalLearnSqlTime, 2)." ms\n";
echo 'Total Controller & View Render Time: '.round($learnDurationMs, 2)." ms\n";

$learnQueryCounts = [];
foreach ($learnQueries as $q) {
    $sql = $q['query'];
    $learnQueryCounts[$sql] = ($learnQueryCounts[$sql] ?? 0) + 1;
}
$learnDuplicates = array_filter($learnQueryCounts, fn ($c) => $c > 1);
echo 'Duplicate Queries Count: '.count($learnDuplicates)."\n";
if (count($learnDuplicates) > 0) {
    foreach ($learnDuplicates as $sql => $count) {
        echo "  - [{$count}x]: {$sql}\n";
    }
}
echo "\nDetailed Queries List for /learn:\n";
foreach ($learnQueries as $i => $q) {
    echo '  '.($i + 1).'. ['.$q['time'].' ms] '.$q['query']."\n";
}

echo "\n======================================================\n";
echo "3. N+1 ANALYSIS: Comparing /my with 5 Courses vs 50 Courses\n";
echo "======================================================\n";

$instructor = User::where('email', 'instructor@younggo.test')->firstOrFail();

// Helper to create N courses and enroll student
function createAndEnrollCourses(User $instructor, User $student, int $targetCount): void
{
    $currentCount = Course::count();
    for ($i = $currentCount + 1; $i <= $targetCount; $i++) {
        $c = Course::create([
            'title' => ['ar' => "دورة رقم {$i}", 'en' => "Course #{$i}"],
            'slug_ar' => "course-{$i}",
            'slug_en' => "course-{$i}-en",
            'status' => 'published',
            'access_mode' => 'free',
        ]);
        $c->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);

        $sec = app(ManageSections::class)->add($instructor, $c, ['title' => ['ar' => "قسم {$i}", 'en' => "Section {$i}"]]);
        $sec->update(['published' => true]);

        $les = app(ManageLessons::class)->add($instructor, $sec, [
            'title' => ['ar' => "درس {$i}", 'en' => "Lesson {$i}"],
            'type' => 'text',
            'content' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => "محتوى {$i}"]]]]],
            'duration_seconds' => 300,
        ]);
        app(SetCurriculumStatus::class)($instructor, $c, [$les->id], 'published');
        app(GrantEntitlement::class)($student, $c, EntitlementSource::Free);
    }
}

// Ensure 5 courses
createAndEnrollCourses($instructor, $student, 5);
echo "Created and enrolled in 5 courses.\n";

DB::flushQueryLog();
DB::enableQueryLog();
$start5 = microtime(true);
$view5 = app(LearnerHomeController::class)->dashboard($request);
$view5->render();
$time5 = (microtime(true) - $start5) * 1000;
$queries5 = DB::getQueryLog();
DB::disableQueryLog();
$sqlTime5 = array_sum(array_column($queries5, 'time'));

echo '-> 5 Courses: '.count($queries5).' queries, SQL time: '.round($sqlTime5, 2).' ms, total time: '.round($time5, 2)." ms\n";

// Ensure 50 courses
createAndEnrollCourses($instructor, $student, 50);
echo "Created and enrolled in 50 courses.\n";

DB::flushQueryLog();
DB::enableQueryLog();
$start50 = microtime(true);
$view50 = app(LearnerHomeController::class)->dashboard($request);
$view50->render();
$time50 = (microtime(true) - $start50) * 1000;
$queries50 = DB::getQueryLog();
DB::disableQueryLog();
$sqlTime50 = array_sum(array_column($queries50, 'time'));

echo '-> 50 Courses: '.count($queries50).' queries, SQL time: '.round($sqlTime50, 2).' ms, total time: '.round($time50, 2)." ms\n";

echo "\nSummary of N+1 Query scaling:\n";
echo '  1 Course:  '.count($queries)." queries\n";
echo '  5 Courses: '.count($queries5)." queries\n";
echo '  50 Courses: '.count($queries50)." queries\n";
$diffPerCourse = (count($queries50) - count($queries5)) / (50 - 5);
echo '  Growth Rate: approximately '.round($diffPerCourse, 2)." queries per additional course!\n";

// Reset back to clean 1 course demo state
Artisan::call('db:seed', ['--class' => 'DemoStudentSeeder']);
