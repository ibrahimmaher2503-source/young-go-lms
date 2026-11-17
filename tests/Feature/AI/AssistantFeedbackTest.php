<?php

// T-P7-04 | AI spec §12 §16, PRD §15.5 §16, D-019..D-021 | A1 A2 A3 A4

declare(strict_types=1);

use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Entitlements\Contracts\LessonAccessChecker;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Notifications\NotificationDispatch;
use App\Domain\Settings\PlatformFlag;
use App\Models\AiConversation;
use App\Models\AiFeedback;
use App\Models\AiMessage;
use App\Models\Course;
use App\Models\HelpRequest;
use App\Models\HelpRequestEvent;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    config(['ai.adult_age' => null]);
    $this->withoutVite();
});

/**
 * @return array{0: User, 1: Lesson, 2: Course, 3: mixed, 4: User, 5: User}
 */
function afbReady(bool $withManageInstructor = true): array
{
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $course = Course::query()->create([
        'title' => ['en' => 'AI Course', 'ar' => 'دورة الذكاء'],
        'slug_ar' => 'ai-ar-'.uniqid(),
        'slug_en' => 'ai-en-'.uniqid(),
        'status' => 'published',
        'ai_enabled' => true,
    ]);

    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);

    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Section 1']]);
    $item = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Lesson 1'], 'type' => 'video']);
    /** @var Lesson $lesson */
    $lesson = $item->itemable;
    $lesson->forceFill(['type' => 'video'])->save();

    if (! $withManageInstructor) {
        $course->instructors()->detach();
    }

    LessonCurriculumDocument::query()->create([
        'lesson_id' => $lesson->id,
        'version' => 1,
        'path' => 'curriculum/doc.pdf',
        'sha256' => str_repeat('a', 64),
        'size' => 1024,
        'pages' => 2,
        'status' => LessonCurriculumDocument::STATUS_READY,
        'active' => true,
    ]);

    $student = User::factory()->create(['status' => 'active'])->assignRole('student');
    $parent = User::factory()->create(['status' => 'active'])->assignRole('parent');
    GuardianChildLink::query()->create([
        'guardian_id' => $parent->id,
        'child_id' => $student->id,
        'status' => GuardianChildLink::STATUS_VERIFIED,
        'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT,
        'verified_at' => now(),
        'ai_consent_granted_at' => now(),
        'ai_consent_version' => 'v1',
    ]);

    Feature::for(null)->activate(PlatformFlag::AiAssistant->value);
    Feature::for(null)->activate(PlatformFlag::AiMinors->value);
    app()->bind(LessonAccessChecker::class, fn () => new class implements LessonAccessChecker
    {
        public function canAccessLessonFiles(User $user, Lesson $lesson): bool
        {
            return true;
        }
    });

    return [$student, $lesson->refresh(), $course, $item, $parent, $instructor];
}

/**
 * @return array{0: AiConversation, 1: AiMessage, 2: AiMessage}
 */
function afbSeedTurn(
    User $student,
    Course $course,
    Lesson $lesson,
    string $question = 'What is composition?',
    string $answer = 'Composition is the arrangement of visual elements.',
    ?array $guardFlags = null,
    ?string $status = 'answered',
    array $sourcePages = [1, 2],
    int $videoPosition = 75,
): array {
    $conversation = AiConversation::query()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'lesson_id' => $lesson->id,
        'document_version' => 1,
        'locale' => 'en',
        'style_tier' => 'standard',
        'video_position' => $videoPosition,
        'status' => 'active',
    ]);

    $userMsg = AiMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => AiMessage::ROLE_USER,
        'content' => $question,
    ]);

    $assistantMsg = AiMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => AiMessage::ROLE_ASSISTANT,
        'content' => $answer,
        'status' => $status,
        'source_pages' => $sourcePages,
        'guard_flags' => $guardFlags,
        'prompt_version' => 1,
        'model' => 'fake-model',
        'cache_hit' => false,
    ]);

    return [$conversation, $userMsg, $assistantMsg];
}

