<?php

// T-P2-05 | YG-WEB-004 | A1 A2

declare(strict_types=1);

use App\Domain\Catalog\CourseStatus;
use App\Domain\Curriculum\Actions\ManageAssessmentItems;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementService;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Identity\ActingLearner;
use App\Domain\Identity\Actions\CreateChildAccount;
use App\Domain\Media\Actions\AttachLessonMedia;
use App\Filament\Shared\Forms\SeoFieldsGroup;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\LessonResource;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    Storage::fake('local');
});

function pcdCourse(array $overrides = []): Course
{
    $uniq = uniqid();

    return Course::query()->create([
        'title' => ['ar' => 'أساسيات التمثيل المسرحي', 'en' => 'Stage Acting Basics'],
        'short_description' => ['ar' => 'مقدمة عملية للتمثيل.', 'en' => 'Practical introduction to acting.'],
        'description' => ['ar' => "السطر الأول\nالسطر الثاني", 'en' => "First line\nSecond line"],
        'slug_ar' => 'تمثيل-مسرحي-'.$uniq,
        'slug_en' => 'stage-acting-'.$uniq,
        'status' => CourseStatus::Published,
        'access_mode' => Course::ACCESS_PAID,
        'format' => 'recorded',
        'level' => 'beginner',
        'content_language' => 'ar',
        'min_age' => 10,
        'max_age' => 16,
        ...$overrides,
    ]);
}

function pcdPublishedCurriculum(Course $course): array
{
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $section = app(ManageSections::class)->add($admin, $course, ['title' => ['en' => 'Foundations', 'ar' => 'الأساسيات']]);
    $section->update(['published' => true]);

    $draftSection = app(ManageSections::class)->add($admin, $course, ['title' => ['en' => 'Hidden Section', 'ar' => 'قسم مخفي']]);

    // Preview text lesson
    $previewText = app(ManageLessons::class)->add($admin, $section, [
        'title' => ['en' => 'Free Text Lesson', 'ar' => 'درس نصي مجاني'],
        'type' => 'text',
    ]);
    $previewText->itemable->forceFill(['is_preview' => true, 'duration_seconds' => 300])->save();
    app(AttachLessonMedia::class)($admin, $previewText->itemable, ['provider' => 'rich_text', 'text' => 'Preview rich text body']);

    // Preview video lesson (YouTube ready MediaAsset)
    $previewVideo = app(ManageLessons::class)->add($admin, $section, [
        'title' => ['en' => 'Free Video Lesson', 'ar' => 'درس فيديو مجاني'],
        'type' => 'video',
    ]);
    $previewVideo->itemable->forceFill(['is_preview' => true, 'duration_seconds' => 600])->save();
    app(AttachLessonMedia::class)($admin, $previewVideo->itemable, ['provider' => 'youtube', 'url' => 'https://youtu.be/dQw4w9WgXcQ']);

    // Protected lesson with private resource & PDF
    $lockedLesson = app(ManageLessons::class)->add($admin, $section, [
        'title' => ['en' => 'Secret Masterclass', 'ar' => 'الدرس المحمي'],
        'type' => 'video',
    ]);
    Storage::disk('local')->put('media/secret-video.mp4', 'video-bytes');
    app(AttachLessonMedia::class)($admin, $lockedLesson->itemable, ['provider' => 'signed_storage', 'path' => 'media/secret-video.mp4']);
    LessonResource::query()->create([
        'lesson_id' => $lockedLesson->itemable_id,
        'title' => ['en' => 'Private Handout'],
        'type' => LessonResource::TYPE_FILE,
        'path' => 'resources/secret-handout-xyz.pdf',
        'mime' => 'application/pdf',
        'size' => 100,
        'is_public_preview' => false,
    ]);
    LessonCurriculumDocument::query()->create([
        'lesson_id' => $lockedLesson->itemable_id,
        'version' => 1,
        'path' => 'curriculum/secret-curriculum-xyz.pdf',
        'sha256' => str_repeat('a', 64),
        'size' => 120,
        'pages' => 1,
        'status' => LessonCurriculumDocument::STATUS_READY,
        'active' => true,
    ]);

    // Draft lesson in published section
    $draftItem = app(ManageLessons::class)->add($admin, $section, ['title' => ['en' => 'Draft Lesson Secret']]);
    $draftItem->itemable->forceFill(['is_preview' => true])->save();

    // Archived preview lesson (published before archiving)
    $archivedItem = app(ManageLessons::class)->add($admin, $section, ['title' => ['en' => 'Archived Lesson Secret']]);
    $archivedItem->itemable->forceFill(['is_preview' => true])->save();

    // Non-lesson item (quiz)
    $quizItem = app(ManageAssessmentItems::class)->add($admin, $section, 'quiz', ['title' => ['en' => 'Quiz Secret']]);

    // Item in unpublished section
    $hiddenSectionItem = app(ManageLessons::class)->add($admin, $draftSection, ['title' => ['en' => 'Unpublished Section Secret']]);
    $hiddenSectionItem->itemable->forceFill(['is_preview' => true])->save();

    app(SetCurriculumStatus::class)($admin, $course, [
        $previewText->id,
        $previewVideo->id,
        $lockedLesson->id,
        $archivedItem->id,
        $quizItem->id,
        $hiddenSectionItem->id,
    ], CurriculumItem::STATUS_PUBLISHED);

    $archivedItem->itemable->forceFill(['archived_at' => now()])->save();

    return compact(
        'admin',
        'section',
        'previewText',
        'previewVideo',
        'lockedLesson',
        'draftItem',
        'archivedItem',
        'quizItem',
        'hiddenSectionItem'
    );
}

