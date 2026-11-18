<?php

declare(strict_types=1);

// Copy to tests/Browser/UIRefinementMatrixTest.php, then run that file with YG_UI_CAPTURE_PHASE=BASELINE|AFTER.
// Remove the temporary copy afterward. Pest browser discovery requires the configured tests directory.
// T-UI-00 | D-026, ED-023, YG-GAP-A11Y-001 | Additional captures; existing browser assertions remain unchanged.

use App\Domain\AI\Ingestion\Contracts\PdfTextExtractor;
use App\Domain\AI\Ingestion\IngestCurriculumDocument;
use App\Domain\Cohorts\Actions\JoinWaitlist;
use App\Domain\Cohorts\Actions\ManageCohorts;
use App\Domain\Cohorts\Actions\ReserveCohortSeat;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Identity\TwoFactorSession;
use App\Domain\Library\Actions\AddLibraryItem;
use App\Domain\Live\Actions\ScheduleLiveSession;
use App\Domain\Media\Actions\AttachLessonMedia;
use App\Domain\Progress\Actions\RecordProgress;
use App\Models\Category;
use App\Models\CmsPage;
use App\Models\Course;
use App\Models\HomepageBlock;
use App\Models\LessonCurriculumDocument;
use App\Models\LiveSession;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\File\File;

uses(RefreshDatabase::class);
pest()->browser()->timeout(15000);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

$uiRefinementViewports = [
    ['ar', 'light', 390, 844], ['en', 'light', 390, 844],
    ['ar', 'dark', 390, 844], ['en', 'dark', 390, 844],
    ['ar', 'light', 1440, 900], ['en', 'light', 1440, 900],
    ['ar', 'dark', 1440, 900], ['en', 'dark', 1440, 900],
];

function uiRefinementUser(string $role, string $locale, array $attributes = []): User
{
    return User::factory()->create([
        'locale' => $locale,
        'name' => $locale === 'ar' ? 'سارة عبد الرحمن' : 'Sara Abdelrahman',
        ...$attributes,
    ])->assignRole($role);
}