it('records understood feedback without creating a help request, idempotently, and logs audit', function (): void {
    [$student, $lesson, $course] = afbReady();
    [$conversation, $userMsg, $assistantMsg] = afbSeedTurn($student, $course, $lesson);

    $endpoint = route('assistant.feedback', ['lesson' => $lesson]);

    $response = $this->actingAs($student)->postJson($endpoint, [
        'message_id' => $assistantMsg->id,
        'type' => AiFeedback::UNDERSTOOD,
    ]);

    $response->assertOk()
        ->assertJson([
            'state' => 'recorded',
            'message' => __('help.panel.thanks'),
        ]);

    expect(HelpRequest::count())->toBe(0);
    expect(AiFeedback::where('message_id', $assistantMsg->id)->where('type', AiFeedback::UNDERSTOOD)->count())->toBe(1);

    // Audit logged
    $audit = Activity::where('log_name', 'audit')->where('event', 'ai.feedback_recorded')->first();
    expect($audit)->not->toBeNull();
    expect((int) $audit?->causer_id)->toBe($student->id);
    expect($audit?->properties['after'])->toBe(['type' => AiFeedback::UNDERSTOOD, 'message_id' => $assistantMsg->id]);
    expect($audit?->properties['context']['lesson_id'])->toBe($lesson->id);

    // Repeated call is idempotent no-op and creates no additional audit
    $repeatResponse = $this->actingAs($student)->postJson($endpoint, [
        'message_id' => $assistantMsg->id,
        'type' => AiFeedback::UNDERSTOOD,
    ]);

    $repeatResponse->assertOk()
        ->assertJson([
            'state' => 'recorded',
            'message' => __('help.panel.thanks'),
        ]);

    expect(AiFeedback::where('message_id', $assistantMsg->id)->count())->toBe(1);
    expect(Activity::where('log_name', 'audit')->where('event', 'ai.feedback_recorded')->count())->toBe(1);
})->group('T-P7-04', 'A1', 'D-019');

it('creates exactly one help request on not_understood feedback and returns 201 then 200 on repeated taps', function (): void {
    [$student, $lesson, $course, $item, $parent, $instructor] = afbReady();
    [$conversation, $userMsg, $assistantMsg] = afbSeedTurn($student, $course, $lesson, 'How to draw circles?', 'Use circular motions.', null, 'answered', [1, 2], 135);

    $endpoint = route('assistant.feedback', ['lesson' => $lesson]);

    // First tap -> 201 request_created
    $response = $this->actingAs($student)->postJson($endpoint, [
        'message_id' => $assistantMsg->id,
        'type' => AiFeedback::NOT_UNDERSTOOD,
    ]);

    $response->assertStatus(201)
        ->assertJson([
            'state' => 'request_created',
            'message' => __('help.panel.created'),
            'status' => 'new',
        ]);

    $requestId = $response->json('request_id');
    expect($requestId)->not->toBeNull();
    expect($response->json('thread_url'))->toBe(route('help.show', ['helpRequest' => $requestId]));

    // Check request row details
    /** @var HelpRequest $request */
    $request = HelpRequest::findOrFail($requestId);
    expect($request->student_id)->toBe($student->id);
    expect($request->course_id)->toBe($course->id);
    expect($request->lesson_id)->toBe($lesson->id);
    expect($request->assigned_instructor_id)->toBe($instructor->id);
    expect($request->conversation_id)->toBe($conversation->id);
    expect($request->ai_message_id)->toBe($assistantMsg->id);
    expect($request->status)->toBe(\App\Domain\AI\Help\HelpRequestStatus::New);
    expect($request->locale)->toBe('en');
    expect($request->video_position)->toBe(135);
    expect($request->document_version)->toBe(1);
    expect($request->ai_status)->toBe('answered');
    expect($request->source_pages)->toBe([1, 2]);
    expect($request->question)->toBe('How to draw circles?');
    expect($request->ai_answer)->toBe('Use circular motions.');
    expect($request->due_at)->not->toBeNull();
    expect($request->due_at?->diffInHours(now()->addHours(24)))->toBeLessThanOrEqual(1);

    // Event created
    $event = HelpRequestEvent::where('help_request_id', $request->id)->first();
    expect($event)->not->toBeNull();
    expect($event?->event)->toBe('created');
    expect($event?->actor_id)->toBe($student->id);
    expect($event?->to_status)->toBe('new');

    // Audit created
    $audit = Activity::where('log_name', 'audit')->where('event', 'help_request.created')->first();
    expect($audit)->not->toBeNull();
    expect((int) $audit?->causer_id)->toBe($student->id);
    expect($audit?->properties['context']['assigned_instructor_id'])->toBe($instructor->id);
    expect($audit?->properties['context']['routed_to'])->toBe('instructor');
    // Ensure audit context contains ids only, no question text
    expect(json_encode($audit?->properties))->not->toContain('How to draw circles?');

    // Also records not_understood ai_feedback
    expect(AiFeedback::where('message_id', $assistantMsg->id)->where('type', AiFeedback::NOT_UNDERSTOOD)->exists())->toBeTrue();

    // Repeated taps (4 more times) return 200 request_exists and same request
    for ($i = 0; $i < 4; $i++) {
        $repeat = $this->actingAs($student)->postJson($endpoint, [
            'message_id' => $assistantMsg->id,
            'type' => AiFeedback::NOT_UNDERSTOOD,
        ]);

        $repeat->assertOk()
            ->assertJson([
                'state' => 'request_exists',
                'message' => __('help.panel.exists'),
                'request_id' => $requestId,
                'status' => 'new',
            ]);
    }

    expect(HelpRequest::count())->toBe(1);
})->group('T-P7-04', 'A1', 'D-019');

