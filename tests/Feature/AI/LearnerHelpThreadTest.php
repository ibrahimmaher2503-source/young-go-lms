<?php

// T-P7-04 | AI spec §12 §16, PRD §15.5 §16, D-019..D-021 | A1 A2 A3 A4

declare(strict_types=1);

use App\Domain\AI\Help\Actions\ReplyToHelpRequest;
use App\Domain\AI\Help\HelpLinks;
use App\Domain\AI\Help\HelpRequestStatus;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Entitlements\Contracts\LessonAccessChecker;
use App\Domain\Identity\ActingLearner;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Settings\PlatformFlag;
use App\Models\AiConversation;
use App\Models\AiFeedback;
use App\Models\AiMessage;
use App\Models\Course;
use App\Models\HelpRequest;
use App\Models\HelpRequestMessage;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
});

/**
 * @return array{0: Course, 1: Lesson, 2: User, 3: User, 4: User}
 */
function lhtCourse(string $title = 'Thread Course'): array
{
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $course = Course::query()->create([
        'title' => ['en' => $title, 'ar' => $title.' ar'],
        'slug_ar' => 'th-ar-'.uniqid(),
        'slug_en' => 'th-en-'.uniqid(),
        'status' => 'published',
        'ai_enabled' => true,
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);

    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Sec']]);
    $item = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Lesson Title'], 'type' => 'video']);
    /** @var Lesson $lesson */
    $lesson = $item->itemable;
    $lesson->forceFill(['type' => 'video'])->save();

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
    $parent = User::factory()->create(['status' => 'active', 'email_verified_at' => now()])->assignRole('parent');

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

    return [$course, $lesson, $instructor, $student, $parent];
}

function lhtCreateRequest(
    Course $course,
    Lesson $lesson,
    User $student,
    ?User $assignedInstructor = null,
    HelpRequestStatus $status = HelpRequestStatus::New,
): HelpRequest {
    $conv = AiConversation::query()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'lesson_id' => $lesson->id,
        'document_version' => 1,
        'locale' => 'en',
        'style_tier' => 'standard',
        'video_position' => 30,
        'status' => 'active',
    ]);

    $userMsg = AiMessage::query()->create([
        'conversation_id' => $conv->id,
        'role' => AiMessage::ROLE_USER,
        'content' => 'How do I shade spherical surfaces?',
    ]);

    $assistantMsg = AiMessage::query()->create([
        'conversation_id' => $conv->id,
        'role' => AiMessage::ROLE_ASSISTANT,
        'content' => 'Use cross-hatching and follow the curve of the sphere.',
        'status' => 'answered',
        'source_pages' => [1],
        'prompt_version' => 1,
        'model' => 'fake',
    ]);

    return HelpRequest::query()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'lesson_id' => $lesson->id,
        'assigned_instructor_id' => $assignedInstructor?->id,
        'conversation_id' => $conv->id,
        'ai_message_id' => $assistantMsg->id,
        'status' => $status,
        'locale' => 'en',
        'video_position' => 30,
        'document_version' => 1,
        'ai_status' => 'answered',
        'source_pages' => [1],
        'question' => 'How do I shade spherical surfaces?',
        'ai_answer' => 'Use cross-hatching and follow the curve of the sphere.',
        'due_at' => now()->addHours(24),
    ]);
}

it('renders learner thread with context, messages, and state-dependent controls', function (): void {
    [$course, $lesson, $instructor, $student] = lhtCourse();
    $request = lhtCreateRequest($course, $lesson, $student, $instructor, HelpRequestStatus::New);

    // 1. In 'new' state: shows waiting notice, no reply form, context present
    $this->actingAs($student);
    $responseNew = $this->get('/my/help/'.$request->id)->assertOk();
    $responseNew->assertSee('data-help-thread', false)
        ->assertSee('data-help-status="new"', false)
        ->assertSee(__('help.thread.title'))
        ->assertSee('data-help-context', false)
        ->assertSee('How do I shade spherical surfaces?')
        ->assertSee('Use cross-hatching and follow the curve of the sphere.')
        ->assertSee('data-help-wait', false)
        ->assertSee(__('help.thread.waiting_instructor'))
        ->assertDontSee('data-help-reply-form', false);

    // Context and messages render dir="auto"
    $responseNew->assertSee('dir="auto"', false);

    // 2. Staff replies -> instructor_replied: reply form is now visible
    app(ReplyToHelpRequest::class)($instructor, $request, 'Start with light strokes before adding darker cross-hatches.');
    $responseReplied = $this->get('/my/help/'.$request->id)->assertOk();
    $responseReplied->assertSee('data-help-status="instructor_replied"', false)
        ->assertSee('data-help-message="instructor"', false)
        ->assertSee('Start with light strokes before adding darker cross-hatches.')
        ->assertSee('data-help-reply-form', false)
        ->assertSee(__('help.thread.your_reply'))
        ->assertDontSee('data-help-wait', false);

    // 3. Learner posts reply via form
    $replyResponse = $this->post('/my/help/'.$request->id.'/replies', [
        'body' => 'I tried that and the sphere looks much rounder now!',
    ]);
    $replyResponse->assertRedirect(route('help.show', ['helpRequest' => $request->id]))
        ->assertSessionHas('help_notice', __('help.thread.sent'));

    $request->refresh();
    expect($request->status)->toBe(HelpRequestStatus::InProgress);

    // 4. InProgress state: student reply shown, waiting notice shown, reply form gone
    $responseInProgress = $this->get('/my/help/'.$request->id)->assertOk();
    $responseInProgress->assertSee('data-help-message="student"', false)
        ->assertSee('I tried that and the sphere looks much rounder now!')
        ->assertSee('data-help-wait', false)
        ->assertDontSee('data-help-reply-form', false);

    // 5. In resolved state: shows closed notice
    $request->forceFill(['status' => HelpRequestStatus::Resolved])->save();
    $responseResolved = $this->get('/my/help/'.$request->id)->assertOk();
    $responseResolved->assertSee('data-help-closed', false)
        ->assertSee(__('help.thread.closed'))
        ->assertDontSee('data-help-reply-form', false)
        ->assertDontSee('data-help-wait', false);
})->group('T-P7-04', 'A1', 'A3', 'D-021');