/** Save even failing baseline states, then return every observed failure for the group's final assertion. */
function uiRefinementCapture($page, string $name, string $locale, string $theme, int $width, int $height, ?string $required = null, ?string $expectedPath = null): array
{
    if ($expectedPath === null) {
        throw new InvalidArgumentException('Every capture must specify its intended route.');
    }
    $normalizePath = static fn (string $path): string => rtrim(rawurldecode((string) parse_url($path, PHP_URL_PATH)), '/') ?: '/';
    $expectedPath = $normalizePath($expectedPath);
    $initialPath = $normalizePath($page->script('() => location.pathname'));
    $phase = getenv('YG_UI_CAPTURE_PHASE') ?: 'BASELINE';
    if (! in_array($phase, ['BASELINE', 'AFTER'], true)) {
        throw new InvalidArgumentException('YG_UI_CAPTURE_PHASE must be BASELINE or AFTER.');
    }
    $directory = base_path('docs/reviews/UI_'.$phase.'/matrix');
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
    $filename = "{$name}-{$locale}-{$theme}-{$width}";
    $page->resize($width, $height);
    $page->script("() => { localStorage.setItem('theme', '{$theme}'); window.dispatchEvent(new CustomEvent('theme-changed', {detail: '{$theme}'})); }");
    $page->refresh();
    $page->script('async () => { await document.fonts.ready; await new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve))); }');
    // Pest always prefixes its screenshot folder; this fixed relative path reaches the evidence folder.
    $page->screenshot(fullPage: true, filename: '../../../docs/reviews/UI_'.$phase.'/matrix/'.$filename);

    $failures = [];
    foreach ([
        'route' => fn () => expect([$initialPath, $normalizePath($page->script('() => location.pathname'))])->toBe([$expectedPath, $expectedPath]),
        'content' => fn () => $page->assertVisible(':is(main)')->assertPresent($required ?? ':is(main) h1'),
        'javascript' => fn () => $page->assertNoJavaScriptErrors(),
        'accessibility' => fn () => $page->assertNoAccessibilityIssues(),
        'direction' => fn () => expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr'),
        'theme' => fn () => expect($page->script("() => document.documentElement.dataset.theme ?? (document.documentElement.classList.contains('dark') ? 'dark' : 'light')"))->toBe($theme),
        'overflow' => fn () => expect($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue(),
    ] as $check => $assertion) {
        try {
            $assertion();
        } catch (Throwable $exception) {
            $failures[$filename.':'.$check] = $exception->getMessage();
        }
    }
    $metrics = $page->script('() => ({url: location.pathname, viewport: {width: innerWidth, height: innerHeight}, width: document.documentElement.scrollWidth, headings: [...document.querySelectorAll("h1")].map(h => h.textContent.trim()), overflowing: [...document.querySelectorAll("main *")].filter(e => {const b = e.getBoundingClientRect(); return b.width && (b.left < -1 || b.right > innerWidth + 1);}).slice(0, 15).map(e => ({tag: e.tagName, classes: e.className, text: e.textContent.trim().slice(0, 80)}))})');
    file_put_contents($directory.'/'.$filename.'.json', json_encode([
        'captured_at' => gmdate(DATE_ATOM), 'screen' => $name, 'locale' => $locale,
        'theme' => $theme, 'expected_path' => $expectedPath, 'initial_path' => $initialPath, 'metrics' => $metrics, 'failures' => $failures,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

    return $failures;
}

/** A real current hybrid curriculum; only isolated test data is created. */
function uiRefinementCurriculum(string $locale): array
{
    Storage::fake('local');
    $admin = uiRefinementUser('admin', $locale);
    $instructor = uiRefinementUser('instructor_academy', $locale, ['name' => $locale === 'ar' ? 'نور محمد' : 'Nour Mohamed']);
    $learner = uiRefinementUser('student', $locale);
    $course = Course::query()->create([
        'title' => ['ar' => 'أساسيات الرسم والتعبير الإبداعي', 'en' => 'Drawing and creative expression basics'],
        'slug_ar' => 'ui-drawing-ar', 'slug_en' => 'ui-drawing-en',
        'status' => 'published', 'access_mode' => 'free', 'format' => 'hybrid',
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['ar' => 'البداية والأدوات', 'en' => 'Getting started and tools']]);
    $section->update(['published' => true]);
    $items = [];
    foreach ([
        'text' => ['الضوء والظل', 'Light and shadow', 'text'],
        'video' => ['التعرف على الأدوات', 'Know your tools', 'video'],
        'locked' => ['المشروع التطبيقي', 'Practice project', 'text'],
        'draft' => ['درس قيد الإعداد', 'Lesson in preparation', 'text'],
    ] as $key => [$ar, $en, $type]) {
        $items[$key] = app(ManageLessons::class)->add($instructor, $section, ['title' => ['ar' => $ar, 'en' => $en], 'type' => $type, 'duration_seconds' => 600]);
    }
    app(ManageLessons::class)->update($instructor, $items['text']->itemable, [
        'title' => ['ar' => 'الضوء والظل', 'en' => 'Light and shadow'], 'type' => 'text',
        'content' => ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'مقياس الضوء والظل — Light and shadow.']]]]],
    ]);
    Storage::disk('local')->put('media/ui-review.mp4', file_get_contents(base_path('tests/Fixtures/lesson-player.mp4')));
    app(AttachLessonMedia::class)($instructor, $items['video']->itemable, ['provider' => 'signed_storage', 'path' => 'media/ui-review.mp4']);
    app(SetCurriculumStatus::class)($instructor, $course, [$items['text']->id, $items['video']->id, $items['locked']->id], 'published');
    $items['locked']->refresh()->update(['unlock_rule' => 'date', 'unlock_at' => now()->addWeek()]);
    foreach (['upcoming', 'joinable', 'cancelled', 'ended'] as $state) {
        $start = match ($state) {
            'joinable' => now()->subMinutes(5), 'ended' => now()->subHours(2), default => now()->addDays(2),
        };
        $items[$state] = app(ScheduleLiveSession::class)($admin, $section, [
            'title' => ['ar' => 'ورشة الرسم المباشرة', 'en' => 'Live drawing workshop'],
            'description' => ['ar' => 'نتعرف على الأدوات ونبدأ التمرين معًا.', 'en' => 'We meet the tools and start the exercise together.'],
            'starts_at' => $start->format('Y-m-d H:i'), 'ends_at' => $start->copy()->addHour()->format('Y-m-d H:i'),
            'timezone' => 'UTC', 'provider' => 'manual_zoom', 'meeting_url' => 'https://zoom.example.com/j/ui-review', 'join_window_minutes' => 15,
        ]);
        app(SetCurriculumStatus::class)($admin, $course, [$items[$state]->id], 'published');
        if ($state === 'cancelled') {
            $items[$state]->itemable->update(['status' => LiveSession::CANCELLED]);
        }
    }
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);
    app(RecordProgress::class)->markComplete($learner, $items['text']->refresh());

    return compact('course', 'section', 'items', 'admin', 'instructor', 'learner');
}

