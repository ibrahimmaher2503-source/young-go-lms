<?php

// T-P7-06 | AI spec §11, §14, §18 | A1 A2 A3

declare(strict_types=1);

use App\Domain\AI\Assistant\AssistantMode;
use App\Domain\AI\Assistant\AssistantOutcome;
use App\Domain\AI\Assistant\StudentHash;
use App\Domain\AI\Assistant\StyleTier;
use App\Domain\AI\Console\Actions\AskInTestConsole;
use App\Domain\AI\Guards\GuardKind;
use App\Domain\AI\Queries\LessonInsights;
use App\Domain\AI\Queries\UsageReport;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Contracts\LessonAccessChecker;
use App\Filament\Pages\AssistantInsights;
use App\Filament\Pages\AssistantUsageReport;
use App\Models\AiConversation;
use App\Models\AiFeedback;
use App\Models\AiMessage;
use App\Models\AiUsage;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\LessonDocumentPage;
use App\Models\LessonKeyTerm;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
});

/**
 * @return array{0: User, 1: Course, 2: Lesson, 3: LessonCurriculumDocument}
 */
function tp706InsightsReadyLesson(?User $instructor = null): array
{
    $instructor ??= User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');

    $course = Course::query()->create([
        'title' => ['en' => 'Geometry Course', 'ar' => 'دورة الهندسة'],
        'slug_ar' => 'geom-ar-'.uniqid(),
        'slug_en' => 'geom-en-'.uniqid(),
        'status' => 'published',
        'access_mode' => 'free',
        'ai_enabled' => true,
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);

    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Unit 1']]);
    $section->update(['published' => true]);

    $item = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Triangles and Angles'], 'type' => 'video']);
    $item->update(['ai_enabled' => true]);
    app(SetCurriculumStatus::class)($instructor, $course, [$item->id], 'published');

    /** @var Lesson $lesson */
    $lesson = $item->itemable;
    $lesson->forceFill(['type' => 'video'])->save();

    $doc = LessonCurriculumDocument::query()->create([
        'lesson_id' => $lesson->id,
        'version' => 1,
        'path' => 'curriculum/geom.pdf',
        'sha256' => str_repeat('c', 64),
        'size' => 2048,
        'pages' => 3,
        'status' => LessonCurriculumDocument::STATUS_READY,
        'active' => true,
    ]);

    LessonDocumentPage::query()->create([
        'document_id' => $doc->id,
        'page_no' => 1,
        'raw_text' => 'Page 1 raw',
        'approved_text' => 'Right triangles follow the Pythagorean theorem.',
        'ocr_used' => false,
    ]);
    LessonDocumentPage::query()->create([
        'document_id' => $doc->id,
        'page_no' => 2,
        'raw_text' => 'Page 2 raw',
        'approved_text' => 'Equilateral triangles have equal angles of sixty degrees.',
        'ocr_used' => false,
    ]);
    LessonDocumentPage::query()->create([
        'document_id' => $doc->id,
        'page_no' => 3,
        'raw_text' => 'Page 3 raw',
        'approved_text' => 'Isosceles triangles possess two congruent sides.',
        'ocr_used' => false,
    ]);

    app()->bind(LessonAccessChecker::class, fn () => new class implements LessonAccessChecker
    {
        public function canAccessLessonFiles(User $user, Lesson $lesson): bool
        {
            return true;
        }
    });

    return [$instructor, $course, $lesson->refresh(), $doc];
}

it('ensures usage report contains no message bodies, PII, student ids, or hashes, and queries only ai_usage', function (): void {
    [$instructor, $course, $lesson] = tp706InsightsReadyLesson();

    $student = User::factory()->create([
        'id' => 88888,
        'email' => 'secret-student-test@example.test',
        'name' => 'Secret Student',
    ]);

    $markerQuestion = 'ZZ-SECRET-QUESTION-ANONYMITY-CHECK-9999';
    $markerAnswer = 'ZZ-SECRET-ANSWER-HIDDEN-8888';

    $conversation = AiConversation::query()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'lesson_id' => $lesson->id,
        'document_version' => 1,
        'locale' => 'en',
        'style_tier' => 'adults',
        'status' => 'open',
    ]);

    AiMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => AiMessage::ROLE_USER,
        'content' => $markerQuestion,
    ]);

    AiMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => AiMessage::ROLE_ASSISTANT,
        'content' => $markerAnswer,
        'status' => 'answered',
    ]);

    $results = array_merge(
        UsageReport::OUTCOMES,
        array_map(static fn (GuardKind $k): string => $k->value, GuardKind::cases()),
        ['provider_error', 'timeout', 'schema', 'citation', 'length', 'leakage']
    );

    $hash = StudentHash::of((int) $student->id);
    foreach ($results as $result) {
        AiUsage::query()->create([
            'student_hash' => $hash,
            'lesson_id' => $lesson->id,
            'provider' => 'fake',
            'model' => 'fake-model',
            'input_tokens' => 50,
            'cached_tokens' => 10,
            'output_tokens' => 20,
            'cost_minor' => 15,
            'latency_ms' => 350,
            'result' => $result,
            'created_at' => now(),
        ]);
    }

    $touchedForbiddenTables = [];
    DB::listen(function ($query) use (&$touchedForbiddenTables) {
        $sql = strtolower($query->sql);
        foreach (['ai_messages', 'ai_conversations', 'users'] as $forbidden) {
            if (str_contains($sql, $forbidden)) {
                $touchedForbiddenTables[] = $forbidden;
            }
        }
    });

    $from = now()->subDays(7);
    $to = now();
    $report = app(UsageReport::class)->for($from, $to);

    // Verify DB queries did NOT touch ai_messages, ai_conversations, or users
    expect($touchedForbiddenTables)->toBeEmpty();

    $json = json_encode($report, JSON_THROW_ON_ERROR);

    // Assert JSON does NOT contain markers, emails, student IDs, hashes, or message bodies
    expect($json)->not->toContain($markerQuestion)
        ->not->toContain($markerAnswer)
        ->not->toContain('secret-student-test@example.test')
        ->not->toContain('88888')
        ->not->toContain($hash);

    // Render AssistantUsageReport HTML as admin
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test(AssistantUsageReport::class)
        ->assertSeeHtml('data-assistant-usage')
        ->assertSeeHtml('data-usage-totals')
        ->assertDontSee($markerQuestion)
        ->assertDontSee($markerAnswer)
        ->assertDontSee('secret-student-test@example.test')
        ->assertDontSee((string) $student->id)
        ->assertDontSee($hash);
})->group('T-P7-06', 'A2');

