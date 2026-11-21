<?php

// T-P7-MG | AI spec: an assistant outage never blocks learning | A1

declare(strict_types=1);

use App\Domain\AI\Contracts\AiProviderInterface;
use App\Domain\AI\Dto\AiCompletionRequest;
use App\Domain\AI\Dto\AiCompletionResult;
use App\Domain\AI\Dto\OcrResult;
use App\Domain\AI\Dto\ProviderHealth;
use App\Domain\AI\Providers\FakeAiProvider;
use App\Domain\AI\Providers\NullAiProvider;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Media\Actions\AttachLessonMedia;
use App\Domain\Settings\PlatformFlag;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Laravel\Pennant\Feature;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    Config::set('ai.enabled', true);
    Config::set('ai.features.assistant', true);
    Config::set('ai.provider', 'fake');
    Config::set('ai.adult_age', 18);
    Feature::for(null)->activate(PlatformFlag::AiAssistant->value);
});

/** @return array{course: Course, lesson: Lesson, learner: User} */
function aolSetup(): array
{
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $learner = User::factory()->create(['status' => 'active', 'birth_year' => 1995, 'birth_month' => 5])->assignRole('student');

    $course = Course::query()->create([
        'title' => ['en' => 'Outage Course', 'ar' => 'دورة الانقطاع'],
        'slug_ar' => 'aol-ar-'.uniqid(), 'slug_en' => 'aol-en-'.uniqid(),
        'status' => 'published', 'access_mode' => Course::ACCESS_FREE, 'ai_enabled' => true,
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Unit 1', 'ar' => 'الوحدة 1']]);
    $section->update(['published' => true]);

    $item = app(ManageLessons::class)->add($instructor, $section, ['type' => 'video', 'title' => ['en' => 'Outage Video Lesson', 'ar' => 'درس الفيديو']]);
    /** @var Lesson $lesson */
    $lesson = $item->itemable;
    app(AttachLessonMedia::class)($instructor, $lesson, ['provider' => 'youtube', 'url' => 'https://youtu.be/dQw4w9WgXcQ']);
    app(SetCurriculumStatus::class)($instructor, $course, [$item->id], CurriculumItem::STATUS_PUBLISHED);
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);

    LessonCurriculumDocument::query()->create([
        'lesson_id' => $lesson->id, 'version' => 1, 'path' => 'curriculum/aol.pdf',
        'sha256' => hash('sha256', 'aol-'.uniqid()), 'size' => 1024, 'pages' => 2,
        'status' => LessonCurriculumDocument::STATUS_READY, 'active' => true,
    ]);

    return ['course' => $course->refresh(), 'lesson' => $lesson->refresh(), 'learner' => $learner, 'item' => $item];
}

/** A provider whose every call throws, standing in for an unreachable AI service. */
function aolBrokenProvider(): AiProviderInterface
{
    return new class implements AiProviderInterface
    {
        public function complete(AiCompletionRequest $request): AiCompletionResult
        {
            throw new RuntimeException('AI service unreachable');
        }

        public function stream(AiCompletionRequest $request): iterable
        {
            throw new RuntimeException('AI service unreachable');
        }

        public function embed(array $texts): array
        {
            throw new RuntimeException('AI service unreachable');
        }

        public function ocrPage(string $imagePath, array $languages): OcrResult
        {
            throw new RuntimeException('AI service unreachable');
        }

        public function health(): ProviderHealth
        {
            return new ProviderHealth(false, 'unreachable', 1);
        }
    };
}

it('keeps the lesson player page at 200 with the video and PDF item when the AI provider throws', function (): void {
    ['course' => $course, 'lesson' => $lesson, 'learner' => $learner, 'item' => $item] = aolSetup();
    app()->instance(AiProviderInterface::class, aolBrokenProvider());

    $this->actingAs($learner)->get("/learn/{$course->id}/{$item->id}")
        ->assertOk()
        ->assertSee('Outage Video Lesson')
        ->assertSee('data-player', false)
        ->assertSee(__('learn.lesson_pdf'));
})->group('T-P7-MG');

it('keeps the lesson player page at 200 when the fake provider fails its next call', function (): void {
    ['course' => $course, 'learner' => $learner, 'item' => $item] = aolSetup();
    $fake = new FakeAiProvider;
    $fake->failNext(new RuntimeException('Downstream failure'));
    app()->instance(AiProviderInterface::class, $fake);

    $this->actingAs($learner)->get("/learn/{$course->id}/{$item->id}")
        ->assertOk()
        ->assertSee('Outage Video Lesson')
        ->assertSee('data-player', false)
        ->assertSee(__('learn.lesson_pdf'));
})->group('T-P7-MG');

it('keeps the lesson player page at 200 when the provider is unhealthy or not configured', function (string $mode): void {
    ['course' => $course, 'learner' => $learner, 'item' => $item] = aolSetup();

    if ($mode === 'unhealthy') {
        app()->instance(AiProviderInterface::class, (new FakeAiProvider)->setHealthy(false));
    } else {
        app()->instance(AiProviderInterface::class, new NullAiProvider);
    }

    $this->actingAs($learner)->get("/learn/{$course->id}/{$item->id}")
        ->assertOk()
        ->assertSee('Outage Video Lesson')
        ->assertSee('data-player', false)
        ->assertSee(__('learn.lesson_pdf'));
})->with(['unhealthy provider' => ['unhealthy'], 'unconfigured provider' => ['unconfigured']])->group('T-P7-MG');

it('still records progress heartbeats and completion while the AI provider is failing', function (): void {
    ['course' => $course, 'learner' => $learner, 'item' => $item] = aolSetup();
    app()->instance(AiProviderInterface::class, aolBrokenProvider());

    $this->actingAs($learner)->postJson(route('learn.progress', ['course' => $course->id, 'item' => $item->id]), ['position' => 30])
        ->assertOk()
        ->assertJsonPath('status', 'in_progress');

    $this->actingAs($learner)->postJson(route('learn.complete', ['course' => $course->id, 'item' => $item->id]))
        ->assertOk()
        ->assertJsonPath('status', 'completed');
})->group('T-P7-MG');
