<?php

declare(strict_types=1);

require __DIR__.'/telescope_tool.php';
require __DIR__.'/LmsClient.php';

use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use Illuminate\Support\Facades\DB;

$results = [];

function captureInteraction(string $journeyName, string $interactionName, string $role, string $method, string $path, callable $fn): array
{
    echo "Executing [{$journeyName}] - {$interactionName} ({$method} {$path})...\n";
    $timeStart = microtime(true);
    $res = $fn();
    $totalTimeMs = round((microtime(true) - $timeStart) * 1000, 2);

    // Fetch Telescope entry for this request
    $t = getLatestRequestDetails($path);
    $entryUuid = $t['uuid'] ?? null;

    $gatesCount = 0;
    $modelsCount = 0;
    $viewsCount = 0;
    $eventsCount = 0;
    $cacheCount = 0;

    if ($entryUuid) {
        $gatesCount = DB::table('telescope_entries')
            ->where('type', 'gate')
            ->where('batch_id', $t['batch_id'] ?? '')
            ->count();

        $modelsCount = DB::table('telescope_entries')
            ->where('type', 'model')
            ->where('batch_id', $t['batch_id'] ?? '')
            ->count();

        $viewsCount = DB::table('telescope_entries')
            ->where('type', 'view')
            ->where('batch_id', $t['batch_id'] ?? '')
            ->count();

        $eventsCount = DB::table('telescope_entries')
            ->where('type', 'event')
            ->where('batch_id', $t['batch_id'] ?? '')
            ->count();

        $cacheCount = DB::table('telescope_entries')
            ->where('type', 'cache')
            ->where('batch_id', $t['batch_id'] ?? '')
            ->count();
    }

    $entry = [
        'journey' => $journeyName,
        'interaction' => $interactionName,
        'role' => $role,
        'method' => $method,
        'path' => $path,
        'http_status' => $res['status'] ?? 0,
        'response_time_ms' => $res['time_ms'] ?? $totalTimeMs,
        'content_length' => $res['content_length'] ?? 0,
        'telescope_uuid' => $entryUuid,
        'telescope_duration_ms' => $t['duration_ms'] ?? null,
        'sql_query_count' => $t['query_count'] ?? 0,
        'total_sql_time_ms' => $t['total_sql_time_ms'] ?? 0,
        'writes_count' => $t['writes_count'] ?? 0,
        'slowest_query' => $t['slowest_query'] ?? null,
        'telescope_url' => $t['telescope_url'] ?? null,
        'gates_checked' => $gatesCount,
        'models_hydrated' => $modelsCount,
        'views_rendered' => $viewsCount,
        'events_fired' => $eventsCount,
        'cache_operations' => $cacheCount,
        'raw_response' => $res,
    ];

    echo "  Status: {$entry['http_status']} in {$entry['response_time_ms']}ms | Queries: {$entry['sql_query_count']} (SQL: {$entry['total_sql_time_ms']}ms, Writes: {$entry['writes_count']}) | Gates: {$gatesCount} | Views: {$viewsCount}\n";
    if ($entry['telescope_url']) {
        echo "  Telescope URL: {$entry['telescope_url']}\n";
    }
    echo "\n";

    return $entry;
}

echo "=================================================================\n";
echo "STARTING COMPREHENSIVE END-TO-END AUDIT ACROSS ALL USER JOURNEYS\n";
echo "=================================================================\n\n";

// ==========================================
// JOURNEY 1: STUDENT AUTH & DASHBOARD
// ==========================================
$studentClient = new LmsClient;
$results[] = captureInteraction('J1: Student Auth & Dashboard', 'Student Login', 'student', 'POST', '/login', function () use ($studentClient) {
    $ok = $studentClient->login('student@younggo.test', 'password123');

    return ['status' => $ok ? 200 : 401, 'time_ms' => 0, 'content_length' => 0];
});

$results[] = captureInteraction('J1: Student Auth & Dashboard', 'Open Learner Dashboard', 'student', 'GET', '/my', function () use ($studentClient) {
    return $studentClient->get('/my');
});

$results[] = captureInteraction('J1: Student Auth & Dashboard', 'Open Enrolled Courses List', 'student', 'GET', '/my/courses', function () use ($studentClient) {
    return $studentClient->get('/my/courses');
});

// ==========================================
// JOURNEY 2: LESSON VIEWER & PROGRESS
// ==========================================
$results[] = captureInteraction('J2: Lesson Player & Progress', 'View Lesson Player', 'student', 'GET', '/learn/1/1', function () use ($studentClient) {
    return $studentClient->get('/learn/1/1');
});

$results[] = captureInteraction('J2: Lesson Player & Progress', 'Progress Heartbeat Ping', 'student', 'POST', '/learn/1/1/progress', function () use ($studentClient) {
    return $studentClient->post('/learn/1/1/progress', ['position' => 30]);
});

$results[] = captureInteraction('J2: Lesson Player & Progress', 'Complete Lesson', 'student', 'POST', '/learn/1/1/complete', function () use ($studentClient) {
    return $studentClient->post('/learn/1/1/complete', []);
});