it('computes accurate totals, metrics, latencies and groupings in UsageReport', function (): void {
    [$instructor, $course, $lesson] = tp706InsightsReadyLesson();

    // 2 answered, 1 not_in_source, 1 cache_hit, 1 moderation (guard block), 1 provider_error (error), 1 timeout (error)
    $rows = [
        ['result' => 'answered', 'latency' => 200, 'cost' => 100, 'in' => 50, 'cached' => 10, 'out' => 25],
        ['result' => 'answered', 'latency' => 400, 'cost' => 120, 'in' => 60, 'cached' => 10, 'out' => 30],
        ['result' => 'not_in_source', 'latency' => 300, 'cost' => 80, 'in' => 40, 'cached' => 0, 'out' => 15],
        ['result' => 'cache_hit', 'latency' => 0, 'cost' => 0, 'in' => 0, 'cached' => 0, 'out' => 0],
        ['result' => 'moderation', 'latency' => 0, 'cost' => 0, 'in' => 0, 'cached' => 0, 'out' => 0],
        ['result' => 'provider_error', 'latency' => 100, 'cost' => 50, 'in' => 20, 'cached' => 0, 'out' => 0],
        ['result' => 'timeout', 'latency' => 16000, 'cost' => 50, 'in' => 20, 'cached' => 0, 'out' => 0],
    ];

    foreach ($rows as $row) {
        AiUsage::query()->create([
            'student_hash' => str_repeat('d', 64),
            'lesson_id' => $lesson->id,
            'provider' => 'fake',
            'model' => 'test-model',
            'input_tokens' => $row['in'],
            'cached_tokens' => $row['cached'],
            'output_tokens' => $row['out'],
            'cost_minor' => $row['cost'],
            'latency_ms' => $row['latency'],
            'result' => $row['result'],
            'created_at' => now(),
        ]);
    }

    $report = app(UsageReport::class)->for(now()->subDays(1), now());
    $totals = $report['totals'];

    expect($totals['requests'])->toBe(7)
        ->and($totals['answered'])->toBe(2)
        ->and($totals['not_in_source'])->toBe(1)
        ->and($totals['cache_hits'])->toBe(1)
        ->and($totals['guard_blocks'])->toBe(1)
        ->and($totals['errors'])->toBe(2) // provider_error + timeout
        ->and($totals['error_rate'])->toBe(28.6) // round(2 * 100 / 7, 1) = 28.6
        ->and($totals['cost_minor'])->toBe(400) // 100+120+80+0+0+50+50 = 400
        ->and($totals['input_tokens'])->toBe(190)
        ->and($totals['cached_tokens'])->toBe(20)
        ->and($totals['output_tokens'])->toBe(70);

    // Latency over rows with latency_ms > 0: [100, 200, 300, 400, 16000] (5 rows)
    // sum = 17000 / 5 = 3400
    // p95 index = ceil(5 * 0.95) - 1 = 5 - 1 = 4 -> 16000
    expect($totals['avg_latency_ms'])->toBe(3400)
        ->and($totals['p95_latency_ms'])->toBe(16000);

    // Groupings
    expect($report['by_day'])->toHaveCount(1)
        ->and($report['by_day'][0]['requests'])->toBe(7)
        ->and($report['by_day'][0]['errors'])->toBe(2)
        ->and($report['by_model'])->toHaveCount(1)
        ->and($report['by_model'][0]['provider'])->toBe('fake')
        ->and($report['by_model'][0]['model'])->toBe('test-model')
        ->and($report['by_lesson'])->toHaveCount(1)
        ->and($report['by_lesson'][0]['requests'])->toBe(7)
        ->and($report['by_lesson'][0]['lesson'])->toContain('Geometry Course')
        ->and($report['by_lesson'][0]['lesson'])->toContain('Triangles and Angles');
})->group('T-P7-06', 'A2');