it('captures public pages and guest identity forms in the complete UI matrix', function (string $locale, string $theme, int $width, int $height): void {
    $this->withSession(['locale' => $locale]);
    config(['identity.terms_version' => 'test-terms', 'identity.privacy_version' => 'test-privacy']);
    HomepageBlock::query()->create(['type' => 'hero', 'visible' => true, 'title' => ['ar' => 'مساحة للتعلم والإبداع', 'en' => 'A space to learn and create'], 'body' => ['ar' => 'اكتشف ورش الرسم والحكي.', 'en' => 'Discover drawing and storytelling workshops.']]);
    $category = Category::query()->create(['name' => ['ar' => 'الفنون', 'en' => 'Arts'], 'slug_ar' => 'arts-ar', 'slug_en' => 'arts-en']);
    Course::query()->create(['title' => ['ar' => 'أساسيات الرسم', 'en' => 'Drawing basics'], 'slug_ar' => 'drawing-ar', 'slug_en' => 'drawing-en', 'status' => 'published', 'access_mode' => 'free', 'category_id' => $category->id]);
    CmsPage::query()->create(['slug' => 'ui-workshops', 'title' => ['ar' => 'ورش إبداعية', 'en' => 'Creative workshops'], 'body' => ['ar' => 'ورش التمثيل والرسم للمبدعين.', 'en' => 'Acting and drawing workshops for creative learners.'], 'status' => CmsPage::PUBLISHED]);
    $failures = [];
    foreach ([
        'home' => '/', 'catalog' => '/courses', 'catalog-empty' => '/courses?q=no-matching-course',
        'cms-page' => '/pages/ui-workshops', 'login' => '/login', 'register' => '/register',
        'forgot-password' => '/forgot-password', 'reset-password' => '/reset-password/ui-review-token?email=review%40example.test',
        'error-404' => '/missing-page-ui-review',
    ] as $name => $path) {
        $failures += uiRefinementCapture($this->visit($path), $name, $locale, $theme, $width, $height, expectedPath: $path);
    }
    expect($failures)->toBe([]);
})->with($uiRefinementViewports)->group('browser', 'T-UI-00', 'D-026', 'YG-GAP-A11Y-001');

it('captures learner and parent identity pages in the complete UI matrix', function (string $locale, string $theme, int $width, int $height): void {
    $this->withSession(['locale' => $locale]);
    $learner = uiRefinementUser('student', $locale);
    $this->actingAs($learner);
    $failures = [];
    foreach (['profile' => '/profile', 'learner-link' => '/link', 'two-factor-setup' => '/security/two-factor'] as $name => $path) {
        $failures += uiRefinementCapture($this->visit($path), $name, $locale, $theme, $width, $height, expectedPath: $path);
    }
    $unverified = uiRefinementUser('student', $locale, ['email_verified_at' => null]);
    $this->actingAs($unverified);
    $failures += uiRefinementCapture($this->visit('/email/verify'), 'verify-email', $locale, $theme, $width, $height, expectedPath: '/email/verify');
    $enrolled = uiRefinementUser('student', $locale, ['two_factor_secret' => 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()]);
    $this->actingAs($enrolled);
    $failures += uiRefinementCapture($this->visit('/two-factor-challenge'), 'two-factor-challenge', $locale, $theme, $width, $height, expectedPath: '/two-factor-challenge');
    $this->withSession([TwoFactorSession::KEY => $enrolled->id]);
    $failures += uiRefinementCapture($this->visit('/security/two-factor'), 'two-factor-enabled', $locale, $theme, $width, $height, expectedPath: '/security/two-factor');
    $parent = uiRefinementUser('parent', $locale);
    GuardianChildLink::query()->create(['guardian_id' => $parent->id, 'child_id' => $learner->id, 'status' => 'verified', 'method' => 'created_by_parent', 'verified_at' => now()]);
    $this->actingAs($parent);
    $failures += uiRefinementCapture($this->visit('/family'), 'family', $locale, $theme, $width, $height, expectedPath: '/family');
    $this->withSession(['acting_child_id' => $learner->id]);
    $failures += uiRefinementCapture($this->visit('/family'), 'family-child-context', $locale, $theme, $width, $height, '[data-child-context]', expectedPath: '/family');
    expect($failures)->toBe([]);
})->with($uiRefinementViewports)->group('browser', 'T-UI-00', 'D-026', 'YG-GAP-A11Y-001');