it('routes request to admin queue when course has no manage-scope reply instructor and notifies managers', function (): void {
    [$student, $lesson, $course] = afbReady(withManageInstructor: false);
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $superAdmin = User::factory()->create(['status' => 'active'])->assignRole('super_admin');

    [$conversation, $userMsg, $assistantMsg] = afbSeedTurn($student, $course, $lesson);

    $endpoint = route('assistant.feedback', ['lesson' => $lesson]);

    $response = $this->actingAs($student)->postJson($endpoint, [
        'message_id' => $assistantMsg->id,
        'type' => AiFeedback::NOT_UNDERSTOOD,
    ]);

    $response->assertStatus(201);
    $request = HelpRequest::firstOrFail();
    expect($request->assigned_instructor_id)->toBeNull();

    // Staff notifications dispatched
    $notices = NotificationDispatch::where('dedupe_key', 'help-request-new:'.$request->id)->get();
    expect($notices->pluck('recipient_id')->all())->toContain($admin->id);
    expect($notices->pluck('recipient_id')->all())->toContain($superAdmin->id);
})->group('T-P7-04', 'A1', 'D-019');

it('returns 404 and creates nothing for other learners answers, other lessons answers, or user-role messages', function (): void {
    [$student, $lesson, $course] = afbReady();
    $otherStudent = User::factory()->create(['status' => 'active'])->assignRole('student');

    [$conversation, $userMsg, $assistantMsg] = afbSeedTurn($student, $course, $lesson);

    $endpoint = route('assistant.feedback', ['lesson' => $lesson]);

    // Another learner's answer
    $this->actingAs($otherStudent)->postJson($endpoint, [
        'message_id' => $assistantMsg->id,
        'type' => AiFeedback::NOT_UNDERSTOOD,
    ])->assertNotFound()->assertJson([
        'state' => 'not_found',
        'message' => __('help.refused.not_found', [
            'max' => (int) config('ai.help.max_open_per_lesson', 3),
            'chars' => (int) config('ai.help.reply_max_chars', 2000),
        ]),
    ]);

    // Another lesson's route
    $otherLesson = Lesson::query()->create(['type' => 'video', 'title' => 'Other Lesson']);
    $otherEndpoint = route('assistant.feedback', ['lesson' => $otherLesson]);
    $this->actingAs($student)->postJson($otherEndpoint, [
        'message_id' => $assistantMsg->id,
        'type' => AiFeedback::NOT_UNDERSTOOD,
    ])->assertNotFound();

    // User-role message ID
    $this->actingAs($student)->postJson($endpoint, [
        'message_id' => $userMsg->id,
        'type' => AiFeedback::NOT_UNDERSTOOD,
    ])->assertNotFound();

    // Non-existent message
    $this->actingAs($student)->postJson($endpoint, [
        'message_id' => 999999,
        'type' => AiFeedback::NOT_UNDERSTOOD,
    ])->assertNotFound();

    expect(HelpRequest::count())->toBe(0);
})->group('T-P7-04', 'A1', 'D-019');

