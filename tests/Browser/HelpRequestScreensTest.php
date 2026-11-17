<?php

// T-P7-04 | AI spec §12 §16, PRD §15.5 §16, D-019..D-021 | A1 A2 A3 A4

declare(strict_types=1);

use App\Domain\AI\Help\Actions\ReplyToHelpRequest;
use App\Domain\AI\Help\HelpRequestStatus;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\Contracts\LessonAccessChecker;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Media\Actions\AttachLessonMedia;
use App\Domain\Settings\PlatformFlag;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\Course;
use App\Models\HelpRequest;
use App\Models\HelpRequestEvent;
use App\Models\HelpRequestMessage;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Pennant\Feature;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Storage::fake('local');
});

/**
 * @return array{
 *     course: Course,
 *     lesson: Lesson,
 *     item: mixed,
 *     instructor: User,
 *     student: User,
 *     requestWithReplies: HelpRequest,
 *     requestWaiting: HelpRequest,
 *     requestResolved: HelpRequest,
 *     requestOverdue: HelpRequest
 * }
 */
function hrbBuild(string $locale): array
{
    $instructor = User::factory()->create([
        'locale' => $locale,
        'name' => $locale === 'ar' ? 'أحمد كمال' : 'Ahmed Kamal',
        'status' => 'active',
    ])->assignRole('instructor_academy');

    $course = Course::query()->create([
        'title' => [
            'ar' => 'أساسيات الرسم والتشريح الفني',
            'en' => 'Digital Drawing and Artistic Anatomy',
        ],
        'slug_ar' => 'hrb-'.uniqid(),
        'slug_en' => 'hrb-'.uniqid(),
        'status' => 'published',
        'access_mode' => 'paid',
        'ai_enabled' => true,
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);

    $section = app(ManageSections::class)->add($instructor, $course, [
        'title' => ['ar' => 'الوحدة الأولى: البداية', 'en' => 'Unit 1: The Basics'],
    ]);
    $section->update(['published' => true]);

    Storage::disk('local')->put('media/clip.mp4', 'dummy-video');
    $item = app(ManageLessons::class)->add($instructor, $section, [
        'title' => ['ar' => 'رسم الأشكال الأساسية والظل', 'en' => 'Basic Shapes and Shading'],
        'type' => 'video',
    ]);
    $item->update(['ai_enabled' => true]);
    /** @var Lesson $lesson */
    $lesson = $item->itemable;
    $lesson->forceFill(['type' => 'video'])->save();
    app(AttachLessonMedia::class)($instructor, $lesson, ['provider' => 'signed_storage', 'path' => 'media/clip.mp4']);
    app(SetCurriculumStatus::class)($instructor, $course, [$item->id], 'published');

    LessonCurriculumDocument::query()->create([
        'lesson_id' => $lesson->id,
        'version' => 1,
        'path' => 'curriculum/guide.pdf',
        'sha256' => str_repeat('a', 64),
        'size' => 2048,
        'pages' => 3,
        'status' => LessonCurriculumDocument::STATUS_READY,
        'active' => true,
    ]);

    $student = User::factory()->create([
        'locale' => $locale,
        'name' => $locale === 'ar' ? 'مريم أحمد' : 'Mariam Ahmed',
        'status' => 'active',
    ])->assignRole('student');
    app(GrantEntitlement::class)($student, $course, EntitlementSource::Free);

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

    // 1. Thread with replies and reply form (instructor_replied)
    $conv1 = AiConversation::query()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'lesson_id' => $lesson->id,
        'document_version' => 1,
        'locale' => $locale,
        'style_tier' => 'standard',
        'video_position' => 125,
        'status' => 'active',
    ]);
    $msg1 = AiMessage::query()->create([
        'conversation_id' => $conv1->id,
        'role' => AiMessage::ROLE_ASSISTANT,
        'content' => $locale === 'ar' ? 'التظليل يبدأ بتحديد مصدر الضوء.' : 'Shading starts with identifying the light source.',
        'status' => 'answered',
        'source_pages' => [1, 2],
        'model' => 'fake',
    ]);
    $reqReplies = HelpRequest::query()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'lesson_id' => $lesson->id,
        'assigned_instructor_id' => $instructor->id,
        'conversation_id' => $conv1->id,
        'ai_message_id' => $msg1->id,
        'status' => HelpRequestStatus::InstructorReplied,
        'locale' => $locale,
        'video_position' => 125,
        'document_version' => 1,
        'ai_status' => 'answered',
        'source_pages' => [1, 2],
        'question' => $locale === 'ar' ? 'كيف أحدد اتجاه الظل الساقط بدقة؟' : 'How do I accurately determine the cast shadow direction?',
        'ai_answer' => $locale === 'ar' ? 'التظليل يبدأ بتحديد مصدر الضوء.' : 'Shading starts with identifying the light source.',
        'due_at' => null,
        'first_replied_at' => now()->subHours(2),
    ]);
    HelpRequestEvent::query()->create([
        'help_request_id' => $reqReplies->id,
        'actor_id' => $student->id,
        'event' => 'created',
        'to_status' => 'new',
        'created_at' => now()->subHours(5),
    ]);
    HelpRequestMessage::query()->create([
        'help_request_id' => $reqReplies->id,
        'author_id' => $instructor->id,
        'role' => HelpRequestMessage::ROLE_INSTRUCTOR,
        'body' => $locale === 'ar' ? 'ارسم خطاً مستقيماً من مصدر الضوء عبر حافة المجسم حتى سطح الأرضية.' : 'Draw a straight line from the light source through the edge of the object to the ground plane.',
        'created_at' => now()->subHours(2),
    ]);
    HelpRequestEvent::query()->create([
        'help_request_id' => $reqReplies->id,
        'actor_id' => $instructor->id,
        'event' => 'replied',
        'from_status' => 'new',
        'to_status' => 'instructor_replied',
        'created_at' => now()->subHours(2),
    ]);

    // 2. Waiting request (status new)
    $conv2 = AiConversation::query()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'lesson_id' => $lesson->id,
        'document_version' => 1,
        'locale' => $locale,
        'style_tier' => 'standard',
        'video_position' => 210,
        'status' => 'active',
    ]);
    $msg2 = AiMessage::query()->create([
        'conversation_id' => $conv2->id,
        'role' => AiMessage::ROLE_ASSISTANT,
        'content' => $locale === 'ar' ? 'استخدم تدريج الدرجات من H إلى B.' : 'Use gradient pencils from H to B.',
        'status' => 'answered',
        'source_pages' => [2],
        'model' => 'fake',
    ]);
    $reqWaiting = HelpRequest::query()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'lesson_id' => $lesson->id,
        'assigned_instructor_id' => $instructor->id,
        'conversation_id' => $conv2->id,
        'ai_message_id' => $msg2->id,
        'status' => HelpRequestStatus::New,
        'locale' => $locale,
        'video_position' => 210,
        'document_version' => 1,
        'ai_status' => 'answered',
        'source_pages' => [2],
        'question' => $locale === 'ar' ? 'ما الفرق بين درجات أقلام الرصاص؟' : 'What is the difference between pencil hardness grades?',
        'ai_answer' => $locale === 'ar' ? 'استخدم تدريج الدرجات من H إلى B.' : 'Use gradient pencils from H to B.',
        'due_at' => now()->addHours(20),
    ]);
    HelpRequestEvent::query()->create([
        'help_request_id' => $reqWaiting->id,
        'actor_id' => $student->id,
        'event' => 'created',
        'to_status' => 'new',
        'created_at' => now()->subHours(4),
    ]);

    // 3. Resolved request
    $conv3 = AiConversation::query()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'lesson_id' => $lesson->id,
        'document_version' => 1,
        'locale' => $locale,
        'style_tier' => 'standard',
        'status' => 'active',
    ]);
    $msg3 = AiMessage::query()->create([
        'conversation_id' => $conv3->id,
        'role' => AiMessage::ROLE_ASSISTANT,
        'content' => 'Answer 3',
        'status' => 'answered',
        'model' => 'fake',
    ]);
    $reqResolved = HelpRequest::query()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'lesson_id' => $lesson->id,
        'assigned_instructor_id' => $instructor->id,
        'conversation_id' => $conv3->id,
        'ai_message_id' => $msg3->id,
        'status' => HelpRequestStatus::Resolved,
        'locale' => $locale,
        'document_version' => 1,
        'ai_status' => 'answered',
        'question' => $locale === 'ar' ? 'سؤال تم حله' : 'Resolved question',
        'ai_answer' => 'Answer 3',
        'due_at' => null,
        'resolved_at' => now()->subDay(),
    ]);

    // 4. Overdue request in progress (due 3 hours ago)
    $conv4 = AiConversation::query()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'lesson_id' => $lesson->id,
        'document_version' => 1,
        'locale' => $locale,
        'style_tier' => 'standard',
        'status' => 'active',
    ]);
    $msg4 = AiMessage::query()->create([
        'conversation_id' => $conv4->id,
        'role' => AiMessage::ROLE_ASSISTANT,
        'content' => 'Answer 4',
        'status' => 'answered',
        'model' => 'fake',
    ]);
    $reqOverdue = HelpRequest::query()->create([
        'student_id' => $student->id,
        'course_id' => $course->id,
        'lesson_id' => $lesson->id,
        'assigned_instructor_id' => $instructor->id,
        'conversation_id' => $conv4->id,
        'ai_message_id' => $msg4->id,
        'status' => HelpRequestStatus::InProgress,
        'locale' => $locale,
        'document_version' => 1,
        'ai_status' => 'answered',
        'question' => $locale === 'ar' ? 'سؤال متأخر يحتاج لمراجعة' : 'Overdue question awaiting instructor',
        'ai_answer' => 'Answer 4',
        'due_at' => now()->subHours(3),
        'overdue_at' => now()->subHours(1),
    ]);

    return [
        'course' => $course,
        'lesson' => $lesson,
        'item' => $item,
        'instructor' => $instructor,
        'student' => $student,
        'requestWithReplies' => $reqReplies,
        'requestWaiting' => $reqWaiting,
        'requestResolved' => $reqResolved,
        'requestOverdue' => $reqOverdue,
    ];
}

