<?php

declare(strict_types=1);

use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Reports\Queries\InstructorOperationsReport;
use App\Domain\Reports\ReportFilters;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('counts current workloads and date-windowed activity for assigned courses', function (): void {
    app()->setLocale('en');
    $this->travelTo(now()->setDate(2026, 11, 15)->startOfDay());
    $instructor = User::factory()->create();
    $otherInstructor = User::factory()->create();
    $learner = User::factory()->create();
    $course = Course::query()->create(['title' => ['en' => 'Assigned course'], 'slug_ar' => 'ops-ar', 'slug_en' => 'ops-en']);
    Course::query()->create(['title' => ['en' => 'Outside scope'], 'slug_ar' => 'outside-ops-ar', 'slug_en' => 'outside-ops-en']);
    $course->instructors()->attach($instructor, ['role' => 'instructor', 'scope' => 'manage']);
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free, 'second-course-grant');

    foreach ([
        ['2026-11-14', 'scheduled'], ['2026-11-16', 'scheduled'],
        ['2026-11-16', 'cancelled'], ['2026-11-22', 'scheduled'],
    ] as [$date, $status]) {
        DB::table('live_sessions')->insert([
            'course_id' => $course->id, 'title' => '{}', 'starts_at' => $date.' 08:00:00', 'ends_at' => $date.' 09:00:00',
            'timezone' => 'UTC', 'status' => $status,
        ]);
    }

    $section = DB::table('course_sections')->insertGetId(['course_id' => $course->id, 'title' => '{}']);
    $assignment = DB::table('assignments')->insertGetId(['title' => '{}']);
    $quiz = DB::table('quizzes')->insertGetId(['title' => '{}']);
    foreach (['assignment' => $assignment, 'quiz' => $quiz] as $type => $id) {
        DB::table('curriculum_items')->insert(['course_id' => $course->id, 'section_id' => $section, 'itemable_type' => $type, 'itemable_id' => $id]);
    }
    DB::table('assignment_submissions')->insert(['assignment_id' => $assignment, 'learner_id' => $learner->id, 'status' => 'submitted']);
    DB::table('quiz_attempts')->insert([
        'quiz_id' => $quiz, 'learner_id' => $learner->id, 'attempt_no' => 1, 'status' => 'submitted',
        'needs_review' => true, 'started_at' => now(), 'submitted_at' => now(), 'snapshot' => '[]', 'settings' => '{}',
    ]);
    DB::table('course_completions')->insert(['course_id' => $course->id, 'learner_id' => $learner->id, 'completed_at' => now(), 'source' => 'auto']);

    $lesson = Lesson::query()->create(['title' => ['en' => 'Help lesson']]);
    $conversation = AiConversation::query()->create([
        'student_id' => $learner->id, 'course_id' => $course->id, 'lesson_id' => $lesson->id,
        'document_version' => 1, 'locale' => 'en', 'style_tier' => 'adults',
    ]);
    foreach ([[$instructor->id, 'new'], [$instructor->id, 'resolved'], [$otherInstructor->id, 'new']] as [$assignee, $status]) {
        $message = AiMessage::query()->create(['conversation_id' => $conversation->id, 'role' => AiMessage::ROLE_ASSISTANT, 'content' => 'Private answer']);
        DB::table('help_requests')->insert([
            'student_id' => $learner->id, 'course_id' => $course->id, 'lesson_id' => $lesson->id, 'locale' => 'en',
            'conversation_id' => $conversation->id, 'ai_message_id' => $message->id, 'document_version' => 1,
            'question' => 'Private question', 'ai_answer' => 'Private answer', 'status' => $status,
            'assigned_instructor_id' => $assignee, 'created_at' => now()->subMonth(),
        ]);
    }

    $filters = ReportFilters::fromInput(['from' => '2026-11-12', 'to' => '2026-11-18'])
        ->scoped([$course->id], null, $instructor->id, null);
    $report = app(InstructorOperationsReport::class);
    $rows = iterator_to_array($report->rows($filters));

    expect($report->count($filters))->toBe(1)
        ->and($rows)->toBe([['Assigned course', 1, 1, 2, 1, 1]])
        ->and($report->totals($filters))->toHaveCount(5)
        ->and($report->count($filters->scoped([], null, $instructor->id, null)))->toBe(0);
})->group('T-P9-06', 'PRD-21', 'A2', 'A3');