it('enforces parent context restrictions: hides question/answer and learner messages, and forbids reply', function (): void {
    [$course, $lesson, $instructor, $student, $parent] = lhtCourse();
    $request = lhtCreateRequest($course, $lesson, $student, $instructor, HelpRequestStatus::New);

    // Add instructor reply and student reply
    HelpRequestMessage::query()->create([
        'help_request_id' => $request->id,
        'author_id' => $instructor->id,
        'role' => HelpRequestMessage::ROLE_INSTRUCTOR,
        'body' => 'Instructor reply text',
    ]);
    HelpRequestMessage::query()->create([
        'help_request_id' => $request->id,
        'author_id' => $student->id,
        'role' => HelpRequestMessage::ROLE_STUDENT,
        'body' => 'Student private response text',
    ]);
    $request->forceFill(['status' => HelpRequestStatus::InstructorReplied])->save();

    // Parent without acting session cannot open thread -> 404
    $this->actingAs($parent);
    $this->get('/my/help/'.$request->id)->assertNotFound();

    // Parent acting for the child opens thread
    $this->actingAs($parent)->withSession([ActingLearner::SESSION_KEY => $student->id]);
    $response = $this->get('/my/help/'.$request->id)->assertOk();

    // Sees status and instructor reply
    $response->assertSee('data-help-status="instructor_replied"', false)
        ->assertSee('data-help-message="instructor"', false)
        ->assertSee('Instructor reply text');

    // Does NOT see question or AI answer: [data-help-context] is absent
    $response->assertDontSee('data-help-context', false)
        ->assertDontSee('How do I shade spherical surfaces?')
        ->assertDontSee('Use cross-hatching and follow the curve of the sphere.');

    // Does NOT see student's private message
    $response->assertDontSee('data-help-message="student"', false)
        ->assertDontSee('Student private response text');

    // Does NOT see reply form
    $response->assertDontSee('data-help-reply-form', false);

    // Reply POST in parent context is 403 forbidden
    $this->post('/my/help/'.$request->id.'/replies', [
        'body' => 'Parent trying to reply',
    ])->assertForbidden();

    // Parent cannot open another child's thread (404)
    $otherStudent = User::factory()->create(['status' => 'active'])->assignRole('student');
    $otherRequest = lhtCreateRequest($course, $lesson, $otherStudent, $instructor);

    $this->get('/my/help/'.$otherRequest->id)->assertNotFound();
})->group('T-P7-04', 'A1', 'A3', 'D-021');

it('records acting_for in audit when parent acts for child on feedback endpoint', function (): void {
    [$course, $lesson, $instructor, $student, $parent] = lhtCourse();

    $conv = AiConversation::query()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'lesson_id' => $lesson->id,
        'document_version' => 1,
        'locale' => 'en',
        'style_tier' => 'standard',
        'video_position' => 10,
        'status' => 'active',
    ]);
    AiMessage::query()->create([
        'conversation_id' => $conv->id,
        'role' => AiMessage::ROLE_USER,
        'content' => 'Child Q',
    ]);
    $assistantMsg = AiMessage::query()->create([
        'conversation_id' => $conv->id,
        'role' => AiMessage::ROLE_ASSISTANT,
        'content' => 'Child A',
        'status' => 'answered',
        'source_pages' => [1],
        'prompt_version' => 1,
        'model' => 'fake',
    ]);

    $endpoint = route('assistant.feedback', ['lesson' => $lesson]);

    // Parent acting for child posts feedback
    $this->actingAs($parent)->withSession([ActingLearner::SESSION_KEY => $student->id]);
    $response = $this->postJson($endpoint, [
        'message_id' => $assistantMsg->id,
        'type' => AiFeedback::UNDERSTOOD,
    ]);

    $response->assertOk();

    // Audit carries acting_for
    $audit = Activity::where('log_name', 'audit')->where('event', 'ai.feedback_recorded')->latest('id')->first();
    expect($audit)->not->toBeNull();
    expect($audit?->properties['context']['acting_for'])->toBe([
        'type' => User::class,
        'id' => $student->id,
    ]);
})->group('T-P7-04', 'A1');

it('resolves correct notification URLs for learner and staff', function (): void {
    $request = new HelpRequest;
    $request->id = 77;

    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');

    expect(HelpLinks::learner(77))->toBe(route('help.show', ['helpRequest' => 77]));
    expect(HelpLinks::staff($admin, 77))->toBe(url('/admin/help-requests/77'));
    expect(HelpLinks::staff($instructor, 77))->toBe(url('/instructor/help-requests/77'));
})->group('T-P7-04', 'unit');