it('captures learning, locks and existing live states in the complete UI matrix', function (string $locale, string $theme, int $width, int $height): void {
    $fixture = uiRefinementCurriculum($locale);
    ['course' => $course, 'items' => $items, 'learner' => $learner] = $fixture;
    $this->withSession(['locale' => $locale])->actingAs($learner);
    $failures = [];
    foreach (['dashboard' => '/my', 'my-courses' => '/my/courses'] as $name => $path) {
        $failures += uiRefinementCapture($this->visit($path), $name, $locale, $theme, $width, $height, expectedPath: $path);
    }
    foreach (['text', 'video', 'locked', 'upcoming', 'joinable', 'cancelled', 'ended'] as $state) {
        $required = match ($state) {
            'locked' => '[data-lock-reason]', 'upcoming', 'joinable', 'cancelled', 'ended' => '[data-live-state="'.$state.'"]', default => null,
        };
        $path = "/learn/{$course->id}/{$items[$state]->id}";
        $failures += uiRefinementCapture($this->visit($path), 'learn-'.$state, $locale, $theme, $width, $height, $required, expectedPath: $path);
    }
    // Exercise the existing flash-message state without making a meeting-provider request.
    $this->withSession(['join_error' => __('live.ended_notice', [], $locale)]);
    $path = "/learn/{$course->id}/{$items['ended']->id}";
    $failures += uiRefinementCapture($this->visit($path), 'learn-live-error', $locale, $theme, $width, $height, '[data-join-error]', expectedPath: $path);
    $parent = uiRefinementUser('parent', $locale);
    GuardianChildLink::query()->create(['guardian_id' => $parent->id, 'child_id' => $learner->id, 'status' => 'verified', 'method' => 'created_by_parent', 'verified_at' => now()]);
    $this->actingAs($parent);
    $failures += uiRefinementCapture($this->visit('/parent'), 'parent-dashboard', $locale, $theme, $width, $height, expectedPath: '/parent');
    $this->actingAs(uiRefinementUser('parent', $locale));
    $failures += uiRefinementCapture($this->visit('/parent'), 'parent-empty', $locale, $theme, $width, $height, expectedPath: '/parent');
    expect($failures)->toBe([]);
})->with($uiRefinementViewports)->group('browser', 'T-UI-00', 'D-026', 'YG-GAP-A11Y-001');