it('handles date ranges correctly, swaps reversed ranges, caps at 366 days, and shows empty state', function (): void {
    [$instructor, $course, $lesson] = tp706InsightsReadyLesson();

    // Row inside range
    $u1 = AiUsage::query()->create([
        'student_hash' => str_repeat('e', 64),
        'lesson_id' => $lesson->id,
        'provider' => 'fake',
        'input_tokens' => 10,
        'cached_tokens' => 0,
        'output_tokens' => 10,
        'cost_minor' => 10,
        'latency_ms' => 200,
        'result' => 'answered',
    ]);
    $u1->forceFill(['created_at' => CarbonImmutable::now()->subDays(5)])->save();

    // Row outside range (15 days ago)
    $u2 = AiUsage::query()->create([
        'student_hash' => str_repeat('e', 64),
        'lesson_id' => $lesson->id,
        'provider' => 'fake',
        'input_tokens' => 10,
        'cached_tokens' => 0,
        'output_tokens' => 10,
        'cost_minor' => 10,
        'latency_ms' => 200,
        'result' => 'answered',
    ]);
    $u2->forceFill(['created_at' => CarbonImmutable::now()->subDays(15)])->save();

    // 1. Report from 7 days ago to now includes only the 5-day-old row
    $report = app(UsageReport::class)->for(now()->subDays(7), now());
    expect($report['totals']['requests'])->toBe(1);

    // 2. Livewire component date range behaviour
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $this->actingAs($admin);

    // Reversed range swaps in component
    $reversed = Livewire::test(AssistantUsageReport::class)
        ->set('from', now()->toDateString())
        ->set('to', now()->subDays(7)->toDateString());
    expect($reversed->viewData('report')['totals']['requests'])->toBe(1)
        ->and($reversed->viewData('range'))->toEqual([now()->subDays(7)->toDateString(), now()->toDateString()]);

    // Invalid date strings fall back gracefully
    Livewire::test(AssistantUsageReport::class)
        ->set('from', 'not-a-date')
        ->set('to', 'also-invalid')
        ->assertSeeHtml('data-total="requests"');

    // Range diff > 366 days is capped
    $capped = Livewire::test(AssistantUsageReport::class)
        ->set('from', now()->subDays(400)->toDateString())
        ->set('to', now()->toDateString());
    expect($capped->viewData('range')[0])->toBe(now()->subDays(366)->toDateString());

    // 3. Empty period shows [data-usage-empty]
    Livewire::test(AssistantUsageReport::class)
        ->set('from', now()->addDays(5)->toDateString())
        ->set('to', now()->addDays(10)->toDateString())
        ->assertSeeHtml('data-usage-empty');
})->group('T-P7-06', 'A2');

it('ensures console traffic through AskInTestConsole does not affect report totals', function (): void {
    [$instructor, $course, $lesson] = tp706InsightsReadyLesson();

    $reportBefore = app(UsageReport::class)->for(now()->subDays(7), now());
    $requestsBefore = $reportBefore['totals']['requests'];

    // Run test console query
    $console = app(AskInTestConsole::class);
    $result = $console($instructor, $lesson, 'How does Pythagoras apply?', 'en', StyleTier::Adults);

    expect($result)->toBeInstanceOf(\App\Domain\AI\Sandbox\SandboxResult::class);

    $reportAfter = app(UsageReport::class)->for(now()->subDays(7), now());
    expect($reportAfter['totals']['requests'])->toBe($requestsBefore);
})->group('T-P7-06', 'A2');

