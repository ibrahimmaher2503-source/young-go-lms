<?php

// T-P7-06 | AI spec §11, §14, §18 | A1 A2 A3

declare(strict_types=1);

use App\Domain\AI\Contracts\AiProviderInterface;
use App\Domain\AI\Providers\FakeAiProvider;
use App\Domain\AI\Resilience\CircuitBreaker;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Models\AiConversation;
use App\Models\AiFeedback;
use App\Models\AiMessage;
use App\Models\AiPromptVersion;
use App\Models\AiUsage;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\LessonDocumentPage;
use App\Models\LessonKeyTerm;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    app()->instance(AiProviderInterface::class, new FakeAiProvider);
    File::ensureDirectoryExists(base_path('tests/Browser/screenshots/T-P7-06'));
});

/**
 * Creates browser testing fixtures for assistant admin tests.
 *
 * @return array{
 *     admin: User,
 *     prompt: AiPromptVersion,
 *     course: Course,
 *     item1: CurriculumItem,
 *     lesson1: Lesson,
 *     item2: CurriculumItem,
 *     lesson2: Lesson,
 *     doc: LessonCurriculumDocument
 * }
 */
function aabFixture(string $locale): array
{
    $admin = User::factory()->create([
        'locale' => $locale,
        'status' => 'active',
        'name' => $locale === 'ar' ? 'مدير النظام' : 'System Administrator',
        'email' => 'admin-browser-'.uniqid().'@example.test',
    ])->assignRole('super_admin');

    $prompt = AiPromptVersion::query()->firstOrCreate(
        ['version' => 1],
        [
            'template' => 'Answer: {question}',
            'style_tiers' => ['adults' => 'Adult tier', 'kids' => 'Kid tier', 'teens' => 'Teen tier'],
            'active' => true,
        ]
    );

    $course = Course::query()->create([
        'title' => [
            'en' => 'Digital Illustration Masterclass',
            'ar' => 'دورة الرسم الرقمي المتقدمة',
        ],
        'slug_ar' => 'aab-course-ar-'.uniqid(),
        'slug_en' => 'aab-course-en-'.uniqid(),
        'status' => 'published',
        'ai_enabled' => true,
    ]);
    $course->instructors()->attach($admin->id, ['role' => 'instructor', 'scope' => 'manage']);

    $section = app(ManageSections::class)->add($admin, $course, [
        'title' => ['en' => 'Module 1: Foundations', 'ar' => 'الوحدة الأولى: الأساسيات'],
    ]);

    // Lesson 1: ready with PDF, ai_enabled = true
    $item1 = app(ManageLessons::class)->add($admin, $section, [
        'title' => ['en' => 'Color Theory & Harmony', 'ar' => 'نظرية الألوان والتناغم'],
    ]);
    $item1->update(['ai_enabled' => true]);
    /** @var Lesson $lesson1 */
    $lesson1 = $item1->itemable;
    $lesson1->forceFill(['type' => 'video'])->save();

    $doc = LessonCurriculumDocument::query()->create([
        'lesson_id' => $lesson1->id,
        'version' => 1,
        'path' => 'curriculum/color-theory.pdf',
        'sha256' => hash('sha256', 'color-theory-doc-1'),
        'size' => 4096,
        'pages' => 3,
        'status' => LessonCurriculumDocument::STATUS_READY,
        'active' => true,
    ]);

    LessonDocumentPage::query()->create([
        'document_id' => $doc->id,
        'page_no' => 1,
        'approved_text' => 'Color harmony and primary colors.',
    ]);
    LessonDocumentPage::query()->create([
        'document_id' => $doc->id,
        'page_no' => 2,
        'approved_text' => 'Complementary contrast between blue and orange.',
    ]);

    LessonKeyTerm::query()->create([
        'document_id' => $doc->id,
        'term' => $locale === 'ar' ? 'التباين' : 'contrast',
        'locale' => $locale,
        'approved' => true,
    ]);

    // Lesson 2: no PDF, ai_enabled = false
    $item2 = app(ManageLessons::class)->add($admin, $section, [
        'title' => ['en' => 'Perspective & Lighting', 'ar' => 'المنظور والإضاءة'],
    ]);
    $item2->update(['ai_enabled' => false]);
    /** @var Lesson $lesson2 */
    $lesson2 = $item2->itemable;
    $lesson2->forceFill(['type' => 'video'])->save();

    return compact('admin', 'prompt', 'course', 'item1', 'lesson1', 'item2', 'lesson2', 'doc');
}

/**
 * Seeds sample usage data across different result types and dates.
 */
