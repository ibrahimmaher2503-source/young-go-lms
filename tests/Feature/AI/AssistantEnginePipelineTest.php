<?php

// T-P7-05 | AI spec §7 §8 §9 §13 | A1 A2

declare(strict_types=1);

use App\Domain\AI\Actions\AskLessonAssistant;
use App\Domain\AI\Assistant\AssistantMode;
use App\Domain\AI\Assistant\AssistantOutcome;
use App\Domain\AI\Assistant\AssistantQuestion;
use App\Domain\AI\Assistant\StudentHash;
use App\Domain\AI\Contracts\AiProviderInterface;
use App\Domain\AI\Contracts\ModerationClassifier;
use App\Domain\AI\Providers\FakeAiProvider;
use App\Domain\AI\Providers\KeywordModerationClassifier;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Entitlements\Contracts\LessonAccessChecker;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Settings\PlatformFlag;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiPromptVersion;
use App\Models\AiQuotaCounter;
use App\Models\AiUsage;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\LessonDocumentPage;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    app()->instance(ModerationClassifier::class, new KeywordModerationClassifier);

    if (AiPromptVersion::current() === null) {
        $migration = require database_path('migrations/2026_11_17_200100_seed_ai_prompt_version.php');
        $migration->up();
    }
});

/**
 * Creates an entitled learner with active READY document for regression testing of the extracted engine.
 *
 * @return array{student: User, lesson: Lesson, course: Course, document: LessonCurriculumDocument}
 */