it('enforces open requests limit per learner and lesson, freeing a slot when resolved and leaving other lessons unaffected', function (): void {
    [$student, $lesson, $course, $item, $parent, $instructor] = afbReady();

    $endpoint = route('assistant.feedback', ['lesson' => $lesson]);

    // Create 3 open requests on $lesson
    $requests = [];
    for ($i = 1; $i <= 3; $i++) {
        [$conversation, $userMsg, $assistantMsg] = afbSeedTurn($student, $course, $lesson, "Question {$i}", "Answer {$i}");
        $res = $this->actingAs($student)->postJson($endpoint, [
            'message_id' => $assistantMsg->id,
            'type' => AiFeedback::NOT_UNDERSTOOD,
        ]);
        $res->assertStatus(201);
        $requests[] = HelpRequest::find($res->json('request_id'));
    }

    expect(HelpRequest::count())->toBe(3);

    // 4th request on same lesson -> 409 open_limit
    [$conv4, $userMsg4, $assistantMsg4] = afbSeedTurn($student, $course, $lesson, 'Question 4', 'Answer 4');
    $res4 = $this->actingAs($student)->postJson($endpoint, [
        'message_id' => $assistantMsg4->id,
        'type' => AiFeedback::NOT_UNDERSTOOD,
    ]);

    $res4->assertStatus(409)->assertJson([
        'state' => 'open_limit',
        'message' => __('help.refused.open_limit', ['max' => 3]),
    ]);
    expect(HelpRequest::count())->toBe(3);

    // A different lesson on the same course is unaffected
    $section = $lesson->item->section;
    $item2 = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Lesson 2'], 'type' => 'video']);
    /** @var Lesson $lesson2 */
    $lesson2 = $item2->itemable;
    $lesson2->forceFill(['type' => 'video'])->save();
    LessonCurriculumDocument::query()->create([
        'lesson_id' => $lesson2->id,
        'version' => 1,
        'path' => 'curriculum/doc2.pdf',
        'sha256' => str_repeat('b', 64),
        'size' => 1024,
        'pages' => 2,
        'status' => LessonCurriculumDocument::STATUS_READY,
        'active' => true,
    ]);

    [$convOther, $userOther, $asstOther] = afbSeedTurn($student, $course, $lesson2, 'Other Q', 'Other A');
    $this->actingAs($student)->postJson(route('assistant.feedback', ['lesson' => $lesson2]), [
        'message_id' => $asstOther->id,
        'type' => AiFeedback::NOT_UNDERSTOOD,
    ])->assertStatus(201);
    expect(HelpRequest::count())->toBe(4);

    // Resolving one request on $lesson frees a slot
    $requests[0]->forceFill(['status' => \App\Domain\AI\Help\HelpRequestStatus::Resolved])->save();

    // Now 4th request on $lesson succeeds
    $res4Retry = $this->actingAs($student)->postJson($endpoint, [
        'message_id' => $assistantMsg4->id,
        'type' => AiFeedback::NOT_UNDERSTOOD,
    ]);
    $res4Retry->assertStatus(201);
    expect(HelpRequest::count())->toBe(5);
})->group('T-P7-04', 'A1', 'D-019');