it('returns 404 publicly for every non-published course status even when signed in as admin', function (CourseStatus $status): void {
    $course = pcdCourse(['status' => $status]);
    $items = pcdPublishedCurriculum($course);
    $admin = $items['admin'];

    $this->get('/courses/'.$course->slug_en)->assertNotFound();
    $this->get('/courses/'.rawurlencode($course->slug_ar))->assertNotFound();
    $this->get('/courses/'.$course->id)->assertNotFound();
    $this->get("/courses/{$course->id}/preview/{$items['previewText']->id}")->assertNotFound();

    $this->actingAs($admin)->get('/courses/'.$course->slug_en)->assertNotFound();
    $this->actingAs($admin)->get("/courses/{$course->id}/preview/{$items['previewText']->id}")->assertNotFound();
})->with([
    'draft' => CourseStatus::Draft,
    'pending_review' => CourseStatus::PendingReview,
    'private' => CourseStatus::Private,
    'upcoming' => CourseStatus::Upcoming,
    'suspended' => CourseStatus::Suspended,
    'archived' => CourseStatus::Archived,
])->group('T-P2-05', 'A1', 'YG-WEB-004');

it('serves published courses by AR slug, EN slug, and numeric id for guests and signed-in users while 404ing unknown slugs', function (): void {
    $course = pcdCourse();
    $student = User::factory()->create(['status' => 'active'])->assignRole('student');

    $this->get('/courses/does-not-exist-course')->assertNotFound();

    $this->withSession(['locale' => 'ar'])
        ->get('/courses/'.rawurlencode($course->slug_ar))
        ->assertOk()
        ->assertSee('data-course-page', false)
        ->assertSee($course->getTranslation('title', 'ar'));

    $this->withSession(['locale' => 'en'])
        ->get('/courses/'.$course->slug_en)
        ->assertOk()
        ->assertSee($course->getTranslation('title', 'en'));

    $this->actingAs($student)
        ->get('/courses/'.$course->id)
        ->assertOk();
})->group('T-P2-05', 'A1', 'YG-WEB-004');