function aabSeedUsage(Course $course, Lesson $lesson, string $locale): void
{
    $rows = [
        ['result' => 'answered', 'input' => 120, 'output' => 80, 'cost' => 15, 'lat' => 450],
        ['result' => 'not_in_source', 'input' => 90, 'output' => 30, 'cost' => 8, 'lat' => 320],
        ['result' => 'cache_hit', 'input' => 10, 'output' => 50, 'cost' => 0, 'lat' => 20],
        ['result' => 'injection', 'input' => 50, 'output' => 0, 'cost' => 0, 'lat' => 15],
        ['result' => 'provider_error', 'input' => 100, 'output' => 0, 'cost' => 0, 'lat' => 1200],
    ];

    foreach ($rows as $i => $row) {
        $u = AiUsage::query()->create([
            'student_hash' => hash('sha256', "std-{$i}"),
            'course_id' => $course->id,
            'lesson_id' => $lesson->id,
            'provider' => 'fake',
            'model' => 'fake',
            'prompt_version' => 1,
            'locale' => $locale,
            'style_tier' => 'adults',
            'result' => $row['result'],
            'input_tokens' => $row['input'],
            'output_tokens' => $row['output'],
            'cost_minor' => $row['cost'],
            'latency_ms' => $row['lat'],
        ]);
        $u->forceFill(['created_at' => now()->subDays($i % 3)])->save();
    }
}

/**
 * Seeds sample conversations, messages, and feedback for insights.
 */
function aabSeedInsights(User $user, Course $course, Lesson $lesson, string $locale): void
{
    $conv = AiConversation::query()->create([
        'student_id' => $user->id,
        'course_id' => $course->id,
        'lesson_id' => $lesson->id,
        'document_version' => 1,
        'locale' => $locale,
        'style_tier' => 'adults',
        'video_position' => 60,
        'status' => 'active',
    ]);

    // Question 1 -> answered with cited page 2
    $q1 = AiMessage::query()->create([
        'conversation_id' => $conv->id,
        'role' => AiMessage::ROLE_USER,
        'content' => $locale === 'ar' ? 'ما هو التباين اللوني؟' : 'What is color contrast?',
        'status' => 'answered',
    ]);
    $a1 = AiMessage::query()->create([
        'conversation_id' => $conv->id,
        'role' => AiMessage::ROLE_ASSISTANT,
        'content' => 'Color contrast is the difference between hues.',
        'status' => 'answered',
        'source_pages' => [2],
        'model' => 'fake',
    ]);

    // Feedback on answer 1
    AiFeedback::query()->create([
        'conversation_id' => $conv->id,
        'message_id' => $a1->id,
        'student_id' => $user->id,
        'type' => 'not_understood',
        'comment' => 'Need simpler explanation',
    ]);

    // Question 2 -> not in source (gap)
    $q2 = AiMessage::query()->create([
        'conversation_id' => $conv->id,
        'role' => AiMessage::ROLE_USER,
        'content' => $locale === 'ar' ? 'كيف أضبط الإضاءة الثلاثية؟' : 'How do I set up three-point lighting?',
        'status' => 'answered',
    ]);
    AiMessage::query()->create([
        'conversation_id' => $conv->id,
        'role' => AiMessage::ROLE_ASSISTANT,
        'content' => '',
        'status' => 'not_in_source',
        'source_pages' => [],
        'model' => 'fake',
    ]);

    // Question 3 -> simpler mode
    AiMessage::query()->create([
        'conversation_id' => $conv->id,
        'role' => AiMessage::ROLE_USER,
        'content' => $locale === 'ar' ? 'اشرح ببساطة' : 'Explain simply',
        'status' => 'answered',
        'guard_flags' => ['mode' => 'simpler'],
    ]);
    AiMessage::query()->create([
        'conversation_id' => $conv->id,
        'role' => AiMessage::ROLE_ASSISTANT,
        'content' => 'Simple explanation.',
        'status' => 'answered',
        'source_pages' => [1],
        'model' => 'fake',
    ]);
}

