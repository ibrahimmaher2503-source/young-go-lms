<?php

declare(strict_types=1);

use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Live\Actions\ScheduleLiveSession;
use App\Domain\Media\Actions\AttachLessonMedia;
use App\Domain\Settings\PlatformFlag;
use App\Models\AiPromptVersion;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\LessonDocumentPage;
use App\Models\LessonKeyTerm;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Laravel\Pennant\Feature;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

/** @return array{student: User, instructor: User, course: Course, videoItem: CurriculumItem, videoLesson: Lesson, textItem: CurriculumItem, liveItem: CurriculumItem, document: LessonCurriculumDocument} */
function apanSetup(): array
{
    Config::set('ai.enabled', true);
    Config::set('ai.features.assistant', true);
    Config::set('ai.provider', 'fake');
    Config::set('ai.adult_age', 18);
    Feature::for(null)->activate(PlatformFlag::AiAssistant->value);

    AiPromptVersion::current() ?? AiPromptVersion::query()->create([
        'version' => 1,
        'template' => "Answer strictly from lesson source.\n{style_tier_instructions}\n{reply_language}\n{lesson_title}\nv{document_version}\n<lesson_source>\n{page_tagged_text}</lesson_source>",
        'style_tiers' => ['adults' => 'Clear tone', 'teens' => 'Teen tone', 'kids' => 'Kids tone'],
        'active' => true,
    ]);

    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $student = User::factory()->create(['status' => 'active', 'birth_year' => 1995, 'birth_month' => 2])->assignRole('student');
    $course = Course::query()->create([
        'title' => ['en' => 'Light & Shadow', 'ar' => 'الضوء والظل'],
        'slug_ar' => 'apan-ar-'.uniqid(),
        'slug_en' => 'apan-en-'.uniqid(),
        'status' => 'published',
        'access_mode' => Course::ACCESS_FREE,
        'ai_enabled' => true,
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Unit 1', 'ar' => 'الوحدة 1']]);
    $section->update(['published' => true]);

    $videoItem = app(ManageLessons::class)->add($instructor, $section, [
        'type' => 'video',
        'title' => ['en' => 'Shading Video', 'ar' => 'فيديو التظليل'],
    ]);
    /** @var Lesson $videoLesson */
    $videoLesson = $videoItem->itemable;
    Storage::fake(config('media.disk'));
    Storage::disk(config('media.disk'))->put('media/shading.mp4', 'v');
    app(AttachLessonMedia::class)($instructor, $videoLesson, ['provider' => 'signed_storage', 'path' => 'media/shading.mp4']);

    $textItem = app(ManageLessons::class)->add($instructor, $section, [
        'type' => 'text',
        'title' => ['en' => 'Reading Notes', 'ar' => 'ملاحظات القراءة'],
    ]);

    $liveItem = app(ScheduleLiveSession::class)($instructor, $section, [
        'title' => ['en' => 'Live Q&A', 'ar' => 'جلسة مباشرة'],
        'timezone' => 'UTC',
        'starts_at' => now()->addDay()->format('Y-m-d H:i:s'),
        'ends_at' => now()->addDay()->addHour()->format('Y-m-d H:i:s'),
        'provider' => 'manual_zoom',
        'meeting_url' => 'https://zoom.us/j/555123',
    ]);

    app(SetCurriculumStatus::class)($instructor, $course, [$videoItem->id, $textItem->id, $liveItem->id], CurriculumItem::STATUS_PUBLISHED);
    app(GrantEntitlement::class)($student, $course, EntitlementSource::Free);

    $document = LessonCurriculumDocument::query()->create([
        'lesson_id' => $videoLesson->id,
        'version' => 1,
        'path' => 'curriculum/shading.pdf',
        'sha256' => hash('sha256', 'shading-'.uniqid()),
        'size' => 1024,
        'pages' => 1,
        'status' => LessonCurriculumDocument::STATUS_READY,
        'active' => true,
    ]);
    LessonDocumentPage::query()->create([
        'document_id' => $document->id,
        'page_no' => 1,
        'approved_text' => 'Cross-hatching uses parallel ink strokes to build tone values.',
    ]);

    return compact('student', 'instructor', 'course', 'videoItem', 'videoLesson', 'textItem', 'liveItem', 'document');
}

it('omits assistant panel on live items, text lessons, and ineligible lessons while rendering wired panel and approved key terms when ready', function (): void {
    ['student' => $student, 'instructor' => $instructor, 'course' => $course, 'videoItem' => $videoItem, 'videoLesson' => $videoLesson, 'textItem' => $textItem, 'liveItem' => $liveItem, 'document' => $document] = apanSetup();

    // Approved key terms (cap 5) + unapproved excluded
    foreach (['Cross-hatching', 'Value Scale', 'Core Shadow', 'Cast Shadow', 'Highlight', 'Sixth Extra Term'] as $idx => $term) {
        LessonKeyTerm::query()->create(['document_id' => $document->id, 'locale' => 'en', 'term' => $term, 'approved' => true]);
    }
    LessonKeyTerm::query()->create(['document_id' => $document->id, 'locale' => 'en', 'term' => 'UNAPPROVED_TERM', 'approved' => false]);

    // Ready recorded video lesson shows panel & feedback URL
    $this->actingAs($student)->get(route('learn.show', ['course' => $course->id, 'item' => $videoItem->id]))
        ->assertOk()
        ->assertSee('data-ai-panel', false)
        ->assertSee('data-ai-open', false)
        ->assertSee(route('assistant.feedback', ['lesson' => $videoLesson->id]), false);

    // YouTube/embed recorded video lesson also renders data-ai-open below 2xl (BUG-008)
    app(AttachLessonMedia::class)($instructor, $videoLesson, ['provider' => 'youtube', 'url' => 'https://youtu.be/dQw4w9WgXcQ']);
    $this->actingAs($student)->get(route('learn.show', ['course' => $course->id, 'item' => $videoItem->id]))
        ->assertOk()
        ->assertSee('data-ai-panel', false)
        ->assertSee('data-ai-open', false);

    // Status JSON includes up to 5 approved key terms only
    $this->actingAs($student)->getJson(route('assistant.show', ['lesson' => $videoLesson->id]))
        ->assertOk()
        ->assertJsonPath('available', true)
        ->assertJsonCount(5, 'key_terms')
        ->assertDontSee('UNAPPROVED_TERM')
        ->assertDontSee('Sixth Extra Term');

    // Text lesson & Live session do NOT render assistant panel or opener
    $this->actingAs($student)->get(route('learn.show', ['course' => $course->id, 'item' => $textItem->id]))
        ->assertOk()
        ->assertDontSee('data-ai-panel', false)
        ->assertDontSee('data-ai-open', false);

    $this->actingAs($student)->get(route('learn.show', ['course' => $course->id, 'item' => $liveItem->id]))
        ->assertOk()
        ->assertDontSee('data-ai-panel', false)
        ->assertDontSee('data-ai-open', false);

    // Document not READY removes panel from video lesson too
    $document->update(['status' => LessonCurriculumDocument::STATUS_AWAITING_APPROVAL]);
    $this->actingAs($student)->get(route('learn.show', ['course' => $course->id, 'item' => $videoItem->id]))
        ->assertOk()
        ->assertDontSee('data-ai-panel', false);

    // Restored doc + global feature flag off removes panel
    $document->update(['status' => LessonCurriculumDocument::STATUS_READY]);
    Feature::for(null)->deactivate(PlatformFlag::AiAssistant->value);
    $this->actingAs($student)->get(route('learn.show', ['course' => $course->id, 'item' => $videoItem->id]))
        ->assertOk()
        ->assertDontSee('data-ai-panel', false);
})->group('T-P7-03', 'A1', 'D-017', 'ED-009');