it('plays explicit preview lessons for guests and denies protected, non-lesson, draft, archived, and foreign items', function (): void {
    $course = pcdCourse();
    $items = pcdPublishedCurriculum($course);
    $otherCourse = pcdCourse();
    $otherItems = pcdPublishedCurriculum($otherCourse);

    // Course detail page exposes preview links and hides protected/draft/hidden content & private URLs
    $html = $this->withSession(['locale' => 'en'])
        ->get('/courses/'.$course->slug_en)
        ->assertOk()
        ->assertSee('data-state="preview"', false)
        ->assertSee('data-state="locked"', false)
        ->assertSee('data-lock', false)
        ->assertSee($items['previewText']->itemable->title)
        ->assertSee($items['previewVideo']->itemable->title)
        ->assertSee($items['lockedLesson']->itemable->title)
        ->assertDontSee('Draft Lesson Secret')
        ->assertDontSee('Archived Lesson Secret')
        ->assertDontSee('Hidden Section')
        ->assertDontSee('Unpublished Section Secret')
        ->assertDontSee('secret-video.mp4')
        ->assertDontSee('secret-handout-xyz.pdf')
        ->assertDontSee('secret-curriculum-xyz.pdf')
        ->getContent();

    expect($html)->not->toContain('/files/')->not->toContain('/media/');

    // Preview text lesson
    $this->get("/courses/{$course->slug_en}/preview/{$items['previewText']->id}")
        ->assertOk()
        ->assertSee('data-public-preview', false)
        ->assertSee('data-player-stage', false)
        ->assertSee('Preview rich text body');

    // Preview YouTube video lesson
    $this->get("/courses/{$course->slug_en}/preview/{$items['previewVideo']->id}")
        ->assertOk()
        ->assertSee('data-public-preview', false)
        ->assertSee('data-player-stage', false)
        ->assertSee('dQw4w9WgXcQ');

    // Denied items return 404 and leak no title
    foreach ([
        [$items['lockedLesson'], 'Secret Masterclass'],
        [$items['quizItem'], 'Quiz Secret'],
        [$items['draftItem'], 'Draft Lesson Secret'],
        [$items['archivedItem'], 'Archived Lesson Secret'],
        [$items['hiddenSectionItem'], 'Unpublished Section Secret'],
        [$otherItems['previewText'], 'Free Text Lesson'],
    ] as [$blockedItem, $title]) {
        $this->get("/courses/{$course->slug_en}/preview/{$blockedItem->id}")
            ->assertNotFound()
            ->assertDontSee($title);
    }

    $this->get("/courses/{$course->slug_en}/preview/999999")->assertNotFound();

    // EntitlementService::canPreviewPublicly reason codes and unchanged private file access
    $access = app(EntitlementService::class);
    $guest = User::factory()->create(['status' => 'active'])->assignRole('student');

    expect($access->canPreviewPublicly($items['previewText']->fresh(['course', 'section', 'itemable']))->allowed)->toBeTrue()
        ->and($access->canPreviewPublicly($items['lockedLesson']->fresh(['course', 'section', 'itemable']))->reason)->toBe('not_entitled')
        ->and($access->canPreviewPublicly($items['draftItem']->fresh(['course', 'section', 'itemable']))->reason)->toBe('item_unpublished')
        ->and($access->canPreviewPublicly($items['hiddenSectionItem']->fresh(['course', 'section', 'itemable']))->reason)->toBe('item_unpublished')
        ->and($access->canPreviewPublicly($items['quizItem']->fresh(['course', 'section', 'itemable']))->reason)->toBe('not_entitled')
        ->and($access->canAccessLessonFiles($guest, $items['previewText']->itemable))->toBeFalse();

    $course->update(['status' => CourseStatus::Draft]);
    expect($access->canPreviewPublicly($items['previewText']->fresh(['course', 'section', 'itemable']))->reason)->toBe('course_unpublished');
})->group('T-P2-05', 'A2', 'YG-WEB-004');