it('anonymizes and aggregates LessonInsights correctly and excludes PII and empty questions', function (): void {
    [$instructor, $course, $lesson, $doc] = tp706InsightsReadyLesson();

    // Create key terms: 2 approved, 1 unapproved
    LessonKeyTerm::query()->create(['document_id' => $doc->id, 'term' => 'Pythagorean', 'locale' => 'en', 'approved' => true]);
    LessonKeyTerm::query()->create(['document_id' => $doc->id, 'term' => 'Equilateral', 'locale' => 'en', 'approved' => true]);
    LessonKeyTerm::query()->create(['document_id' => $doc->id, 'term' => 'UnapprovedTerm', 'locale' => 'en', 'approved' => false]);

    $student = User::factory()->create(['id' => 98765, 'name' => 'John Doe', 'email' => 'john@test.com']);

    $conv = AiConversation::query()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'lesson_id' => $lesson->id,
        'document_version' => 1,
        'locale' => 'en',
        'style_tier' => 'adults',
        'status' => 'open',
    ]);

    // 1. Plain question mentioning Pythagorean -> answered citing pages 1 and 2
    $q1 = AiMessage::query()->create([
        'conversation_id' => $conv->id,
        'role' => AiMessage::ROLE_USER,
        'content' => 'What is the Pythagorean theorem?',
        'guard_flags' => ['mode' => 'normal'],
    ]);
    $a1 = AiMessage::query()->create([
        'conversation_id' => $conv->id,
        'role' => AiMessage::ROLE_ASSISTANT,
        'content' => 'SECRET-ANSWER-1',
        'status' => 'answered',
        'source_pages' => [1, 2],
    ]);

    // 2. Question with PII -> answered citing page 1
    $q2 = AiMessage::query()->create([
        'conversation_id' => $conv->id,
        'role' => AiMessage::ROLE_USER,
        'content' => 'My phone is 01012345678, tell me about Equilateral triangles',
        'guard_flags' => ['mode' => 'normal', 'pii' => ['phone']],
    ]);
    $a2 = AiMessage::query()->create([
        'conversation_id' => $conv->id,
        'role' => AiMessage::ROLE_ASSISTANT,
        'content' => 'SECRET-ANSWER-2',
        'status' => 'answered',
        'source_pages' => [1],
    ]);

    // 3. Simpler mode question
    $q3 = AiMessage::query()->create([
        'conversation_id' => $conv->id,
        'role' => AiMessage::ROLE_USER,
        'content' => 'Explain simply',
        'guard_flags' => ['mode' => 'simpler'],
    ]);
    $a3 = AiMessage::query()->create([
        'conversation_id' => $conv->id,
        'role' => AiMessage::ROLE_ASSISTANT,
        'content' => 'SECRET-ANSWER-3',
        'status' => 'answered',
        'source_pages' => [1],
    ]);

    // 4. Gap question -> not_in_source answer
    $q4 = AiMessage::query()->create([
        'conversation_id' => $conv->id,
        'role' => AiMessage::ROLE_USER,
        'content' => 'How to build a wooden easel?',
        'guard_flags' => ['mode' => 'normal'],
    ]);
    $a4 = AiMessage::query()->create([
        'conversation_id' => $conv->id,
        'role' => AiMessage::ROLE_ASSISTANT,
        'content' => '',
        'status' => 'not_in_source',
    ]);

    // 5. Out of scope question
    $q5 = AiMessage::query()->create([
        'conversation_id' => $conv->id,
        'role' => AiMessage::ROLE_USER,
        'content' => 'What is the capital of France?',
        'guard_flags' => ['mode' => 'normal'],
    ]);
    $a5 = AiMessage::query()->create([
        'conversation_id' => $conv->id,
        'role' => AiMessage::ROLE_ASSISTANT,
        'content' => '',
        'status' => 'out_of_scope',
    ]);

    // 6. Fallback answer
    $q6 = AiMessage::query()->create([
        'conversation_id' => $conv->id,
        'role' => AiMessage::ROLE_USER,
        'content' => 'Some other question',
        'guard_flags' => ['mode' => 'normal'],
    ]);
    $a6 = AiMessage::query()->create([
        'conversation_id' => $conv->id,
        'role' => AiMessage::ROLE_ASSISTANT,
        'content' => '',
        'status' => 'fallback',
    ]);

    // 7. Not understood feedback
    AiFeedback::query()->create([
        'message_id' => $a1->id,
        'student_id' => $student->id,
        'type' => AiFeedback::NOT_UNDERSTOOD,
    ]);

    $insights = app(LessonInsights::class)->for($lesson, now()->subDays(1), now());

    // Questions count excludes simpler mode: 5 plain questions (q1, q2, q4, q5, q6)
    expect($insights['questions'])->toBe(5)
        ->and($insights['answered'])->toBe(3)
        ->and($insights['not_in_source'])->toBe(1)
        ->and($insights['out_of_scope'])->toBe(1)
        ->and($insights['fallback'])->toBe(1)
        ->and($insights['not_understood'])->toBe(1)
        ->and($insights['simpler'])->toBe(1);

    // Topics: only approved terms found in plain questions without PII:
    // Pythagorean in q1 -> 1 hit
    // Equilateral in q2 has PII -> excluded by isPlainQuestion
    // UnapprovedTerm -> excluded because unapproved
    expect($insights['topics'])->toHaveCount(1)
        ->and($insights['topics'][0]['term'])->toBe('Pythagorean')
        ->and($insights['topics'][0]['count'])->toBe(1);

    // Cited pages: page 1 cited 3 times, page 2 cited 1 time
    expect($insights['pages'])->toEqual([
        ['page' => 1, 'count' => 3],
        ['page' => 2, 'count' => 1],
    ]);

    // Gaps: q4 preceding a4 (not_in_source)
    expect($insights['gaps'])->toHaveCount(1)
        ->and($insights['gaps'][0]['question'])->toBe('How to build a wooden easel?')
        ->and($insights['gaps'][0]['count'])->toBe(1);

    // Strict anonymity check: serialize insights and ensure NO PII, student name, email, IDs, or answer texts
    $json = json_encode($insights, JSON_THROW_ON_ERROR);
    expect($json)->not->toContain('John Doe')
        ->not->toContain('john@test.com')
        ->not->toContain((string) $student->id)
        ->not->toContain('01012345678')
        ->not->toContain('SECRET-ANSWER-1')
        ->not->toContain('SECRET-ANSWER-2')
        ->not->toContain('SECRET-ANSWER-3');
})->group('T-P7-06', 'A2');

