<?php

declare(strict_types=1);

use App\Domain\AI\Admin\Actions\DisableAssistantGlobally;
use App\Domain\AI\Admin\Actions\EnableAssistantGlobally;
use App\Domain\AI\Assistant\AssistantOutcome;
use App\Domain\AI\Assistant\StyleTier;
use App\Domain\AI\Console\Actions\AskInTestConsole;
use App\Domain\AI\Contracts\AiProviderInterface;
use App\Domain\AI\Help\Actions\ReplyToHelpRequest;
use App\Domain\AI\Providers\FakeAiProvider;
use App\Domain\AI\Queries\UsageReport;
use App\Domain\AI\UnavailableReason;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Media\Actions\AttachLessonMedia;
use App\Domain\Settings\AiSettings;
use App\Domain\Settings\PlatformFlag;
use App\Models\AiConversation;
use App\Models\AiFeedback;
use App\Models\AiMessage;
use App\Models\AiPromptVersion;
use App\Models\AiQuotaCounter;
use App\Models\AiUsage;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\HelpRequest;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\LessonDocumentPage;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    config(['ai.enabled' => true, 'ai.features.assistant' => true, 'ai.provider' => 'fake', 'ai.adult_age' => 18]);

    if (AiPromptVersion::current() === null) {
        $migration = require database_path('migrations/2026_11_17_200100_seed_ai_prompt_version.php');
        $migration->up();
    }
});

/** Acts as one user: a fresh session per actor, the way a person switching accounts gets one. */
function p7dAs($test, User $user)
{
    $test->flushSession();
    app('auth')->forgetGuards();

    return $test->actingAs($user);
}

/** The assistant answers with a stream when it has an answer, and with JSON when it refuses or is unavailable. */
function p7dAsk($test, User $user, Lesson $lesson, string $question): array
{
    p7dAs($test, $user);
    $response = $test->post(route('assistant.ask', ['lesson' => $lesson->id]), ['question' => $question]);

    if (str_contains((string) $response->headers->get('Content-Type'), 'text/event-stream')) {
        $response->assertOk();
        $events = array_values(array_filter(preg_split("/\n\n/", trim($response->streamedContent())) ?: []));
        $result = null;
        foreach ($events as $event) {
            if (str_starts_with($event, "event: result\n")) {
                $result = json_decode(substr($event, strlen("event: result\ndata: ")), true, 512, JSON_THROW_ON_ERROR);
            }
        }

        return ['status' => 200, 'body' => $result ?? throw new RuntimeException('Assistant stream had no result event.')];
    }

    return ['status' => $response->getStatusCode(), 'body' => $response->json()];
}

/**
 * Builds one published course with two recorded lessons, each with its own approved PDF text. The learner is adult by default
 * and holds a free entitlement. The provider is the fake, bound through the container so no real provider can be reached.
 *
 * @return array<string, mixed>
 */