// ==========================================
// JOURNEY 3: QUIZ JOURNEY (START, SAVE, SUBMIT, RESULT)
// ==========================================
$attemptId = null;
$results[] = captureInteraction('J3: Quiz Flow', 'Start Quiz Attempt', 'student', 'POST', '/learn/1/100/quiz/start', function () use ($studentClient, &$attemptId) {
    $res = $studentClient->post('/learn/1/100/quiz/start', []);
    // The attempt is in DB
    $attempt = QuizAttempt::where('learner_id', 3)->latest()->first();
    if ($attempt) {
        $attemptId = $attempt->id;
    }

    return $res;
});

if ($attemptId) {
    $attemptPath = "/learn/1/100/quiz/{$attemptId}";
    $results[] = captureInteraction('J3: Quiz Flow', 'View Quiz Attempt Form & Timer', 'student', 'GET', $attemptPath, function () use ($studentClient, $attemptPath) {
        return $studentClient->get($attemptPath);
    });

    $q = Question::where('quiz_id', 1)->first();
    $option = $q ? $q->options()->where('is_correct', true)->first() : null;
    $answers = $q && $option ? [(string) $q->id => (int) $option->id] : [];

    $savePath = "/learn/1/100/quiz/{$attemptId}/save";
    $results[] = captureInteraction('J3: Quiz Flow', 'Autosave Answers', 'student', 'POST', $savePath, function () use ($studentClient, $savePath, $answers) {
        return $studentClient->post($savePath, ['answers' => $answers]);
    });

    $submitPath = "/learn/1/100/quiz/{$attemptId}/submit";
    $results[] = captureInteraction('J3: Quiz Flow', 'Submit Quiz Attempt for Grading', 'student', 'POST', $submitPath, function () use ($studentClient, $submitPath, $answers) {
        return $studentClient->post($submitPath, ['answers' => $answers]);
    });

    $results[] = captureInteraction('J3: Quiz Flow', 'View Graded Quiz Result & Score', 'student', 'GET', $attemptPath, function () use ($studentClient, $attemptPath) {
        return $studentClient->get($attemptPath);
    });
}

// ==========================================
// JOURNEY 4: INSTRUCTOR COURSES & LEARNERS
// ==========================================
$instructorClient = new LmsClient;
$results[] = captureInteraction('J4: Instructor Courses & Learners', 'Instructor Login', 'instructor', 'POST', '/login', function () use ($instructorClient) {
    $ok = $instructorClient->login('instructor@younggo.test', 'password123');

    return ['status' => $ok ? 200 : 401, 'time_ms' => 0, 'content_length' => 0];
});

$results[] = captureInteraction('J4: Instructor Courses & Learners', 'View Instructor Courses Table', 'instructor', 'GET', '/instructor/courses', function () use ($instructorClient) {
    return $instructorClient->get('/instructor/courses');
});

$results[] = captureInteraction('J4: Instructor Courses & Learners', 'View Course Learners (Subqueries)', 'instructor', 'GET', '/instructor/courses/1/learners', function () use ($instructorClient) {
    return $instructorClient->get('/instructor/courses/1/learners');
});

// ==========================================
// JOURNEY 5: INSTRUCTOR REVIEW QUEUES & LIBRARY
// ==========================================
$results[] = captureInteraction('J5: Review Queues & Library', 'Submission Review Queue', 'instructor', 'GET', '/instructor/submission-review', function () use ($instructorClient) {
    return $instructorClient->get('/instructor/submission-review');
});

$results[] = captureInteraction('J5: Review Queues & Library', 'Essay Review Queue', 'instructor', 'GET', '/instructor/essay-review', function () use ($instructorClient) {
    return $instructorClient->get('/instructor/essay-review');
});

$results[] = captureInteraction('J5: Review Queues & Library', 'Content Library', 'instructor', 'GET', '/instructor/library', function () use ($instructorClient) {
    return $instructorClient->get('/instructor/library');
});

// ==========================================
// JOURNEY 6: ADMIN 2FA BARRIER & AUDIT LOGS
// ==========================================
$adminClient = new LmsClient;
$results[] = captureInteraction('J6: Admin Flow', 'Admin Login', 'admin', 'POST', '/login', function () use ($adminClient) {
    $ok = $adminClient->login('admin@younggo.test', 'password123');

    return ['status' => $ok ? 200 : 401, 'time_ms' => 0, 'content_length' => 0];
});

$results[] = captureInteraction('J6: Admin Flow', 'Access Admin Courses (2FA Barrier)', 'admin', 'GET', '/admin/courses', function () use ($adminClient) {
    return $adminClient->get('/admin/courses');
});

$results[] = captureInteraction('J6: Admin Flow', 'Access Audit Logs (2FA Barrier)', 'admin', 'GET', '/admin/audit/audit-logs', function () use ($adminClient) {
    return $adminClient->get('/admin/audit/audit-logs');
});

file_put_contents(__DIR__.'/all_journeys_audit_results.json', json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "\nAudit complete! Results saved to scripts/all_journeys_audit_results.json\n";
