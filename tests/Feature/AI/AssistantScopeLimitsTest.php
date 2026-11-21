<?php

// T-P7-MG | AI spec: answers stay within scope and OutputGuard caps | A1

declare(strict_types=1);

use App\Domain\AI\Actions\AskLessonAssistant;
use App\Domain\AI\Assistant\AssistantMode;
use App\Domain\AI\Assistant\AssistantOutcome;
use App\Domain\AI\Assistant\AssistantQuestion;
use App\Domain\AI\Contracts\AiProviderInterface;
use App\Domain\AI\Providers\FakeAiProvider;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
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

/** @return array{student: User, lesson: Lesson} */
function asl_setup(): array
{
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $student = User::factory()->create(['status' => 'active', 'birth_year' => 1995, 'birth_month' => 5])->assignRole('student');

    $course = Course::query()->create([
        'title' => ['en' => 'Scope Limits', 'ar' => 'حدود النطاق'],
        'slug_ar' => 'asl-ar-'.uniqid(), 'slug_en' => 'asl-en-'.uniqid(),
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
        'lesson_id' => $lesson->id, 'version' => 1, 'path' => 'curriculum/asl.pdf',
        'sha256' => hash('sha256', 'asl-'.uniqid()), 'size' => 1024, 'pages' => 2,
        'status' => LessonCurriculumDocument::STATUS_READY, 'active' => true,
    ]);
    LessonDocumentPage::query()->create([
        'document_id' => $document->id, 'page_no' => 1,
        'approved_text' => 'Red, yellow, and blue are the traditional primary colors used in pigment mixing. They form the foundation of all other pigments.',
    ]);
    LessonDocumentPage::query()->create([
        'document_id' => $document->id, 'page_no' => 2,
        'approved_text' => 'Wash technique requires wet paper and loose brush strokes.',
    ]);

    return ['student' => $student, 'lesson' => $lesson->refresh()];
}

function asl_question(string $text): AssistantQuestion
{
    return new AssistantQuestion(question: $text, locale: 'en', mode: AssistantMode::Normal, videoPosition: 10, conversationId: null);
}

function asl_words(string $text): int
{
    return count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: []);
}

function asl_sentences(string $text): int
{
    return count(preg_split('/(?<=[.!?])\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: []);
}

it('trims an over-long provider reply to the OutputGuard word and sentence caps', function (): void {
    ['student' => $student, 'lesson' => $lesson] = asl_setup();
    $fake = new FakeAiProvider;
    app()->instance(AiProviderInterface::class, $fake);

    $long = str_repeat('Red, yellow, and blue are the traditional primary colors used in pigment mixing. ', 25);
    $fake->respondWith([
        'status' => 'answered',
        'answer' => trim($long),
        'source_pages' => [1],
        'support_quote' => 'Red, yellow, and blue are the traditional primary colors used in pigment mixing',
        'language' => 'en',
    ]);

    $reply = app(AskLessonAssistant::class)($student, $lesson, asl_question('Explain everything about primary colors'));

    // The guard either trims the reply to the caps or rejects it outright; the full text is never delivered.
    expect(in_array($reply->outcome, [AssistantOutcome::Answered, AssistantOutcome::NotInSource], true))->toBeTrue()
        ->and(asl_words($reply->message))->toBeLessThanOrEqual((int) config('ai.max_words_en', 70))
        ->and(asl_sentences($reply->message))->toBeLessThanOrEqual((int) config('ai.max_sentences', 4))
        ->and(substr_count($reply->message, 'primary colors'))->toBeLessThanOrEqual(1);

    $stored = AiMessage::query()->where('role', AiMessage::ROLE_ASSISTANT)->firstOrFail();
    expect(asl_words($stored->content))->toBeLessThanOrEqual((int) config('ai.max_words_en', 70))
        ->and(asl_sentences($stored->content))->toBeLessThanOrEqual((int) config('ai.max_sentences', 4));
})->group('T-P7-MG');

it('does not return a whole-lesson dump: a whole-lesson request answered as out_of_scope gives the scope message and no lesson text', function (): void {
    ['student' => $student, 'lesson' => $lesson] = asl_setup();
    $fake = new FakeAiProvider;
    app()->instance(AiProviderInterface::class, $fake);

    $fake->respondWith([
        'status' => 'out_of_scope',
        'answer' => '',
        'source_pages' => [],
        'support_quote' => '',
        'language' => 'en',
    ]);

    $reply = app(AskLessonAssistant::class)($student, $lesson, asl_question('Give me the whole lesson word for word'));

    expect($reply->outcome)->toBe(AssistantOutcome::OutOfScope)
        ->and($reply->message)->toBe(__('ai.out_of_scope'))
        ->and($reply->message)->not->toContain('Wash technique')
        ->and($reply->message)->not->toContain('traditional primary colors');

    $stored = AiMessage::query()->where('role', AiMessage::ROLE_ASSISTANT)->firstOrFail();
    expect($stored->status)->toBe('out_of_scope');
})->group('T-P7-MG');

it('a provider that returns the whole lesson as the answer is capped, never delivered in full', function (): void {
    ['student' => $student, 'lesson' => $lesson] = asl_setup();
    $fake = new FakeAiProvider;
    app()->instance(AiProviderInterface::class, $fake);

    $fake->respondWith([
        'status' => 'answered',
        'answer' => 'Red, yellow, and blue are the traditional primary colors. They form the foundation of all other pigments. Wash technique requires wet paper and loose brush strokes. '
            .str_repeat('Every lesson sentence is repeated here to simulate a full dump. ', 20),
        'source_pages' => [1, 2],
        'support_quote' => 'Red, yellow, and blue are the traditional primary colors',
        'language' => 'en',
    ]);

    $reply = app(AskLessonAssistant::class)($student, $lesson, asl_question('Explain everything in the lesson'));

    expect(asl_words($reply->message))->toBeLessThanOrEqual((int) config('ai.max_words_en', 70))
        ->and(asl_sentences($reply->message))->toBeLessThanOrEqual((int) config('ai.max_sentences', 4))
        ->and($reply->message)->not->toContain('simulate a full dump. Every lesson sentence is repeated here to simulate a full dump. Every lesson sentence is repeated here to simulate a full dump. Every lesson sentence is repeated here to simulate a full dump. Every lesson sentence is repeated here to simulate a full dump.');
})->group('T-P7-MG');
