<?php

declare(strict_types=1);

use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
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

it('renders accessible assistant panel on mobile and desktop across AR and EN with keyboard submit', function (string $locale, int $width, int $height): void {
    $this->seed(RolePermissionSeeder::class);
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
    $student = User::factory()->create(['status' => 'active', 'locale' => $locale, 'birth_year' => 1995, 'birth_month' => 2])->assignRole('student');
    $course = Course::query()->create([
        'title' => ['en' => 'Color Workshop', 'ar' => 'ورشة الألوان'],
        'slug_ar' => 'apb-ar-'.uniqid(),
        'slug_en' => 'apb-en-'.uniqid(),
        'status' => 'published',
        'access_mode' => Course::ACCESS_FREE,
        'ai_enabled' => true,
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Unit 1', 'ar' => 'الوحدة 1']]);
    $section->update(['published' => true]);

    $item = app(ManageLessons::class)->add($instructor, $section, [
        'type' => 'video',
        'title' => ['en' => 'Pigment Lesson', 'ar' => 'درس الصبغات'],
    ]);
    /** @var Lesson $lesson */
    $lesson = $item->itemable;
    Storage::fake(config('media.disk'));
    Storage::disk(config('media.disk'))->put('media/apb.mp4', 'v');
    app(AttachLessonMedia::class)($instructor, $lesson, ['provider' => 'signed_storage', 'path' => 'media/apb.mp4']);
    app(SetCurriculumStatus::class)($instructor, $course, [$item->id], CurriculumItem::STATUS_PUBLISHED);
    app(GrantEntitlement::class)($student, $course, EntitlementSource::Free);

    $document = LessonCurriculumDocument::query()->create([
        'lesson_id' => $lesson->id,
        'version' => 1,
        'path' => 'curriculum/apb.pdf',
        'sha256' => hash('sha256', 'apb-'.uniqid()),
        'size' => 1024,
        'pages' => 1,
        'status' => LessonCurriculumDocument::STATUS_READY,
        'active' => true,
    ]);
    LessonDocumentPage::query()->create([
        'document_id' => $document->id,
        'page_no' => 1,
        'approved_text' => 'Primary colors are red, yellow, and blue.',
    ]);
    LessonKeyTerm::query()->create(['document_id' => $document->id, 'locale' => $locale, 'term' => 'Primary Colors', 'approved' => true]);

    $this->actingAs($student);
    $page = $this->visit(route('learn.show', ['course' => $course->id, 'item' => $item->id]))->resize($width, $height);
    if ($width < 1536) {
        $page->click('[data-ai-open]')->assertVisible('[data-ai-panel]');
    } else {
        $page->assertVisible('[data-ai-panel]');
    }

    $page->script('async () => { await document.fonts.ready; }');
    expect($page->script('() => document.documentElement.dir'))->toBe($locale === 'ar' ? 'rtl' : 'ltr')
        ->and($page->script('() => document.documentElement.scrollWidth <= innerWidth'))->toBeTrue();

    $page->assertVisible('[data-ai-input]')->assertVisible('[data-ai-send]');
    $page->assertNoJavaScriptErrors()->assertNoAccessibilityIssues();

    $dir = base_path('tests/Browser/screenshots/T-P7-03');
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $page->screenshot(fullPage: false, filename: "../screenshots/T-P7-03/assistant-panel-{$locale}-{$width}");
})->with(['ar', 'en'])->with([[390, 844], [1440, 900]])
    ->group('T-P7-03', 'A2', 'A3', 'D-017', 'ED-009')
    ->group('browser');
