<?php

// T-P7-MG | AI spec: entitlement is rechecked on every assistant request | A1

declare(strict_types=1);

use App\Domain\AI\Contracts\AiProviderInterface;
use App\Domain\AI\Providers\FakeAiProvider;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\Actions\RevokeEntitlement;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Media\Actions\AttachLessonMedia;
use App\Domain\Settings\PlatformFlag;
use App\Models\AiMessage;
use App\Models\AiPromptVersion;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\LessonDocumentPage;
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

    if (AiPromptVersion::current() === null) {
        AiPromptVersion::query()->create([
            'version' => 1,
            'template' => "Always answer strictly from source only.\nTier: {style_tier_instructions}\nLanguage: {reply_language}\nTitle: {lesson_title}\nDoc v{document_version}\n<lesson_source>\n{page_tagged_text}</lesson_source>",
            'style_tiers' => ['adults' => 'Clear adult guide', 'teens' => 'Friendly teen tone', 'kids' => 'Simple child tone'],
            'active' => true,
        ]);
    }
});

/** @return array{student: User, course: Course, lesson: Lesson, admin: User} */
function aerSetup(): array
{
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $admin = User::factory()->create(['status' => 'active'])->assignRole('super_admin');
    $student = User::factory()->create(['status' => 'active', 'birth_year' => 1995, 'birth_month' => 5])->assignRole('student');

    $course = Course::query()->create([
        'title' => ['en' => 'Entitlement Recheck', 'ar' => 'اختبار الاستحقاق'],
        'slug_ar' => 'aer-ar-'.uniqid(), 'slug_en' => 'aer-en-'.uniqid(),
        'status' => 'published', 'access_mode' => Course::ACCESS_FREE, 'ai_enabled' => true,
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Unit 1', 'ar' => 'الوحدة 1']]);
    $section->update(['published' => true]);
    $item = app(ManageLessons::class)->add($instructor, $section, ['type' => 'video', 'title' => ['en' => 'Primary Colors', 'ar' => 'الألوان']]);
    /** @var Lesson $lesson */
    $lesson = $item->itemable;
    app(AttachLessonMedia::class)($instructor, $lesson, ['provider' => 'youtube', 'url' => 'https://youtu.be/dQw4w9WgXcQ']);
    app(SetCurriculumStatus::class)($instructor, $course, [$item->id], CurriculumItem::STATUS_PUBLISHED);
    app(GrantEntitlement::class)($student, $course, EntitlementSource::Free);

    $document = LessonCurriculumDocument::query()->create([
        'lesson_id' => $lesson->id, 'version' => 1, 'path' => 'curriculum/aer.pdf',
        'sha256' => hash('sha256', 'aer-'.uniqid()), 'size' => 1024, 'pages' => 1,
        'status' => LessonCurriculumDocument::STATUS_READY, 'active' => true,
    ]);
    LessonDocumentPage::query()->create([
        'document_id' => $document->id, 'page_no' => 1,
        'approved_text' => 'Red, yellow, and blue are the traditional primary colors used in pigment mixing.',
    ]);

    return ['student' => $student, 'course' => $course, 'lesson' => $lesson->refresh(), 'admin' => $admin];
}

/** @return array<string, mixed> */
function aerAnswer(): array
{
    return [
        'status' => 'answered',
        'answer' => 'Red, yellow, and blue are the primary colors.',
        'source_pages' => [1],
        'support_quote' => 'Red, yellow, and blue are the traditional primary colors',
        'language' => 'en',
    ];
}

it('answers while entitled, then refuses the next request after the entitlement is revoked without calling the provider', function (): void {
    ['student' => $student, 'course' => $course, 'lesson' => $lesson, 'admin' => $admin] = aerSetup();
    $fake = new FakeAiProvider;
    app()->instance(AiProviderInterface::class, $fake);
    $fake->respondWith(aerAnswer());

    $this->actingAs($student)->post(route('assistant.ask', ['lesson' => $lesson->id]), ['question' => 'What are primary colors?'])->assertOk();
    expect($fake->requests())->toHaveCount(1);

    $entitlement = Entitlement::query()->where('learner_id', $student->id)->where('course_id', $course->id)->firstOrFail();
    app(RevokeEntitlement::class)($admin, $entitlement, 'aer test revoke');
    app(EntitlementService::class)->flush();

    $second = $this->actingAs($student)->post(route('assistant.ask', ['lesson' => $lesson->id]), ['question' => 'Tell me about blue']);
    expect($second->getStatusCode())->not->toBe(200);
    expect($fake->requests())->toHaveCount(1);
    expect(AiMessage::query()->where('role', AiMessage::ROLE_ASSISTANT)->count())->toBe(1);
})->group('T-P7-MG');

it('refuses the next request once the entitlement has expired, without calling the provider', function (): void {
    ['student' => $student, 'course' => $course, 'lesson' => $lesson] = aerSetup();
    $fake = new FakeAiProvider;
    app()->instance(AiProviderInterface::class, $fake);
    $fake->respondWith(aerAnswer());

    $this->actingAs($student)->post(route('assistant.ask', ['lesson' => $lesson->id]), ['question' => 'What are primary colors?'])->assertOk();
    expect($fake->requests())->toHaveCount(1);

    Entitlement::query()->where('learner_id', $student->id)->where('course_id', $course->id)
        ->update(['ends_at' => now()->subMinute()]);
    app(EntitlementService::class)->flush();

    $next = $this->actingAs($student)->post(route('assistant.ask', ['lesson' => $lesson->id]), ['question' => 'Explain yellow']);
    expect($next->getStatusCode())->not->toBe(200);
    expect($fake->requests())->toHaveCount(1);
})->group('T-P7-MG');
