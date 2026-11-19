<?php

declare(strict_types=1);

// T-DECISIONS-2 | O-028 | minor help request path (safeguarding notice) and the adult path without the flag

use App\Domain\AI\Help\HelpRequestStatus;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Entitlements\Contracts\LessonAccessChecker;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Settings\PlatformFlag;
use App\Models\AiConversation;
use App\Models\AiFeedback;
use App\Models\AiMessage;
use App\Models\Course;
use App\Models\HelpRequest;
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
    config(['ai.adult_age' => null]);
    $this->withoutVite();
});

/**
 * A published AI course with one ready lesson and a manage-scope instructor (so routing reaches a person).
 *
 * @return array{0: Lesson, 1: Course}
 */
function dmhCourse(): array
{
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $course = Course::query()->create([
        'title' => ['en' => 'Minor Help Course', 'ar' => 'دورة مساعدة القاصرين'],
        'slug_ar' => 'dmh-ar-'.uniqid(),
        'slug_en' => 'dmh-en-'.uniqid(),
        'status' => 'published',
        'ai_enabled' => true,
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);

    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Section 1']]);
    $item = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Lesson 1'], 'type' => 'video']);
    /** @var Lesson $lesson */
    $lesson = $item->itemable;
    $lesson->forceFill(['type' => 'video'])->save();

    LessonCurriculumDocument::query()->create([
        'lesson_id' => $lesson->id,
        'version' => 1,
        'path' => 'curriculum/dmh.pdf',
        'sha256' => str_repeat('b', 64),
        'size' => 1024,
        'pages' => 2,
        'status' => LessonCurriculumDocument::STATUS_READY,
        'active' => true,
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

    return [$lesson->refresh(), $course];
}

/** A learner linked to a verified parent with AI consent: a minor by GuardianAccess::isMinor. */
function dmhLinkedLearner(): User
{
    $student = User::factory()->create(['status' => 'active', 'email' => null, 'email_verified_at' => null])->assignRole('student');
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

    return $student;
}

/** An adult learner: a real email and a birth year well past the adult age, with no guardian link. */
function dmhAdultLearner(): User
{
    return User::factory()->create([
        'status' => 'active', 'email' => 'dmh-adult-'.uniqid().'@example.test', 'email_verified_at' => now(), 'birth_year' => 1990,
    ])->assignRole('student');
}

/** One learner question and the assistant answer, in a conversation for the lesson. @return array{0: AiConversation, 1: AiMessage} */
function dmhTurn(User $student, Course $course, Lesson $lesson): array
{
    $conversation = AiConversation::query()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'lesson_id' => $lesson->id,
        'document_version' => 1,
        'locale' => 'en',
        'style_tier' => 'standard',
        'video_position' => 60,
        'status' => 'active',
    ]);
    AiMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => AiMessage::ROLE_USER,
        'content' => 'Why does the sky look blue?',
    ]);
    $answer = AiMessage::query()->create([
        'conversation_id' => $conversation->id,
        'role' => AiMessage::ROLE_ASSISTANT,
        'content' => 'Shorter wavelengths scatter more.',
        'status' => 'answered',
        'source_pages' => [1],
        'guard_flags' => null,
        'prompt_version' => 1,
        'model' => 'fake-model',
        'cache_hit' => false,
    ]);

    return [$conversation, $answer];
}

function dmhSafeguardingAudits(int $requestId): int
{
    return Activity::query()->where('log_name', 'audit')->where('event', 'safeguarding.notified')
        ->get()
        ->filter(fn (Activity $activity): bool => ($activity->properties['context']['source'] ?? null) === 'minor_help_request'
            && (int) ($activity->properties['context']['case_id'] ?? 0) === $requestId)
        ->count();
}

it('notifies the safeguarding group once for a minor help request, and repeated taps send nothing more', function (): void {
    [$lesson, $course] = dmhCourse();
    $student = dmhLinkedLearner();
    [, $answer] = dmhTurn($student, $course, $lesson);

    $endpoint = route('assistant.feedback', ['lesson' => $lesson]);

    $first = $this->actingAs($student)->postJson($endpoint, ['message_id' => $answer->id, 'type' => AiFeedback::NOT_UNDERSTOOD]);
    $first->assertStatus(201)->assertJson(['state' => 'request_created']);
    $requestId = (int) $first->json('request_id');

    expect(dmhSafeguardingAudits($requestId))->toBe(1);

    $this->actingAs($student)->postJson($endpoint, ['message_id' => $answer->id, 'type' => AiFeedback::NOT_UNDERSTOOD])
        ->assertOk()
        ->assertJson(['request_id' => $requestId]);

    expect(dmhSafeguardingAudits($requestId))->toBe(1)
        ->and(HelpRequest::query()->where('ai_message_id', $answer->id)->count())->toBe(1);
})->group('T-DECISIONS-2', 'O-028');

it('stores the minor help request with its conversation, lesson, course and AI answer rows and the new status', function (): void {
    [$lesson, $course] = dmhCourse();
    $student = dmhLinkedLearner();
    [$conversation, $answer] = dmhTurn($student, $course, $lesson);

    $response = $this->actingAs($student)->postJson(route('assistant.feedback', ['lesson' => $lesson]), [
        'message_id' => $answer->id, 'type' => AiFeedback::NOT_UNDERSTOOD,
    ])->assertStatus(201);

    $request = HelpRequest::query()->findOrFail($response->json('request_id'));

    expect($request->student_id)->toBe($student->id)
        ->and($request->conversation_id)->toBe($conversation->id)
        ->and($request->lesson_id)->toBe($lesson->id)
        ->and($request->course_id)->toBe($course->id)
        ->and($request->ai_message_id)->toBe($answer->id)
        ->and($request->status)->toBe(HelpRequestStatus::New)
        ->and($request->question)->toBe('Why does the sky look blue?')
        ->and($request->ai_answer)->toBe('Shorter wavelengths scatter more.');
})->group('T-DECISIONS-2', 'O-028');

it('writes no safeguarding notice for an adult learner with no guardian link, but still creates the help request', function (): void {
    [$lesson, $course] = dmhCourse();
    $adult = dmhAdultLearner();
    [$conversation, $answer] = dmhTurn($adult, $course, $lesson);

    $response = $this->actingAs($adult)->postJson(route('assistant.feedback', ['lesson' => $lesson]), [
        'message_id' => $answer->id, 'type' => AiFeedback::NOT_UNDERSTOOD,
    ])->assertStatus(201)->assertJson(['state' => 'request_created']);

    $requestId = (int) $response->json('request_id');
    $request = HelpRequest::query()->findOrFail($requestId);

    expect($request->student_id)->toBe($adult->id)
        ->and($request->conversation_id)->toBe($conversation->id)
        ->and($request->course_id)->toBe($course->id)
        ->and($request->lesson_id)->toBe($lesson->id)
        ->and($request->ai_message_id)->toBe($answer->id)
        ->and(dmhSafeguardingAudits($requestId))->toBe(0);
})->group('T-DECISIONS-2', 'O-028');