function aepReady(): array
{
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');

    $course = Course::query()->create([
        'title' => ['en' => 'Watercolor Painting', 'ar' => 'الرسم بالألوان المائية'],
        'slug_ar' => 'watercolor-ar-'.uniqid(),
        'slug_en' => 'watercolor-en-'.uniqid(),
        'status' => 'published',
        'ai_enabled' => true,
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);

    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Intro', 'ar' => 'مقدمة']]);
    $item = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Primary Colors', 'ar' => 'الألوان الأساسية']]);
    /** @var Lesson $lesson */
    $lesson = $item->itemable;
    $lesson->forceFill(['type' => 'video'])->save();
    $item->update(['ai_enabled' => true]);

    $document = LessonCurriculumDocument::query()->create([
        'lesson_id' => $lesson->id,
        'version' => 1,
        'path' => 'curriculum/watercolor.pdf',
        'sha256' => hash('sha256', 'wc-1'),
        'size' => 1024,
        'pages' => 2,
        'status' => LessonCurriculumDocument::STATUS_READY,
        'active' => true,
    ]);
    LessonDocumentPage::query()->create([
        'document_id' => $document->id,
        'page_no' => 1,
        'approved_text' => 'Red, blue, and yellow are primary colors. They form the foundation of all other pigments.',
    ]);
    LessonDocumentPage::query()->create([
        'document_id' => $document->id,
        'page_no' => 2,
        'approved_text' => 'Wash technique requires wet paper and loose brush strokes.',
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

    return compact('student', 'lesson', 'course', 'document');
}

it('answers question with verified quote and persists conversation, messages, usage, audit and commits quota', function (): void {
    $data = aepReady();
    $fake = new FakeAiProvider;
    app()->instance(AiProviderInterface::class, $fake);

    $fake->respondWith([
        'status' => 'answered',
        'answer' => 'Red, blue, and yellow are primary colors.',
        'source_pages' => [1],
        'support_quote' => 'Red, blue, and yellow are primary colors',
        'language' => 'en',
    ]);

    $askAction = app(AskLessonAssistant::class);
    $question = new AssistantQuestion(
        question: 'What are the primary colors?',
        locale: 'en',
        mode: AssistantMode::Normal,
        videoPosition: 45,
        conversationId: null,
    );

    $reply = $askAction($data['student'], $data['lesson'], $question);

    expect($reply->outcome)->toBe(AssistantOutcome::Answered)
        ->and($reply->message)->toBe('Red, blue, and yellow are primary colors.')
        ->and($reply->sourcePages)->toBe([1])
        ->and($reply->quoteVerified)->toBeTrue();

    // 1. Exactly one conversation persisted
    expect(AiConversation::query()->count())->toBe(1);
    /** @var AiConversation $conv */
    $conv = AiConversation::query()->first();
    expect($conv->student_id)->toBe($data['student']->id)
        ->and($conv->lesson_id)->toBe($data['lesson']->id)
        ->and($conv->course_id)->toBe($data['course']->id)
        ->and($conv->document_version)->toBe(1);

    // 2. User and Assistant messages persisted
    $messages = AiMessage::query()->where('conversation_id', $conv->id)->orderBy('id')->get();
    expect($messages)->toHaveCount(2);

    $userMsg = $messages->first();
    expect($userMsg->role)->toBe(AiMessage::ROLE_USER)
        ->and($userMsg->content)->toBe('What are the primary colors?');

    $assistantMsg = $messages->last();
    expect($assistantMsg->role)->toBe(AiMessage::ROLE_ASSISTANT)
        ->and($assistantMsg->content)->toBe('Red, blue, and yellow are primary colors.')
        ->and($assistantMsg->status)->toBe('answered')
        ->and($assistantMsg->source_pages)->toBe([1])
        ->and($assistantMsg->quote_verified)->toBeTrue();

    // 3. One ai_usage row persisted
    expect(AiUsage::query()->count())->toBe(1);
    /** @var AiUsage $usage */
    $usage = AiUsage::query()->first();
    expect($usage->student_hash)->toBe(StudentHash::of((int) $data['student']->id))
        ->and($usage->lesson_id)->toBe($data['lesson']->id)
        ->and($usage->result)->toBe('answered');

    // 4. One audit event recorded
    $audits = Activity::query()->where('event', 'ai.assistant_asked')->get();
    expect($audits)->toHaveCount(1);
    expect((int) $audits->first()->causer_id)->toBe($data['student']->id);

    // 5. Quota committed (2 quota counters: student_day and student_lesson_day)
    $quotaCount = AiQuotaCounter::query()->where('student_id', $data['student']->id)->sum('count');
    expect($quotaCount)->toBe(2);
})->group('T-P7-05', 'T-P7-01', 'A1');

it('retries on guard rejection twice, ends as fallback and refunds reserved quota', function (): void {
    $data = aepReady();
    $fake = new FakeAiProvider;
    app()->instance(AiProviderInterface::class, $fake);

    // Queue 2 invalid responses (schema violations)
    $fake->respondWith('{"status":"invalid_status"}');
    $fake->respondWith('{"status":"invalid_status_again"}');

    $askAction = app(AskLessonAssistant::class);
    $question = new AssistantQuestion(
        question: 'How do I paint?',
        locale: 'en',
        mode: AssistantMode::Normal,
        videoPosition: 10,
        conversationId: null,
    );

    $reply = $askAction($data['student'], $data['lesson'], $question);

    expect($reply->outcome)->toBe(AssistantOutcome::Fallback)
        ->and($reply->message)->toBe(__('ai.temporarily_unavailable'))
        ->and($reply->quoteVerified)->toBeFalse();

    // Provider was attempted twice
    expect($fake->requests())->toHaveCount(2);

    // Quota was reserved and then refunded: net count is 0
    $quotaCount = AiQuotaCounter::query()->where('student_id', $data['student']->id)->sum('count');
    expect($quotaCount)->toBe(0);

    // Assistant message recorded fallback
    $assistantMsg = AiMessage::query()->where('role', AiMessage::ROLE_ASSISTANT)->first();
    expect($assistantMsg->status)->toBe('fallback');
})->group('T-P7-05', 'T-P7-01', 'A1');

it('ends as not_in_source when quote fails to match cited page after retry', function (): void {
    $data = aepReady();
    $fake = new FakeAiProvider;
    app()->instance(AiProviderInterface::class, $fake);

    // Quote does not exist on page 1
    $mismatchedResponse = [
        'status' => 'answered',
        'answer' => 'Red, blue, and yellow are primary colors.',
        'source_pages' => [1],
        'support_quote' => 'Fabricated quote about purple and green',
        'language' => 'en',
    ];
    $fake->respondWith($mismatchedResponse);
    $fake->respondWith($mismatchedResponse);

    $askAction = app(AskLessonAssistant::class);
    $question = new AssistantQuestion(
        question: 'Are colors primary?',
        locale: 'en',
        mode: AssistantMode::Normal,
        videoPosition: 10,
        conversationId: null,
    );

    $reply = $askAction($data['student'], $data['lesson'], $question);

    expect($reply->outcome)->toBe(AssistantOutcome::NotInSource)
        ->and($reply->message)->toBe(__('ai.not_in_source'));

    $assistantMsg = AiMessage::query()->where('role', AiMessage::ROLE_ASSISTANT)->first();
    expect($assistantMsg->status)->toBe('not_in_source');

    // Quota was refunded because quote validation failed
    $quotaCount = AiQuotaCounter::query()->where('student_id', $data['student']->id)->sum('count');
    expect($quotaCount)->toBe(0);
})->group('T-P7-05', 'T-P7-01', 'A1');

it('handles provider exception by returning fallback with provider_error and refunding quota', function (): void {
    $data = aepReady();
    $fake = new FakeAiProvider;
    app()->instance(AiProviderInterface::class, $fake);

    $fake->failNext(new RuntimeException('Downstream API connection failure'));

    $askAction = app(AskLessonAssistant::class);
    $question = new AssistantQuestion(
        question: 'Tell me about colors',
        locale: 'en',
        mode: AssistantMode::Normal,
        videoPosition: 10,
        conversationId: null,
    );

    $reply = $askAction($data['student'], $data['lesson'], $question);

    expect($reply->outcome)->toBe(AssistantOutcome::Fallback)
        ->and($reply->message)->toBe(__('ai.temporarily_unavailable'));

    $assistantMsg = AiMessage::query()->where('role', AiMessage::ROLE_ASSISTANT)->first();
    expect($assistantMsg->status)->toBe('fallback')
        ->and($assistantMsg->guard_flags['reason'] ?? null)->toBe('provider_error');

    // Quota refunded
    $quotaCount = AiQuotaCounter::query()->where('student_id', $data['student']->id)->sum('count');
    expect($quotaCount)->toBe(0);
})->group('T-P7-05', 'T-P7-01', 'A1');

it('verifies engine request parameters: maxTokens 220, JSON schema, and userHash equal to StudentHash::of', function (): void {
    $data = aepReady();
    $fake = new FakeAiProvider;
    app()->instance(AiProviderInterface::class, $fake);

    $fake->respondWith([
        'status' => 'not_in_source',
        'answer' => '',
        'source_pages' => [],
        'support_quote' => '',
        'language' => 'en',
    ]);

    $askAction = app(AskLessonAssistant::class);
    $question = new AssistantQuestion(
        question: 'What is watercolor?',
        locale: 'en',
        mode: AssistantMode::Normal,
        videoPosition: 10,
        conversationId: null,
    );

    $askAction($data['student'], $data['lesson'], $question);

    $requests = $fake->requests();
    expect($requests)->toHaveCount(1);

    $req = $requests[0];
    expect($req->maxTokens)->toBe(220)
        ->and($req->userHash)->toBe(StudentHash::of((int) $data['student']->id));

    $schema = $req->responseSchema;
    expect($schema['type'])->toBe('object')
        ->and($schema['required'])->toBe(['status', 'answer', 'source_pages', 'support_quote', 'language'])
        ->and($schema['additionalProperties'])->toBeFalse()
        ->and(array_keys($schema['properties']))->toContain('status', 'answer', 'source_pages', 'support_quote', 'language');
})->group('T-P7-05', 'T-P7-01', 'A1');