it('captures screenshots and verifies a11y for assistant controls page', function (
    string $locale,
    string $theme,
    int $width,
    int $height,
): void {
    $data = aabFixture($locale);
    $this->withSession(['locale' => $locale]);
    $this->actingAs($data['admin']);

    // Trigger open circuit on fake provider
    $breaker = app(CircuitBreaker::class);
    for ($i = 0; $i < 5; $i++) {
        $breaker->recordFailure('fake');
    }

    $page = $this->visit('/admin/assistant-controls')->resize($width, $height);
    $page->script("() => { localStorage.setItem('theme', '{$theme}'); window.dispatchEvent(new CustomEvent('theme-changed', {detail: '{$theme}'})); }");
    $page->refresh();
    $page->script('async () => { await document.fonts.ready; }');

    // Verification of selectors and state
    $page->assertPresent('[data-assistant-controls]')
        ->assertPresent('[data-section="global"]')
        ->assertPresent('[data-section="readiness"]')
        ->assertPresent('[data-section="lessons"]')
        ->assertPresent('[data-section="health"]')
        ->assertPresent('[data-section="provider"]')
        ->assertPresent('[data-section="quotas"]')
        ->assertPresent('[data-section="retention"]')
        ->assertPresent('[data-global-state="off"]')
        ->assertPresent('[data-action="enable"]')
        ->assertPresent('[data-action="provider"]')
        ->assertPresent('[data-action="quotas"]')
        ->assertPresent('[data-action="retention"]')
        ->assertPresent('[data-route="fake"][data-circuit="open"]')
        ->assertPresent('[data-reset-circuit="fake"]')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');
    expect($page->script('() => document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();

    $page->screenshot(fullPage: true, filename: "T-P7-06/controls-{$locale}-{$theme}-{$width}");
})->with(['ar', 'en'])->with(['light', 'dark'])->with([[390, 844], [1440, 900]])
    ->group('T-P7-06', 'A3')->group('browser');

it('captures screenshots and verifies a11y for assistant usage report page with data and empty', function (
    string $locale,
    string $theme,
    int $width,
    int $height,
): void {
    $data = aabFixture($locale);
    aabSeedUsage($data['course'], $data['lesson1'], $locale);

    $this->withSession(['locale' => $locale]);
    $this->actingAs($data['admin']);

    // 1. With data
    $page = $this->visit('/admin/assistant-usage')->resize($width, $height);
    $page->script("() => { localStorage.setItem('theme', '{$theme}'); window.dispatchEvent(new CustomEvent('theme-changed', {detail: '{$theme}'})); }");
    $page->refresh();
    $page->script('async () => { await document.fonts.ready; }');

    $page->assertPresent('[data-assistant-usage]')
        ->assertPresent('[data-usage-totals]')
        ->assertPresent('[data-total="requests"]')
        ->assertPresent('[data-section="by-day"]')
        ->assertPresent('[data-section="by-result"]')
        ->assertPresent('[data-section="by-lesson"]')
        ->assertPresent('[data-section="by-model"]')
        ->assertPresent('.yg-ai-scroll')
        ->assertPresent('.yg-ai-num')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');
    expect($page->script('() => document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();

    $page->screenshot(fullPage: true, filename: "T-P7-06/usage-report-{$locale}-{$theme}-{$width}");

    // 2. Empty state
    AiUsage::query()->delete();
    $page->refresh();
    $page->script('async () => { await document.fonts.ready; }');

    $page->assertPresent('[data-usage-empty]')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    expect($page->script('() => document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();

    $page->screenshot(fullPage: true, filename: "T-P7-06/usage-report-empty-{$locale}-{$theme}-{$width}");
})->with(['ar', 'en'])->with(['light', 'dark'])->with([[390, 844], [1440, 900]])
    ->group('T-P7-06', 'A3')->group('browser');

it('captures screenshots and verifies a11y for assistant insights page with data and empty', function (
    string $locale,
    string $theme,
    int $width,
    int $height,
): void {
    $data = aabFixture($locale);
    aabSeedInsights($data['admin'], $data['course'], $data['lesson1'], $locale);

    $this->withSession(['locale' => $locale]);
    $this->actingAs($data['admin']);

    // 1. Empty / unselected state
    $page = $this->visit('/admin/assistant-insights')->resize($width, $height);
    $page->script("() => { localStorage.setItem('theme', '{$theme}'); window.dispatchEvent(new CustomEvent('theme-changed', {detail: '{$theme}'})); }");
    $page->refresh();
    $page->script('async () => { await document.fonts.ready; }');

    $page->assertPresent('[data-assistant-insights]')
        ->assertSee(__('ai_admin.insights.choose', [], $locale))
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');
    expect($page->script('() => document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();

    $page->screenshot(fullPage: true, filename: "T-P7-06/insights-empty-{$locale}-{$theme}-{$width}");

    // 2. With data (select lesson)
    $page->select('#ins-lesson', (string) $data['lesson1']->id)->wait(0.8);
    $page->script('async () => { await document.fonts.ready; }');

    $page->assertPresent('[data-insight-totals]')
        ->assertPresent('[data-insight="questions"]')
        ->assertPresent('[data-insight="answered"]')
        ->assertPresent('[data-section="gaps"]')
        ->assertPresent('[data-gap]')
        ->assertPresent('[data-section="topics"]')
        ->assertPresent('[data-topic]')
        ->assertPresent('[data-section="pages"]')
        ->assertPresent('[data-cited-page]')
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();

    expect($page->script('() => document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();

    $page->screenshot(fullPage: true, filename: "T-P7-06/insights-{$locale}-{$theme}-{$width}");
})->with(['ar', 'en'])->with(['light', 'dark'])->with([[390, 844], [1440, 900]])
    ->group('T-P7-06', 'A3')->group('browser');

it('captures screenshots and verifies layout for assistant availability page', function (
    string $locale,
    string $theme,
    int $width,
    int $height,
): void {
    $data = aabFixture($locale);
    $this->withSession(['locale' => $locale]);
    $this->actingAs($data['admin']);

    $page = $this->visit('/admin/assistant-availability')->resize($width, $height);
    $page->script("() => { localStorage.setItem('theme', '{$theme}'); window.dispatchEvent(new CustomEvent('theme-changed', {detail: '{$theme}'})); }");
    $page->refresh();
    $page->script('async () => { await document.fonts.ready; }');

    // Select course to view availability table
    $page->select('#av-course', (string) $data['course']->id)->wait(0.8);
    $page->script('async () => { await document.fonts.ready; }');

    $page->assertPresent('[data-assistant-availability]')
        ->assertPresent('[data-section="course"]')
        ->assertPresent('[data-section="lessons"]')
        ->assertPresent('[data-course-switch="on"]')
        ->assertPresent('[data-lesson-switch="on"]')
        ->assertPresent('[data-lesson-switch="off"]')
        ->assertPresent('[data-readiness="ready"]')
        ->assertPresent('[data-readiness="none"]')
        ->assertPresent('.yg-ai-scroll')
        ->assertNoJavaScriptErrors();

    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');
    expect($page->script('() => document.documentElement.scrollWidth <= window.innerWidth'))->toBeTrue();

    $page->screenshot(fullPage: true, filename: "T-P7-06/availability-{$locale}-{$theme}-{$width}");
})->with(['ar', 'en'])->with(['light', 'dark'])->with([[390, 844], [1440, 900]])
    ->group('T-P7-06', 'A3')->group('browser');

it('verifies accessibility for assistant availability page', function (
    string $locale,
    string $theme,
    int $width,
    int $height,
): void {
    $data = aabFixture($locale);
    $this->withSession(['locale' => $locale]);
    $this->actingAs($data['admin']);

    $page = $this->visit('/admin/assistant-availability')->resize($width, $height);
    $page->script("() => { localStorage.setItem('theme', '{$theme}'); window.dispatchEvent(new CustomEvent('theme-changed', {detail: '{$theme}'})); }");
    $page->refresh();
    $page->script('async () => { await document.fonts.ready; }');

    $page->select('#av-course', (string) $data['course']->id)->wait(0.8);
    $page->script('async () => { await document.fonts.ready; }');

    $page->assertNoAccessibilityIssues();
})->with(['ar', 'en'])->with(['light', 'dark'])->with([[390, 844], [1440, 900]])
    ->group('T-P7-06', 'A3')->group('browser');

it('opens, validates, and closes modal forms on assistant controls page', function (string $locale): void {
    $data = aabFixture($locale);
    $this->withSession(['locale' => $locale]);
    $this->actingAs($data['admin']);

    $page = $this->visit('/admin/assistant-controls')->resize(1440, 900);
    $page->script('async () => { await document.fonts.ready; }');

    // 1. Enable Modal
    $page->click('[data-action="enable"]')->wait(0.5);
    $page->assertVisible('.fi-modal-window:visible');
    $page->keys('.fi-modal-window:visible', 'Escape')->wait(0.5);
    $page->assertNotPresent('.fi-modal-window:visible');

    // 2. Provider Modal
    $page->click('[data-action="provider"]')->wait(0.5);
    $page->assertVisible('.fi-modal-window:visible');
    $page->keys('.fi-modal-window:visible', 'Escape')->wait(0.5);
    $page->assertNotPresent('.fi-modal-window:visible');

    // 3. Quotas Modal
    $page->click('[data-action="quotas"]')->wait(0.5);
    $page->assertVisible('.fi-modal-window:visible');
    $page->keys('.fi-modal-window:visible', 'Escape')->wait(0.5);
    $page->assertNotPresent('.fi-modal-window:visible');

    // 4. Retention Modal
    $page->click('[data-action="retention"]')->wait(0.5);
    $page->assertVisible('.fi-modal-window:visible');
    $page->keys('.fi-modal-window:visible', 'Escape')->wait(0.5);
    $page->assertNotPresent('.fi-modal-window:visible');
})->with(['ar', 'en'])
    ->group('T-P7-06', 'A3')->group('browser');