it('refuses guard-blocked answers with 409 not_escalatable', function (): void {
    [$student, $lesson, $course] = afbReady();

    // Guard-produced answer (e.g. quiz question guard blocked)
    [$conversation, $userMsg, $assistantMsg] = afbSeedTurn(
        $student, $course, $lesson,
        'What is on question 1 of the quiz?',
        'I cannot help with quiz questions.',
        ['guard' => 'quiz_question']
    );

    $endpoint = route('assistant.feedback', ['lesson' => $lesson]);

    $this->actingAs($student)->postJson($endpoint, [
        'message_id' => $assistantMsg->id,
        'type' => AiFeedback::NOT_UNDERSTOOD,
    ])->assertStatus(409)->assertJson([
        'state' => 'not_escalatable',
        'message' => __('help.refused.not_escalatable', [
            'max' => (int) config('ai.help.max_open_per_lesson', 3),
            'chars' => (int) config('ai.help.reply_max_chars', 2000),
        ]),
    ]);

    // Answer with null status is also treated as non-escalatable
    [$conv2, $userMsg2, $assistantMsg2] = afbSeedTurn(
        $student, $course, $lesson,
        'Another question',
        'Blocked',
        null,
        null
    );

    $this->actingAs($student)->postJson($endpoint, [
        'message_id' => $assistantMsg2->id,
        'type' => AiFeedback::NOT_UNDERSTOOD,
    ])->assertStatus(409);

    expect(HelpRequest::count())->toBe(0);
})->group('T-P7-04', 'A1', 'D-019');

it('allows feedback even when the assistant is switched off for the course or lesson', function (): void {
    [$student, $lesson, $course] = afbReady();
    [$conversation, $userMsg, $assistantMsg] = afbSeedTurn($student, $course, $lesson);

    // Disable AI on course and curriculum item
    $course->forceFill(['ai_enabled' => false])->save();
    $lesson->item->forceFill(['ai_enabled' => false])->save();

    $endpoint = route('assistant.feedback', ['lesson' => $lesson]);

    $response = $this->actingAs($student)->postJson($endpoint, [
        'message_id' => $assistantMsg->id,
        'type' => AiFeedback::NOT_UNDERSTOOD,
    ]);

    $response->assertStatus(201);
    expect(HelpRequest::count())->toBe(1);
})->group('T-P7-04', 'A1', 'D-019');

it('returns 403 for user without ai.ask permission and 401 for unauthenticated user', function (): void {
    [$student, $lesson, $course] = afbReady();
    [$conversation, $userMsg, $assistantMsg] = afbSeedTurn($student, $course, $lesson);

    $endpoint = route('assistant.feedback', ['lesson' => $lesson]);

    // Unauthenticated
    auth()->logout();
    $this->postJson($endpoint, [
        'message_id' => $assistantMsg->id,
        'type' => AiFeedback::NOT_UNDERSTOOD,
    ])->assertUnauthorized();

    // User without ai.ask
    $userWithoutAsk = User::factory()->create(['status' => 'active']);
    $this->actingAs($userWithoutAsk)->postJson($endpoint, [
        'message_id' => $assistantMsg->id,
        'type' => AiFeedback::NOT_UNDERSTOOD,
    ])->assertForbidden();
})->group('T-P7-04', 'A1', 'D-019');

it('validates feedback input schema and ignores client-provided student_id', function (): void {
    [$student, $lesson, $course] = afbReady();
    [$conversation, $userMsg, $assistantMsg] = afbSeedTurn($student, $course, $lesson);

    $endpoint = route('assistant.feedback', ['lesson' => $lesson]);

    // Invalid type
    $this->actingAs($student)->postJson($endpoint, [
        'message_id' => $assistantMsg->id,
        'type' => 'unknown_type',
    ])->assertUnprocessable();

    // Missing message_id
    $this->actingAs($student)->postJson($endpoint, [
        'type' => AiFeedback::UNDERSTOOD,
    ])->assertUnprocessable();

    // Attempting to pass a foreign student_id is ignored
    $otherStudent = User::factory()->create(['status' => 'active'])->assignRole('student');
    $res = $this->actingAs($student)->postJson($endpoint, [
        'message_id' => $assistantMsg->id,
        'type' => AiFeedback::NOT_UNDERSTOOD,
        'student_id' => $otherStudent->id,
    ]);

    $res->assertStatus(201);
    $req = HelpRequest::firstOrFail();
    expect($req->student_id)->toBe($student->id);
    expect($req->student_id)->not->toBe($otherStudent->id);
})->group('T-P7-04', 'A1', 'D-019');