it('captures staff curriculum, previews and uncovered native pages in the complete UI matrix', function (string $locale, string $theme, int $width, int $height): void {
    ['course' => $course, 'items' => $items, 'learner' => $learner, 'admin' => $admin, 'instructor' => $instructor] = uiRefinementCurriculum($locale);
    $cohort = app(ManageCohorts::class)->create($admin, $course, ['name' => $locale === 'ar' ? 'مجموعة الإبداع' : 'Creative group', 'capacity' => 1]);
    app(ReserveCohortSeat::class)($cohort, $learner);
    app(JoinWaitlist::class)($cohort, uiRefinementUser('student', $locale, ['name' => $locale === 'ar' ? 'عمر حسن' : 'Omar Hassan']));
    $this->withSession(['locale' => $locale]);
    $failures = [];
    foreach (['admin' => $admin, 'instructor' => $instructor] as $panel => $actor) {
        $this->actingAs($actor);
        foreach (['curriculum' => 'curriculum', 'learners' => 'learners', 'cohorts' => 'cohorts', 'wizard-edit' => 'edit'] as $name => $suffix) {
            $path = "/{$panel}/courses/{$course->id}/{$suffix}";
            $failures += uiRefinementCapture($this->visit($path), $panel.'-'.$name, $locale, $theme, $width, $height, expectedPath: $path);
        }
        $course->update(['wizard_step' => 2]);
        $path = "/{$panel}/courses/{$course->id}/edit";
        $failures += uiRefinementCapture($this->visit($path), $panel.'-wizard-information', $locale, $theme, $width, $height, expectedPath: $path);
        $course->update(['wizard_step' => 1]);
        $path = '/'.$panel.'/courses/create';
        $failures += uiRefinementCapture($this->visit($path), $panel.'-wizard-create', $locale, $theme, $width, $height, expectedPath: $path);
    }
    app(AddLibraryItem::class)($admin, new File(base_path('tests/Fixtures/lesson-player.mp4')), ['title' => ['ar' => 'مرجع أدوات الرسم', 'en' => 'Drawing tools reference'], 'scope' => 'academy', 'tags' => ['drawing']]);
    foreach (['admin' => $admin, 'instructor' => $instructor] as $panel => $actor) {
        $this->actingAs($actor);
        $path = '/'.$panel.'/library';
        $failures += uiRefinementCapture($this->visit($path), $panel.'-library-populated', $locale, $theme, $width, $height, expectedPath: $path);
    }
    foreach (['admin' => $admin, 'finance' => uiRefinementUser('finance', $locale)] as $panel => $actor) {
        $this->actingAs($actor);
        $path = '/'.$panel.'/manual-grants';
        $failures += uiRefinementCapture($this->visit($path), $panel.'-grants-populated', $locale, $theme, $width, $height, expectedPath: $path);
    }
    $this->actingAs($instructor);
    // Follow the real preview entry point so Filament's panel context is the instructor panel.
    $this->visit("/instructor/courses/{$course->id}/curriculum")->assertVisible('[data-section]');
    foreach (['draft', 'locked', 'upcoming'] as $state) {
        $path = "/preview/{$course->id}/{$items[$state]->id}";
        $failures += uiRefinementCapture($this->visit($path), 'preview-'.$state, $locale, $theme, $width, $height, '[data-preview-banner]', expectedPath: $path);
    }
    $draft = CmsPage::query()->create(['slug' => 'ui-draft', 'title' => ['ar' => 'مسودة الورشة', 'en' => 'Draft workshop'], 'body' => ['ar' => 'محتوى للمراجعة قبل النشر.', 'en' => 'Content for review before publication.'], 'status' => CmsPage::DRAFT]);
    $this->actingAs($admin);
    $path = URL::temporarySignedRoute('cms.preview', now()->addMinutes(10), ['page' => $draft]);
    $failures += uiRefinementCapture($this->visit($path), 'cms-preview', $locale, $theme, $width, $height, expectedPath: $path);
    Storage::disk('local')->put('documents/ui-review.pdf', '%PDF-1.4 fake');
    $document = LessonCurriculumDocument::query()->create([
        'lesson_id' => $items['text']->itemable_id, 'version' => 1, 'path' => 'documents/ui-review.pdf',
        'sha256' => hash('sha256', 'ui-review-'.$locale), 'size' => 13, 'pages' => 2,
        'status' => LessonCurriculumDocument::STATUS_UPLOADED, 'active' => false,
    ]);
    app()->instance(PdfTextExtractor::class, new class implements PdfTextExtractor
    {
        public function extract(string $absolutePath): array
        {
            return [
                'الضوء والظل يعطيان الرسم عمقاً. الضوء يأتي من مصدر واحد، والظل يتبع اتجاه الضوء دائماً.',
                'Light and shadow give a drawing depth. Shadow follows the direction of the light source.',
            ];
        }
    });
    app(IngestCurriculumDocument::class)($document);
    $path = "/admin/courses/{$course->id}/curriculum/documents/{$document->id}";
    $failures += uiRefinementCapture($this->visit($path), 'admin-document-review', $locale, $theme, $width, $height, '[wire\\:click="approve"]', expectedPath: $path);
    expect($failures)->toBe([]);
})->with($uiRefinementViewports)->group('browser', 'T-UI-00', 'D-026', 'YG-GAP-A11Y-001');

it('captures branded error pages in the complete UI matrix', function (string $locale, string $theme, int $width, int $height): void {
    config(['app.debug' => false]);
    $this->withSession(['locale' => $locale]);
    $failures = [];
    foreach ([403, 419, 429, 500, 503] as $status) {
        Route::middleware('web')->get('/_ui-review-error/'.$status, static fn () => abort($status));
        $path = '/_ui-review-error/'.$status;
        $failures += uiRefinementCapture($this->visit($path), 'error-'.$status, $locale, $theme, $width, $height, expectedPath: $path);
    }
    expect($failures)->toBe([]);
})->with($uiRefinementViewports)->group('browser', 'T-UI-00', 'D-026', 'YG-GAP-A11Y-001');

it('captures full shared galleries in the complete UI matrix', function (string $locale, string $theme, int $width, int $height): void {
    $this->app['env'] = 'staging';
    config(['design.token_preview' => true]);
    $failures = [];
    foreach (['tokens', 'components'] as $gallery) {
        $path = "/_design/{$gallery}?locale={$locale}&theme={$theme}";
        $failures += uiRefinementCapture($this->visit($path), 'gallery-'.$gallery, $locale, $theme, $width, $height, expectedPath: $path);
    }
    expect($failures)->toBe([]);
})->with($uiRefinementViewports)->group('browser', 'T-UI-00', 'D-026', 'YG-GAP-A11Y-001');
