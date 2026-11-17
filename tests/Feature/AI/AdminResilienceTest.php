<?php

// T-P7-06 | AI spec §11, §14, §18 | A1 A2 A3

declare(strict_types=1);

use App\Domain\AI\Actions\AskLessonAssistant;
use App\Domain\AI\Admin\Actions\ResetProviderCircuit;
use App\Domain\AI\Admin\Actions\SaveAssistantProvider;
use App\Domain\AI\AiProviderNotConfigured;
use App\Domain\AI\Assistant\AssistantOutcome;
use App\Domain\AI\Assistant\AssistantQuestion;
use App\Domain\AI\Contracts\AiProviderInterface;
use App\Domain\AI\Dto\AiCompletionRequest;
use App\Domain\AI\Dto\AiCompletionResult;
use App\Domain\AI\Dto\AiUsage;
use App\Domain\AI\Dto\OcrResult;
use App\Domain\AI\Dto\ProviderHealth;
use App\Domain\AI\Providers\FakeAiProvider;
use App\Domain\AI\Providers\NullAiProvider;
use App\Domain\AI\Queries\ProviderHealthReport;
use App\Domain\AI\Resilience\AiProviderCircuitOpen;
use App\Domain\AI\Resilience\CircuitBreaker;
use App\Domain\AI\Resilience\CircuitState;
use App\Domain\AI\Resilience\ProviderRegistry;
use App\Domain\AI\Resilience\ResilientProvider;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\Contracts\LessonAccessChecker;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Media\SignedFileUrl;
use App\Domain\Settings\AiSettings;
use App\Domain\Settings\PlatformFlag;
use App\Models\AiPromptVersion;
use App\Models\AiQuotaCounter;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\LessonDocumentPage;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Laravel\Pennant\Feature;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Cache::flush();
    config(['ai.adult_age' => 18]);
});

/**
 * @return array{0: User, 1: Lesson, 2: Course, 3: mixed, 4: User, 5: LessonCurriculumDocument}
 */