it('enforces authorization on assistant usage report page', function (): void {
    $roles = [
        'super_admin' => 200,
        'admin' => 200,
        'instructor_academy' => 403,
        'support' => 403,
        'finance' => 403,
        'student' => 403,
    ];

    foreach ($roles as $role => $expectedStatus) {
        $user = User::factory()->create(['status' => 'active'])->assignRole($role);
        $this->actingAs($user);

        $response = $this->get('/admin/assistant-usage');
        expect($response->status())->toBe($expectedStatus, "Failed for role {$role}");
    }

    // Suspended admin is 403
    $suspendedAdmin = User::factory()->create(['status' => 'suspended'])->assignRole('admin');
    $this->actingAs($suspendedAdmin);
    expect($this->get('/admin/assistant-usage')->status())->toBe(403);
})->group('T-P7-06', 'A2');

it('enforces lesson scoping and course permissions on AssistantInsights page', function (): void {
    [$instructor1, $course1, $lesson1] = tp706InsightsReadyLesson();
    [$instructor2, $course2, $lesson2] = tp706InsightsReadyLesson();

    // 1. Instructor 1 only sees their own courses / lessons
    $this->actingAs($instructor1);

    $test = Livewire::test(AssistantInsights::class);
    $lessons = $test->viewData('lessons');
    expect(array_keys($lessons))->toContain($lesson1->id)
        ->and(array_keys($lessons))->not->toContain($lesson2->id);

    // Selecting own lesson yields data
    $test->set('lessonId', $lesson1->id);
    expect($test->viewData('insights'))->not->toBeNull();

    // Setting another instructor's lessonId yields null (no data leak, no error)
    $test->set('lessonId', $lesson2->id);
    expect($test->viewData('insights'))->toBeNull();

    // 2. Admin sees all lessons
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $this->actingAs($admin);

    $adminTest = Livewire::test(AssistantInsights::class);
    $adminLessons = $adminTest->viewData('lessons');
    expect(array_keys($adminLessons))->toContain($lesson1->id)
        ->and(array_keys($adminLessons))->toContain($lesson2->id);
})->group('T-P7-06', 'A2');
