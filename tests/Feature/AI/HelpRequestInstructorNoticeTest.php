<?php

// T-P7-MG | AI spec §12 §16: "I still don't understand" notifies the responsible instructor | A1

declare(strict_types=1);

use App\Domain\AI\Help\HelpRequestNotices;
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
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    config(['ai.adult_age' => null]);
    $this->withoutVite();
});

/** @return array{0: User, 1: Lesson, 2: Course, 3: User} learner, lesson, course, responsible instructor */
function hinSetup(): array
{
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $course = Course::query()->create([
        'title' => ['en' => 'Notice Course', 'ar' => 'دورة الإشعار'],
        'slug_ar' => 'hin-ar-'.uniqid(), 'slug_en' => 'hin-en-'.uniqid(),
        'status' => 'published', 'ai_enabled' => true,
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);

    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Section 1']]);
    $item = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Lesson 1'], 'type' => 'video']);
    /** @var Lesson $lesson */
    $lesson = $item->itemable;
    $lesson->forceFill(['type' => 'video'])->save();

    LessonCurriculumDocument::query()->create([
        'lesson_id' => $lesson->id, 'version' => 1, 'path' => 'curriculum/hin.pdf',
        'sha256' => str_repeat('a', 64), 'size' => 1024, 'pages' => 2,
        'status' => LessonCurriculumDocument::STATUS_READY, 'active' => true,
    ]);

    $student = User::factory()->create(['status' => 'active', 'birth_year' => 1995, 'birth_month' => 5])->assignRole('student');
    $parent = User::factory()->create(['status' => 'active'])->assignRole('parent');
    GuardianChildLink::query()->create([
        'guardian_id' => $parent->id, 'child_id' => $student->id, 'status' => GuardianChildLink::STATUS_VERIFIED,
        'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT, 'verified_at' => now(),
        'ai_consent_granted_at' => now(), 'ai_consent_version' => 'v1',
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

    return [$student, $lesson->refresh(), $course, $instructor];
}

/** Seeds one answered assistant turn for the learner and returns the assistant message. */
function hinAnswer(User $student, Course $course, Lesson $lesson): AiMessage
{
    $conversation = AiConversation::query()->create([
        'student_id' => $student->id, 'course_id' => $course->id, 'lesson_id' => $lesson->id,
        'document_version' => 1, 'locale' => 'en', 'style_tier' => 'standard', 'video_position' => 75, 'status' => 'active',
    ]);
    AiMessage::query()->create([
        'conversation_id' => $conversation->id, 'role' => AiMessage::ROLE_USER, 'content' => 'What is composition?',
    ]);

    return AiMessage::query()->create([
        'conversation_id' => $conversation->id, 'role' => AiMessage::ROLE_ASSISTANT,
        'content' => 'Composition is the arrangement of visual elements.', 'status' => 'answered',
        'source_pages' => [1], 'guard_flags' => null, 'prompt_version' => 1, 'model' => 'fake-model', 'cache_hit' => false,
    ]);
}

it('sends the responsible instructor the new-help-request notification when the learner says they still do not understand', function (): void {
    [$student, $lesson, $course, $instructor] = hinSetup();
    $answer = hinAnswer($student, $course, $lesson);

    $this->actingAs($student)->postJson(route('assistant.feedback', ['lesson' => $lesson]), [
        'message_id' => $answer->id,
        'type' => AiFeedback::NOT_UNDERSTOOD,
    ])->assertStatus(201);

    $request = HelpRequest::query()->firstOrFail();
    expect($request->assigned_instructor_id)->toBe($instructor->id);

    $notices = NotificationDispatch::query()->where('dedupe_key', 'help-request-new:'.$request->id)->get();
    expect($notices->pluck('recipient_id')->all())->toContain($instructor->id)
        ->and($notices->pluck('recipient_id')->all())->not->toContain($student->id);
})->group('T-P7-MG');

it('does not notify the instructor again when the same answer is tapped twice', function (): void {
    [$student, $lesson, $course, $instructor] = hinSetup();
    $answer = hinAnswer($student, $course, $lesson);
    $endpoint = route('assistant.feedback', ['lesson' => $lesson]);

    $this->actingAs($student)->postJson($endpoint, ['message_id' => $answer->id, 'type' => AiFeedback::NOT_UNDERSTOOD])->assertStatus(201);
    $this->actingAs($student)->postJson($endpoint, ['message_id' => $answer->id, 'type' => AiFeedback::NOT_UNDERSTOOD])->assertOk();

    $request = HelpRequest::query()->firstOrFail();
    expect(HelpRequest::query()->count())->toBe(1);
    expect(NotificationDispatch::query()->where('dedupe_key', 'help-request-new:'.$request->id)
        ->where('recipient_id', $instructor->id)->count())->toBe(1);
})->group('T-P7-MG');

it('dispatches the instructor notice through the help request notices service', function (): void {
    [$student, $lesson, $course, $instructor] = hinSetup();
    $answer = hinAnswer($student, $course, $lesson);

    $this->actingAs($student)->postJson(route('assistant.feedback', ['lesson' => $lesson]), [
        'message_id' => $answer->id,
        'type' => AiFeedback::NOT_UNDERSTOOD,
    ])->assertStatus(201);

    $request = HelpRequest::query()->firstOrFail();
    expect(app(HelpRequestNotices::class))->toBeInstanceOf(HelpRequestNotices::class);
    expect(NotificationDispatch::query()->where('dedupe_key', 'help-request-new:'.$request->id)
        ->where('recipient_id', $instructor->id)->exists())->toBeTrue();
})->group('T-P7-MG');