function tp706ResilienceReady(): array
{
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $course = Course::query()->create([
        'title' => ['en' => 'Digital Art AI', 'ar' => 'فن رقمي بالذكاء'],
        'slug_ar' => 'da-ar-'.uniqid(),
        'slug_en' => 'da-en-'.uniqid(),
        'status' => 'published',
        'access_mode' => 'free',
        'ai_enabled' => true,
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);

    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Section 1', 'ar' => 'الوحدة الأولى']]);
    $section->update(['published' => true]);
    $item = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Lesson 1', 'ar' => 'الدرس الأول'], 'type' => 'video']);
    $item->update(['ai_enabled' => true]);
    app(SetCurriculumStatus::class)($instructor, $course, [$item->id], 'published');

    /** @var Lesson $lesson */
    $lesson = $item->itemable;
    $lesson->forceFill(['type' => 'video'])->save();

    Storage::fake('local');
    Storage::disk('local')->put('curriculum/guide.pdf', '%PDF-1.4 dummy content');

    $doc = LessonCurriculumDocument::query()->create([
        'lesson_id' => $lesson->id,
        'version' => 1,
        'path' => 'curriculum/guide.pdf',
        'sha256' => str_repeat('a', 64),
        'size' => 1024,
        'pages' => 2,
        'status' => LessonCurriculumDocument::STATUS_READY,
        'active' => true,
    ]);

    LessonDocumentPage::query()->create([
        'document_id' => $doc->id,
        'page_no' => 1,
        'raw_text' => 'Page 1 raw text',
        'approved_text' => 'Composition and perspective are essential in visual drawing.',
        'ocr_used' => false,
    ]);

    LessonDocumentPage::query()->create([
        'document_id' => $doc->id,
        'page_no' => 2,
        'raw_text' => 'Page 2 raw text',
        'approved_text' => 'Shading techniques include hatching, cross-hatching and blending.',
        'ocr_used' => false,
    ]);

    $student = User::factory()->create([
        'status' => 'active',
        'birth_year' => 1995,
        'birth_month' => 5,
    ])->assignRole('student');

    Feature::for(null)->activate(PlatformFlag::AiAssistant->value);
    Feature::for(null)->activate(PlatformFlag::AiMinors->value);

    app()->bind(LessonAccessChecker::class, fn () => new class implements LessonAccessChecker
    {
        public function canAccessLessonFiles(User $user, Lesson $lesson): bool
        {
            return true;
        }
    });

    app(GrantEntitlement::class)($student, $course, EntitlementSource::Free);

    return [$student, $lesson->refresh(), $course, $item, $instructor, $doc];
}

it('opens circuit after N consecutive failures through AskLessonAssistant and routes to fallback state with zero provider calls', function (): void {
    Exceptions::fake();
    [$student, $lesson, $course, $item, $instructor, $doc] = tp706ResilienceReady();

    $settings = app(AiSettings::class);
    $settings->breaker_failures = 3;
    $settings->breaker_window_seconds = 120;
    $settings->breaker_cool_off_seconds = 60;
    $settings->save();

    $fake = new FakeAiProvider;
    app()->instance(AiProviderInterface::class, $fake);

    // Three consecutive failures
    $fake->failNext(new RuntimeException('Provider breakdown 1'));
    $fake->failNext(new RuntimeException('Provider breakdown 2'));
    $fake->failNext(new RuntimeException('Provider breakdown 3'));

    $ask = app(AskLessonAssistant::class);

    for ($i = 1; $i <= 3; $i++) {
        $reply = $ask($student, $lesson, new AssistantQuestion('What is composition?', 'en', 0));
        expect($reply->outcome)->toBe(AssistantOutcome::Fallback)
            ->and($reply->message)->toBe(__('ai.temporarily_unavailable'));
    }

    expect($fake->requests())->toHaveCount(3);

    $breaker = app(CircuitBreaker::class);
    expect($breaker->state('fake')->state)->toBe(CircuitState::OPEN);

    // Initial quota counters before the fourth ask
    $countersBefore = AiQuotaCounter::query()->where('student_id', $student->id)->where('period', now()->toDateString())->pluck('count', 'scope')->toArray();

    // Fourth ask: circuit is open, so zero new provider calls
    $reply4 = $ask($student, $lesson, new AssistantQuestion('What is composition?', 'en', 0));
    expect($reply4->outcome)->toBe(AssistantOutcome::Fallback)
        ->and($reply4->message)->toBe(__('ai.temporarily_unavailable'))
        ->and($fake->requests())->toHaveCount(3); // count unchanged!

    // Assert AiProviderCircuitOpen was NOT reported
    Exceptions::assertNotReported(AiProviderCircuitOpen::class);

    // Quota was refunded: counters unchanged
    $countersAfter = AiQuotaCounter::query()->where('student_id', $student->id)->where('period', now()->toDateString())->pluck('count', 'scope')->toArray();
    expect($countersAfter)->toBe($countersBefore);

    // Lesson player route and PDF download route still return 200
    $this->actingAs($student);
    $lessonResponse = $this->get('/learn/'.$course->id.'/'.$item->id);
    $lessonResponse->assertOk();

    $pdfUrl = app(SignedFileUrl::class)->for($student, $doc);
    $pdfResponse = $this->get($pdfUrl);
    $pdfResponse->assertOk();
})->group('T-P7-06', 'A1');

it('resets failure counter on a successful completion', function (): void {
    $settings = app(AiSettings::class);
    $settings->breaker_failures = 3;
    $settings->save();

    $breaker = app(CircuitBreaker::class);

    $breaker->recordFailure('fake');
    $breaker->recordFailure('fake');
    expect($breaker->state('fake')->failures)->toBe(2);

    $breaker->recordSuccess('fake');
    expect($breaker->state('fake')->failures)->toBe(0)
        ->and($breaker->state('fake')->state)->toBe(CircuitState::CLOSED);

    $breaker->recordFailure('fake');
    $breaker->recordFailure('fake');
    expect($breaker->state('fake')->failures)->toBe(2)
        ->and($breaker->state('fake')->state)->toBe(CircuitState::CLOSED);
})->group('T-P7-06', 'A1');

it('does not accumulate failures outside the breaker window', function (): void {
    $settings = app(AiSettings::class);
    $settings->breaker_failures = 3;
    $settings->breaker_window_seconds = 60;
    $settings->save();

    $breaker = app(CircuitBreaker::class);

    $breaker->recordFailure('fake');
    $breaker->recordFailure('fake');
    expect($breaker->state('fake')->failures)->toBe(2);

    // Travel beyond the window TTL
    $this->travel(70)->seconds();

    // Next failure occurs in a new window
    $breaker->recordFailure('fake');
    expect($breaker->state('fake')->failures)->toBe(1)
        ->and($breaker->state('fake')->state)->toBe(CircuitState::CLOSED);
})->group('T-P7-06', 'A1');

it('manages half-open state after cool-off: one trial allowed, failure re-opens immediately, success closes', function (): void {
    $settings = app(AiSettings::class);
    $settings->breaker_failures = 2;
    $settings->breaker_cool_off_seconds = 30;
    $settings->save();

    $breaker = app(CircuitBreaker::class);

    // Trip the breaker
    $breaker->recordFailure('fake');
    $breaker->recordFailure('fake');
    expect($breaker->state('fake')->state)->toBe(CircuitState::OPEN)
        ->and($breaker->allows('fake'))->toBeFalse();

    // Travel past cool-off
    $this->travel(35)->seconds();
    expect($breaker->state('fake')->state)->toBe(CircuitState::HALF_OPEN);

    // Exactly one trial is allowed (Cache::add lock)
    expect($breaker->allows('fake'))->toBeTrue();
    // Concurrent second caller is locked out
    expect($breaker->allows('fake'))->toBeFalse();

    // A failed trial re-opens immediately without needing N more failures
    $breaker->recordFailure('fake');
    expect($breaker->state('fake')->state)->toBe(CircuitState::OPEN);

    // Travel past cool-off again
    $this->travel(35)->seconds();
    expect($breaker->allows('fake'))->toBeTrue();

    // A successful trial closes the circuit
    $breaker->recordSuccess('fake');
    expect($breaker->state('fake')->state)->toBe(CircuitState::CLOSED)
        ->and($breaker->allows('fake'))->toBeTrue();
})->group('T-P7-06', 'A1');

it('routes to fallback provider when primary circuit opens and throws AiProviderCircuitOpen when all open', function (): void {
    $superAdmin = User::factory()->create(['status' => 'active'])->assignRole('super_admin');

    app(SaveAssistantProvider::class)($superAdmin, [
        'provider' => 'unconfigured',
        'fallback_provider' => 'fake',
        'model' => null,
        'prompt_version' => 1,
        'breaker_failures' => 2,
        'breaker_window_seconds' => 120,
        'breaker_cool_off_seconds' => 60,
    ]);

    $stubPrimary = new class implements AiProviderInterface
    {
        public int $calls = 0;

        public function complete(AiCompletionRequest $request): AiCompletionResult
        {
            $this->calls++;
            throw new RuntimeException('Primary unavailable');
        }

        public function stream(AiCompletionRequest $request): iterable
        {
            yield from [];
        }

        public function embed(array $texts): array
        {
            return [];
        }

        public function ocrPage(string $imagePath, array $languages): OcrResult
        {
            return new OcrResult('', false, 1.0);
        }

        public function health(): ProviderHealth
        {
            return new ProviderHealth(false, 'Down');
        }
    };

    $registry = app(ProviderRegistry::class);
    $breaker = app(CircuitBreaker::class);
    $resilient = new ResilientProvider($stubPrimary, $registry, $breaker);

    $request = new AiCompletionRequest(
        promptVersion: 1,
        cacheablePrefix: 'prefix',
        turns: [['role' => 'user', 'content' => 'Hello']],
        maxTokens: 220,
        responseSchema: ['type' => 'object'],
        timeoutSeconds: 15,
        userHash: str_repeat('b', 64),
        idempotencyKey: 'resilience-test-1',
    );

    // Attempt 1: primary fails, fallback succeeds
    $result1 = $resilient->complete($request);
    expect($result1->content)->toBe(FakeAiProvider::DEFAULT_ANSWER)
        ->and($stubPrimary->calls)->toBe(1)
        ->and($breaker->state('unconfigured')->failures)->toBe(1);

    // Attempt 2: primary fails again -> trips unconfigured breaker, fallback succeeds
    $result2 = $resilient->complete($request);
    expect($result2->content)->toBe(FakeAiProvider::DEFAULT_ANSWER)
        ->and($stubPrimary->calls)->toBe(2)
        ->and($breaker->state('unconfigured')->isOpen())->toBeTrue();

    // Attempt 3: primary circuit is OPEN, skipped entirely, fallback answers directly
    $result3 = $resilient->complete($request);
    expect($result3->content)->toBe(FakeAiProvider::DEFAULT_ANSWER)
        ->and($stubPrimary->calls)->toBe(2); // primary was not called!

    // Trip fallback circuit as well
    $breaker->recordFailure('fake');
    $breaker->recordFailure('fake');
    expect($breaker->state('fake')->isOpen())->toBeTrue();

    // Now both circuits are open: complete() throws AiProviderCircuitOpen
    $resilient->complete($request);
})->throws(AiProviderCircuitOpen::class)->group('T-P7-06', 'A1');

it('never counts AiProviderNotConfigured against circuit breaker', function (): void {
    $stubPrimary = new class implements AiProviderInterface
    {
        public function complete(AiCompletionRequest $request): AiCompletionResult
        {
            throw new AiProviderNotConfigured;
        }

        public function stream(AiCompletionRequest $request): iterable
        {
            yield from [];
        }

        public function embed(array $texts): array
        {
            return [];
        }

        public function ocrPage(string $imagePath, array $languages): OcrResult
        {
            return new OcrResult('', false, 1.0);
        }

        public function health(): ProviderHealth
        {
            return new ProviderHealth(false, 'Unconfigured');
        }
    };

    $breaker = app(CircuitBreaker::class);
    $registry = app(ProviderRegistry::class);
    $resilient = new ResilientProvider($stubPrimary, $registry, $breaker);

    $request = new AiCompletionRequest(
        promptVersion: 1,
        cacheablePrefix: 'prefix',
        turns: [['role' => 'user', 'content' => 'Hello']],
        maxTokens: 220,
        responseSchema: ['type' => 'object'],
        timeoutSeconds: 15,
        userHash: str_repeat('c', 64),
        idempotencyKey: 'not-configured-test',
    );

    try {
        $resilient->complete($request);
    } catch (AiProviderNotConfigured) {
        // expected
    }

    expect($breaker->state($registry->activeKey())->failures)->toBe(0)
        ->and($breaker->state($registry->activeKey())->isOpen())->toBeFalse();
})->group('T-P7-06', 'A1');

it('counts slow result exceeding timeout as a failure and returns it when it is the only route', function (): void {
    [$student, $lesson] = tp706ResilienceReady();

    $settings = app(AiSettings::class);
    $settings->breaker_failures = 3;
    $settings->save();

    $stubSlow = new class implements AiProviderInterface
    {
        public function complete(AiCompletionRequest $request): AiCompletionResult
        {
            return new AiCompletionResult(
                content: json_encode(['status' => 'not_in_source'], JSON_THROW_ON_ERROR),
                usage: new AiUsage(10, 0, 5),
                costMinor: 10,
                latencyMs: 16000, // 16s > timeout 15s
                providerRequestId: 'slow-req',
                finishReason: 'stop',
                model: 'slow-model',
            );
        }

        public function stream(AiCompletionRequest $request): iterable
        {
            yield from [];
        }

        public function embed(array $texts): array
        {
            return [];
        }

        public function ocrPage(string $imagePath, array $languages): OcrResult
        {
            return new OcrResult('', false, 1.0);
        }

        public function health(): ProviderHealth
        {
            return new ProviderHealth(true, 'Slow but ok');
        }
    };

    app()->instance(AiProviderInterface::class, $stubSlow);

    $reply = app(AskLessonAssistant::class)($student, $lesson, new AssistantQuestion('What is composition?', 'en', 0));

    expect($reply->outcome)->toBe(AssistantOutcome::Fallback);

    $breaker = app(CircuitBreaker::class);
    expect($breaker->state('fake')->failures)->toBe(1);

    $this->assertDatabaseHas('ai_usage', [
        'result' => 'timeout',
    ]);
})->group('T-P7-06', 'A1');

it('allows super admin to reset provider circuit, audits event, and forbids non-super admins', function (): void {
    $superAdmin = User::factory()->create(['status' => 'active'])->assignRole('super_admin');
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');

    $breaker = app(CircuitBreaker::class);
    $breaker->recordFailure('fake');
    $breaker->recordFailure('fake');
    $breaker->recordFailure('fake');
    $breaker->recordFailure('fake');
    $breaker->recordFailure('fake');
    expect($breaker->state('fake')->isOpen())->toBeTrue();

    // Admin without ai.configure is denied
    expect(fn () => app(ResetProviderCircuit::class)($admin, 'fake'))->toThrow(AuthorizationException::class);

    // Unknown route throws ValidationException
    expect(fn () => app(ResetProviderCircuit::class)($superAdmin, 'nonexistent'))->toThrow(ValidationException::class);

    // Super admin resets circuit
    app(ResetProviderCircuit::class)($superAdmin, 'fake');
    expect($breaker->state('fake')->state)->toBe(CircuitState::CLOSED)
        ->and($breaker->state('fake')->failures)->toBe(0);

    $this->assertDatabaseHas('activity_log', [
        'event' => 'ai.circuit_reset',
        'causer_id' => $superAdmin->id,
    ]);
})->group('T-P7-06', 'A1');

it('builds accurate ProviderHealthReport reflecting circuit state and stats', function (): void {
    $report = app(ProviderHealthReport::class)();
    expect($report['routes'])->toHaveCount(1)
        ->and($report['routes'][0]['role'])->toBe('primary')
        ->and($report['routes'][0]['key'])->toBe('fake')
        ->and($report['routes'][0]['circuit'])->toBe('closed')
        ->and($report['routes'][0]['healthy'])->toBeTrue();

    // Trip circuit
    $breaker = app(CircuitBreaker::class);
    $breaker->recordFailure('fake');
    $breaker->recordFailure('fake');
    $breaker->recordFailure('fake');
    $breaker->recordFailure('fake');
    $breaker->recordFailure('fake');

    $reportOpen = app(ProviderHealthReport::class)();
    expect($reportOpen['routes'][0]['circuit'])->toBe('open')
        ->and($reportOpen['routes'][0]['healthy'])->toBeFalse();

    // Now test with fallback configured
    $superAdmin = User::factory()->create(['status' => 'active'])->assignRole('super_admin');
    app(SaveAssistantProvider::class)($superAdmin, [
        'provider' => 'fake',
        'fallback_provider' => 'unconfigured',
        'model' => 'gpt-test',
        'prompt_version' => 1,
        'breaker_failures' => 5,
        'breaker_window_seconds' => 120,
        'breaker_cool_off_seconds' => 60,
    ]);

    $reportWithFallback = app(ProviderHealthReport::class)();
    expect($reportWithFallback['routes'])->toHaveCount(2)
        ->and($reportWithFallback['routes'][0]['role'])->toBe('primary')
        ->and($reportWithFallback['routes'][1]['role'])->toBe('fallback')
        ->and($reportWithFallback['routes'][1]['key'])->toBe('unconfigured')
        ->and($reportWithFallback['model'])->toBe('gpt-test');
})->group('T-P7-06', 'A1');

it('confirms container resolves FakeAiProvider by default in test environment', function (): void {
    $provider = app(AiProviderInterface::class);
    expect($provider)->toBeInstanceOf(FakeAiProvider::class);
})->group('T-P7-06', 'A1');