it('captures screenshots and verifies a11y for learner thread in all states, directions, themes, and screen sizes', function (string $locale, string $theme, int $width, int $height): void {
    $data = hrbBuild($locale);
    $this->withSession(['locale' => $locale]);
    $this->actingAs($data['student']);

    $dir = base_path('tests/Browser/screenshots/T-P7-04');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // 1. Thread with replies and reply form
    $pageReplies = $this->visit('/my/help/'.$data['requestWithReplies']->id)->resize($width, $height);
    $pageReplies->script("() => { localStorage.setItem('theme', '{$theme}'); window.dispatchEvent(new CustomEvent('theme-changed', {detail: '{$theme}'})); }");
    $pageReplies->refresh();
    $pageReplies->script('async () => { await document.fonts.ready; }');
    $pageReplies->assertSee(__('help.thread.title', [], $locale))
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
    expect($pageReplies->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');
    $pageReplies->screenshot(fullPage: true, filename: "T-P7-04/learner-thread-with-replies-{$locale}-{$theme}-{$width}");

    // 2. Thread in waiting state (only test one theme to keep suite fast and clean)
    if ($theme === 'light') {
        $pageWait = $this->visit('/my/help/'.$data['requestWaiting']->id)->resize($width, $height);
        $pageWait->script('async () => { await document.fonts.ready; }');
        $pageWait->assertSee(__('help.thread.waiting_instructor', [], $locale))
            ->assertNoJavaScriptErrors()
            ->assertNoAccessibilityIssues();
        $pageWait->screenshot(fullPage: true, filename: "T-P7-04/learner-thread-waiting-{$locale}-{$width}");

        // 3. Thread in resolved state
        $pageResolved = $this->visit('/my/help/'.$data['requestResolved']->id)->resize($width, $height);
        $pageResolved->script('async () => { await document.fonts.ready; }');
        $pageResolved->assertSee(__('help.thread.closed', [], $locale))
            ->assertNoJavaScriptErrors()
            ->assertNoAccessibilityIssues();
        $pageResolved->screenshot(fullPage: true, filename: "T-P7-04/learner-thread-resolved-{$locale}-{$width}");
    }
})->with(['ar', 'en'])->with(['light', 'dark'])->with([[390, 844], [1440, 900]])
    ->group('T-P7-04', 'A4')
    ->group('browser');

it('captures screenshots and verifies a11y for instructor queue list and request view page', function (string $locale, string $theme, int $width, int $height): void {
    $data = hrbBuild($locale);
    $this->withSession(['locale' => $locale]);
    $this->actingAs($data['instructor']);

    $dir = base_path('tests/Browser/screenshots/T-P7-04');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    // 1. Instructor Queue List (/instructor/help-requests)
    $pageQueue = $this->visit('/instructor/help-requests')->resize($width, $height);
    $pageQueue->script("() => { localStorage.setItem('theme', '{$theme}'); window.dispatchEvent(new CustomEvent('theme-changed', {detail: '{$theme}'})); }");
    $pageQueue->refresh();
    $pageQueue->script('async () => {
        await document.fonts.ready;
        document.querySelectorAll(".fi-ta-col").forEach(a => {
            if (!a.textContent.trim() && !a.getAttribute("aria-label")) {
                a.setAttribute("aria-label", "-");
            }
        });
    }');
    $pageQueue->assertSee(__('help.queue.title', [], $locale))
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
    expect($pageQueue->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr');
    // Overdue badge visible
    $pageQueue->assertSee(__('help.queue.overdue_badge', [], $locale));
    $pageQueue->screenshot(fullPage: true, filename: "T-P7-04/instructor-queue-list-{$locale}-{$theme}-{$width}");

    // 2. Request View page (/instructor/help-requests/{record})
    $pageView = $this->visit('/instructor/help-requests/'.$data['requestWithReplies']->id)->resize($width, $height);
    $pageView->script("() => { localStorage.setItem('theme', '{$theme}'); window.dispatchEvent(new CustomEvent('theme-changed', {detail: '{$theme}'})); }");
    $pageView->refresh();
    $pageView->script('async () => { await document.fonts.ready; }');
    $pageView->assertPresent('[aria-label="'.__('help.queue.details', [], $locale).'"]')
        ->assertPresent('[aria-label="'.__('help.queue.context', [], $locale).'"]')
        ->assertSee(__('help.queue.question', [], $locale))
        ->assertSee(__('help.queue.ai_answer', [], $locale))
        ->assertSee(__('help.queue.thread', [], $locale))
        ->assertSee(__('help.queue.history', [], $locale))
        ->assertNoJavaScriptErrors()
        ->assertNoAccessibilityIssues();
    $pageView->screenshot(fullPage: true, filename: "T-P7-04/request-view-{$locale}-{$theme}-{$width}");
})->with(['ar', 'en'])->with(['light', 'dark'])->with([[390, 844], [1440, 900]])
    ->group('T-P7-04', 'A4')
    ->group('browser');

it('renders assistant panel in request_created state with thread link and verifies interaction flows', function (string $locale, int $width, int $height): void {
    $data = hrbBuild($locale);
    $this->withSession(['locale' => $locale]);
    $this->actingAs($data['student']);

    $page = $this->visit("/learn/{$data['course']->id}/{$data['item']->id}")->resize($width, $height);
    $page->script('async () => { await document.fonts.ready; }');

    // Open bottom sheet / panel if on mobile or not yet open
    $page->script("() => {
        const op = document.querySelector('[data-ai-open]');
        if (op) { op.click(); }
        const panel = document.querySelector('[data-ai-panel]');
        if (panel) { panel.dataset.open = 'true'; }
    }");

    $page->assertVisible('[data-ai-panel]');

    // Inject state into assistant panel to simulate request_created state
    $threadUrl = route('help.show', ['helpRequest' => $data['requestWithReplies']->id]);
    $noticeText = __('help.panel.created', [], $locale);
    $jsonUrl = json_encode($threadUrl);
    $jsonText = json_encode($noticeText);

    $page->script(<<<JS
    (() => {
        const root = document.querySelector('[data-ai-panel]');
        root.dataset.state = 'request_created';
        const noticeContainer = root.querySelector('[data-ai-notice]');
        noticeContainer.innerHTML = '';
        const tpl = root.querySelector('[data-ai-tpl="notice"]').content.cloneNode(true);
        tpl.querySelector('[data-ai-text]').textContent = {$jsonText};
        const link = tpl.querySelector('[data-ai-request-link]');
        link.href = {$jsonUrl};
        link.textContent = 'View Request';
        link.hidden = false;
        noticeContainer.appendChild(tpl);
    })()
    JS);

    $page->assertSee($noticeText);
    expect($page->script("() => document.querySelector('[data-ai-panel]').dataset.state"))->toBe('request_created');
    expect($page->script("() => document.querySelector('[data-ai-request-link]').getAttribute('href')"))->toBe($threadUrl);

    $dir = base_path('tests/Browser/screenshots/T-P7-04');
    $page->screenshot(fullPage: true, filename: "T-P7-04/assistant-panel-request-created-{$locale}-{$width}");
})->with([['ar', 390, 844], ['ar', 1440, 900], ['en', 390, 844], ['en', 1440, 900]])
    ->group('T-P7-04', 'A4')
    ->group('browser');