it('outputs escaped SEO meta tags, Course JSON-LD, bilingual content blocks, and correct CTA states', function (): void {
    $course = pcdCourse([
        'title' => ['en' => 'Acting </script><script>alert(1)</script>', 'ar' => 'التمثيل الإبداعي'],
        'seo_title' => ['en' => 'SEO Acting Title', 'ar' => 'عنوان سيو التمثيل'],
        'seo_description' => ['en' => 'Custom SEO meta description', 'ar' => 'وصف سيو مخصص'],
        'seo_keywords' => ['en' => 'acting, drama', 'ar' => 'تمثيل, دراما'],
        'canonical_url' => 'https://youngschool.test/en/custom-acting',
        'outcomes' => [['text' => ['en' => '<b>Confident voice</b>', 'ar' => 'صوت واثق']]],
        'requirements' => [['text' => ['en' => 'Notebook only', 'ar' => 'دفتر فقط']]],
        'faqs' => [['question' => ['en' => 'Any age?', 'ar' => 'أي عمر؟'], 'answer' => ['en' => 'Yes <script>x</script>', 'ar' => 'نعم']]],
    ]);
    $instructor = User::factory()->create(['name' => 'Tarek <img src=x>', 'status' => 'active']);
    $course->instructors()->attach($instructor->id);

    $response = $this->withSession(['locale' => 'en'])->get('/courses/'.$course->slug_en)->assertOk();
    $html = $response->getContent();

    $response->assertSee('<title>SEO Acting Title', false)
        ->assertSee('<meta name="description" content="Custom SEO meta description">', false)
        ->assertSee('<meta name="keywords" content="acting, drama">', false)
        ->assertSee('<meta name="robots" content="index, follow">', false)
        ->assertSee('<link rel="canonical" href="https://youngschool.test/en/custom-acting">', false)
        ->assertSee('<meta property="og:locale" content="en_US">', false)
        ->assertSee('data-section="outcomes"', false)
        ->assertSee('data-section="requirements"', false)
        ->assertSee('data-section="faq"', false)
        ->assertSee('data-cta="sign-in"', false)
        ->assertSee('data-mobile-cta', false)
        ->assertDontSee('<b>Confident voice</b>', false)
        ->assertDontSee('<img src=x>', false)
        ->assertDontSee('alert(1)</script>', false);

    // The plain catalog is indexable now (T-P2-04); filtered/sorted/paged views stay noindex, follow
    $this->get('/courses')->assertOk()->assertSee('<meta name="robots" content="index, follow">', false);

    // JSON-LD decodes cleanly to Course + BreadcrumbList
    preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $matches);
    expect($matches[1])->toHaveCount(2);
    $courseSchema = json_decode($matches[1][0], true, 512, JSON_THROW_ON_ERROR);
    expect($courseSchema['@type'])->toBe('Course')
        ->and($courseSchema['name'])->toBe('SEO Acting Title');

    // Empty optional blocks stay hidden
    $minimal = pcdCourse(['outcomes' => null, 'requirements' => null, 'faqs' => null]);
    $this->get('/courses/'.$minimal->slug_en)->assertOk()
        ->assertDontSee('data-section="outcomes"', false)
        ->assertDontSee('data-section="requirements"', false)
        ->assertDontSee('data-section="faq"', false)
        ->assertSee('data-curriculum-empty', false);

    // Signed-in non-entitled user vs entitled user vs parent acting as entitled child
    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');
    $this->actingAs($learner)->get('/courses/'.$course->slug_en)->assertOk()->assertSee('data-cta="soon"', false);

    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);
    $this->actingAs($learner)->get('/courses/'.$course->slug_en)->assertOk()->assertSee('data-cta="continue"', false);

    $parent = User::factory()->create(['status' => 'active', 'email_verified_at' => now()])->assignRole('parent');
    $child = app(CreateChildAccount::class)->handle($parent, [
        'username' => 'pcd_child_'.uniqid(),
        'password' => 'ChildPass123!',
        'name_en' => 'Child One',
        'birth_year' => 2016,
        'birth_month' => 5,
    ]);
    app(GrantEntitlement::class)($child, $course, EntitlementSource::Free);

    $this->actingAs($parent)->withSession([ActingLearner::SESSION_KEY => $child->id])
        ->get('/courses/'.$course->slug_en)
        ->assertOk()
        ->assertSee('data-cta="continue"', false);

    // Catalog cards link to catalog.show
    $this->get('/courses')->assertOk()->assertSee('data-course-link', false);
})->group('T-P2-05', 'YG-WEB-004');

it('persists SEO columns on courses and builds the shared SeoFieldsGroup form section', function (): void {
    expect(Schema::hasColumns('courses', ['seo_title', 'seo_description', 'seo_keywords', 'canonical_url']))->toBeTrue();

    $course = pcdCourse([
        'seo_title' => ['ar' => 'عنوان عربي', 'en' => 'English Title'],
        'seo_description' => ['ar' => 'وصف عربي', 'en' => 'English Description'],
        'seo_keywords' => ['ar' => 'كلمات', 'en' => 'keywords'],
        'canonical_url' => 'https://example.test/canonical',
    ]);
    $fresh = $course->fresh();
    expect($fresh->getTranslation('seo_title', 'ar', false))->toBe('عنوان عربي')
        ->and($fresh->getTranslation('seo_title', 'en', false))->toBe('English Title')
        ->and($fresh->canonical_url)->toBe('https://example.test/canonical');

    $section = SeoFieldsGroup::make();
    expect($section)->toBeInstanceOf(Section::class);

    $names = [];
    $collect = function (array $components) use (&$collect, &$names): void {
        foreach ($components as $component) {
            if ($component instanceof TextInput || $component instanceof Textarea) {
                $names[] = $component->getName();
            }
            if (method_exists($component, 'getDefaultChildComponents')) {
                $collect($component->getDefaultChildComponents());
            }
        }
    };
    $collect($section->getDefaultChildComponents());

    expect($names)->toContain(
        'seo_title.ar',
        'seo_title.en',
        'seo_description.ar',
        'seo_description.en',
        'seo_keywords.ar',
        'seo_keywords.en',
        'canonical_url'
    );
})->group('T-P2-05', 'YG-WEB-004');