function p7dSetup(): array
{
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $superAdmin = User::factory()->create(['status' => 'active'])->assignRole('super_admin');
    $student = User::factory()->create(['status' => 'active', 'birth_year' => 1995, 'birth_month' => 5])->assignRole('student');
    $minor = User::factory()->create(['status' => 'active', 'birth_year' => now()->year - 15, 'birth_month' => 1])->assignRole('student');

    $course = Course::query()->create([
        'title' => ['en' => 'P7 Colour Course', 'ar' => 'دورة الألوان'],
        'slug_ar' => 'p7d-ar-'.uniqid(), 'slug_en' => 'p7d-en-'.uniqid(),
        'status' => 'published', 'access_mode' => Course::ACCESS_FREE, 'ai_enabled' => true,
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Unit 1', 'ar' => 'الوحدة 1']]);
    $section->update(['published' => true]);

    $texts = [
        'colours' => 'Red, yellow, and blue are the traditional primary colors used in pigment mixing.',
        'cells' => 'Mitochondria produce ATP, the energy currency of the cell.',
    ];
    $lessons = [];
    $items = [];
    foreach ($texts as $key => $text) {
        $item = app(ManageLessons::class)->add($instructor, $section, ['type' => 'video', 'title' => ['en' => "P7 {$key}", 'ar' => "درس {$key}"]]);
        /** @var Lesson $lesson */
        $lesson = $item->itemable;
        app(AttachLessonMedia::class)($instructor, $lesson, ['provider' => 'youtube', 'url' => 'https://youtu.be/dQw4w9WgXcQ']);
        $items[] = $item;

        $document = LessonCurriculumDocument::query()->create([
            'lesson_id' => $lesson->id, 'version' => 1, 'path' => "curriculum/p7d-{$key}.pdf", 'sha256' => hash('sha256', "p7d-{$key}"),
            'size' => 1024, 'pages' => 1, 'status' => LessonCurriculumDocument::STATUS_READY, 'active' => true,
        ]);
        LessonDocumentPage::query()->create(['document_id' => $document->id, 'page_no' => 1, 'approved_text' => $text]);
        $lessons[$key] = ['lesson' => $lesson, 'document' => $document, 'text' => $text];
    }
    app(SetCurriculumStatus::class)($instructor, $course, array_map(static fn ($item) => $item->id, $items), CurriculumItem::STATUS_PUBLISHED);

    app(GrantEntitlement::class)($student, $course, EntitlementSource::Free);
    app(GrantEntitlement::class)($minor, $course, EntitlementSource::Free);

    // The minor needs a verified guardian who consented to AI; the minors flag stays off for the whole journey.
    $parent = User::factory()->create();
    GuardianChildLink::query()->create([
        'guardian_id' => $parent->id, 'child_id' => $minor->id, 'status' => GuardianChildLink::STATUS_VERIFIED,
        'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT, 'verified_at' => now(),
        'ai_consent_granted_at' => now(), 'ai_consent_version' => 'v1',
    ]);

    Feature::for(null)->activate(PlatformFlag::AiAssistant->value);
    Feature::for(null)->deactivate(PlatformFlag::AiMinors->value);

    $provider = new FakeAiProvider;
    app()->instance(AiProviderInterface::class, $provider);

    return [
        'instructor' => $instructor, 'superAdmin' => $superAdmin, 'student' => $student, 'minor' => $minor,
        'provider' => $provider, 'colours' => $lessons['colours'], 'cells' => $lessons['cells'],
    ];
}

/**
 * T-P7-MG demo script with the fake provider only: an eligible adult asks on an approved-PDF lesson and gets a cited answer; an
 * out-of-lesson question is refused; another lesson's text never reaches the provider; a minor is not eligible while ai.minors
 * is off; the daily quota returns 429; the learner's "still not understood" raises a help request that the instructor answers;
 * the admin switch disables and re-enables the assistant; the instructor test console answers and leaves no learner report data.
 */
it('runs the P7 demo script end to end', function (): void {
    $s = p7dSetup();
    /** @var FakeAiProvider $provider */
    $provider = $s['provider'];
    /** @var Lesson $lesson */
    $lesson = $s['colours']['lesson'];
    $student = $s['student'];

    $settings = app(AiSettings::class);
    $settings->questions_per_student_day = 3;
    $settings->questions_per_student_lesson_day = 10;
    $settings->save();

    // 1. An eligible adult asks about the lesson and gets a streamed, cited answer whose quote is verified on page 1.
    $provider->respondWith([
        'status' => 'answered',
        'answer' => 'Red, yellow and blue are the primary colours.',
        'source_pages' => [1],
        'support_quote' => 'Red, yellow, and blue are the traditional primary colors',
        'language' => 'en',
    ]);
    $first = p7dAsk($this, $student, $lesson, 'Which colours are primary?');
    expect($first['status'])->toBe(200)
        ->and($first['body']['state'])->toBe(AssistantOutcome::Answered->value)
        ->and($first['body']['source_pages'])->toBe([1])
        ->and($first['body']['quote_verified'])->toBeTrue();
    $answeredMessageId = (int) $first['body']['message_id'];
    expect(str_contains($provider->requests()[0]->cacheablePrefix, $s['colours']['text']))->toBeTrue();

    // 2. A question the lesson does not answer is refused, and the other lesson's text is never sent to the provider.
    $provider->respondWith(['status' => 'not_in_source', 'answer' => '', 'source_pages' => [], 'support_quote' => '', 'language' => 'en']);
    $otherTopic = p7dAsk($this, $student, $lesson, 'Which organelle produces ATP?');
    expect($otherTopic['status'])->toBe(200)->and($otherTopic['body']['state'])->toBe(AssistantOutcome::NotInSource->value);
    $sent = $provider->requests()[1];
    $payload = $sent->cacheablePrefix.json_encode($sent->turns, JSON_THROW_ON_ERROR);
    expect(str_contains($payload, $s['cells']['text']))->toBeFalse()
        ->and(str_contains($payload, 'Mitochondria'))->toBeFalse();

    $provider->respondWith(['status' => 'not_in_source', 'answer' => '', 'source_pages' => [], 'support_quote' => '', 'language' => 'en']);
    $offTopic = p7dAsk($this, $student, $lesson, 'What is the capital of France?');
    expect($offTopic['body']['state'])->toBe(AssistantOutcome::NotInSource->value);

    // 3. The third question of the day is the last one allowed; the fourth returns 429 with reset_at and never reaches the provider.
    p7dAsk($this, $student, $lesson, 'What are complementary colours?');
    $callsBeforeLimit = count($provider->requests());
    $limited = p7dAsk($this, $student, $lesson, 'Does green count as primary?');
    expect($limited['status'])->toBe(429)
        ->and($limited['body']['state'])->toBe(UnavailableReason::QuotaExhausted->value)
        ->and($limited['body'])->toHaveKey('reset_at')
        ->and(count($provider->requests()))->toBe($callsBeforeLimit);
    expect(AiQuotaCounter::query()->where('student_id', $student->id)->where('scope', 'student_day')->value('count'))->toBe(3);

    // 4. The learner taps "still not understood" on the answer: exactly one help request is created and routed to the instructor.
    $feedback = p7dAs($this, $student)->postJson(route('assistant.feedback', ['lesson' => $lesson->id]), [
        'message_id' => $answeredMessageId, 'type' => AiFeedback::NOT_UNDERSTOOD,
    ]);
    $feedback->assertCreated()->assertJsonPath('state', 'request_created');
    $requestId = (int) $feedback->json('request_id');
    p7dAs($this, $student)->postJson(route('assistant.feedback', ['lesson' => $lesson->id]), [
        'message_id' => $answeredMessageId, 'type' => AiFeedback::NOT_UNDERSTOOD,
    ])->assertOk()->assertJsonPath('state', 'request_exists');
    expect(HelpRequest::query()->where('ai_message_id', $answeredMessageId)->count())->toBe(1);

    // The instructor answers in the thread and the learner sees the reply.
    $helpRequest = HelpRequest::query()->findOrFail($requestId);
    app(ReplyToHelpRequest::class)($s['instructor'], $helpRequest, 'Start from the three pigment primaries and mix pairs.');
    p7dAs($this, $student)->get(route('help.show', ['helpRequest' => $requestId]))
        ->assertOk()->assertSee('Start from the three pigment primaries');

    // 5. A minor is not eligible while ai.minors is off: refused before any provider call.
    $callsBeforeMinor = count($provider->requests());
    $minor = p7dAsk($this, $s['minor'], $lesson, 'Which colours are primary?');
    expect($minor['status'])->toBe(403)
        ->and($minor['body']['state'])->toBe(UnavailableReason::MinorsOff->value)
        ->and(count($provider->requests()))->toBe($callsBeforeMinor);

    // 6. Admin AI controls: the super admin disables the assistant and a learner is refused at once.
    $disable = app(DisableAssistantGlobally::class);
    $disable($s['superAdmin']);
    $callsBeforeOff = count($provider->requests());
    $off = p7dAsk($this, $student, $lesson, 'Which colours are secondary?');
    expect($off['status'])->toBe(403)
        ->and($off['body']['state'])->toBe(UnavailableReason::GlobalOff->value)
        ->and(count($provider->requests()))->toBe($callsBeforeOff);

    // Re-enabling needs an approval reference; the daily allowance is raised by the admin so the same learner can ask again.
    app(EnableAssistantGlobally::class)($s['superAdmin'], 'P7-DEMO-APPROVAL');
    $settings->questions_per_student_day = 10;
    $settings->save();
    $provider->respondWith([
        'status' => 'answered', 'answer' => 'Secondary colours mix two primaries.', 'source_pages' => [1],
        'support_quote' => 'Red, yellow, and blue are the traditional primary colors', 'language' => 'en',
    ]);
    $back = p7dAsk($this, $student, $lesson, 'Which colours are secondary?');
    expect($back['status'])->toBe(200)->and($back['body']['state'])->toBe(AssistantOutcome::Answered->value);

    // 7. The instructor test console answers the lesson's own document, and the run is excluded from every usage report.
    $provider->respondWith([
        'status' => 'answered', 'answer' => 'Red, yellow and blue.', 'source_pages' => [1],
        'support_quote' => 'Red, yellow, and blue are the traditional primary colors', 'language' => 'en',
    ]);
    $report = fn (): int => (int) app(UsageReport::class)->for(now()->subDay(), now()->addDay())['totals']['requests'];
    $usageBefore = AiUsage::query()->count();
    $conversationsBefore = AiConversation::query()->count();
    $messagesBefore = AiMessage::query()->count();
    $reportBefore = $report();

    $console = app(AskInTestConsole::class)($s['instructor'], $lesson, 'Which colours are primary?', 'en', StyleTier::Adults);
    expect($console->outcome->value)->toBe(AssistantOutcome::Answered->value)
        ->and(AiUsage::query()->count())->toBe($usageBefore)
        ->and(AiConversation::query()->count())->toBe($conversationsBefore)
        ->and(AiMessage::query()->count())->toBe($messagesBefore)
        ->and($report())->toBe($reportBefore);
})->group('T-P7-MG', 'A1');
