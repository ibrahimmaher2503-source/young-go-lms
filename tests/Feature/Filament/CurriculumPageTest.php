<?php

declare(strict_types=1);

use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Queries\CurriculumOutline;
use App\Domain\Media\Actions\AttachLessonMedia;
use App\Filament\Resources\Courses\Pages\ManageCurriculum;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\MediaAsset;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
});

function cpBuilt(): array
{
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['en' => 'Drawing', 'ar' => 'الرسم'], 'slug_ar' => 'cp-'.uniqid(), 'slug_en' => 'cp-'.uniqid()]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $one = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Basics', 'ar' => 'الأساسيات']]);
    $two = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Practice']]);
    $a = app(ManageLessons::class)->add($instructor, $one, ['title' => ['en' => 'Tools', 'ar' => 'الأدوات'], 'duration_seconds' => 720]);
    $b = app(ManageLessons::class)->add($instructor, $one, ['title' => ['en' => 'Lines']]);
    $c = app(ManageLessons::class)->add($instructor, $two, ['title' => ['en' => 'Shading']]);

    return [$course, $instructor, [$one, $two], [$a, $b, $c]];
}

it('opens for admin and the managing instructor and refuses everyone else', function (): void {
    [$course, $instructor] = cpBuilt();

    $this->actingAs($instructor)->get("/instructor/courses/{$course->id}/curriculum")->assertOk()->assertSee('Basics')->assertSee('Tools');
    $this->actingAs(User::factory()->create()->assignRole('admin'))->get("/admin/courses/{$course->id}/curriculum")->assertOk();

    $viewOnly = User::factory()->create()->assignRole('instructor_academy');
    $course->instructors()->attach($viewOnly->id, ['role' => 'instructor', 'scope' => 'view']);
    $this->actingAs($viewOnly)->get("/instructor/courses/{$course->id}/curriculum")->assertForbidden();
    $this->actingAs(User::factory()->create()->assignRole('instructor_academy'))->get("/instructor/courses/{$course->id}/curriculum")->assertNotFound();
})->group('T-P3-04', 'A3', 'I02');

it('shows sections, numbered lessons, durations and status', function (): void {
    [$course, $instructor] = cpBuilt();
    $this->actingAs($instructor);

    Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()])
        ->assertSee('1. Basics')->assertSee('2. Practice')->assertSee(__('curriculum.minutes', ['count' => 12]))->assertSee(__('curriculum.status_draft'));
})->group('T-P3-04', 'A1');

it('reorders from the page payload, by drag order and by keyboard buttons', function (): void {
    [$course, $instructor, $sections, $items] = cpBuilt();
    $this->actingAs($instructor);
    $test = Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()]);

    $test->call('reorder', [
        ['section_id' => $sections[1]->id, 'items' => [$items[2]->id, $items[0]->id]],
        ['section_id' => $sections[0]->id, 'items' => [$items[1]->id]],
    ]);
    expect(CurriculumItem::query()->find($items[0]->id)->section_id)->toBe($sections[1]->id)
        ->and(collect(CurriculumOutline::forManager($course))->map(fn ($s) => $s->items->count())->all())->toBe([2, 1]);

    $test->call('moveItem', $items[0]->id, 'up');
    expect(CurriculumOutline::forManager($course)->first()->items->pluck('id')->map(fn ($id) => (int) $id)->all())->toBe([$items[0]->id, $items[2]->id]);

    $test->call('moveSection', $sections[0]->id, 'down');
    expect(CurriculumOutline::forManager($course)->pluck('id')->map(fn ($id) => (int) $id)->all())->toBe([$sections[1]->id, $sections[0]->id]);
})->group('T-P3-04', 'A1');

it('rejects a stale drag payload with a validation error and leaves the order alone', function (): void {
    [$course, $instructor, $sections, $items] = cpBuilt();
    $this->actingAs($instructor);

    Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()])
        ->call('reorder', [['section_id' => $sections[0]->id, 'items' => [$items[0]->id]]])
        ->assertHasErrors(['layout']);
    expect(CurriculumOutline::forManager($course)->first()->items)->toHaveCount(2);
})->group('T-P3-04', 'A1');

it('adds sections and lessons from the page as drafts and edits a lesson', function (): void {
    [$course, $instructor, $sections] = cpBuilt();
    $this->actingAs($instructor);
    $test = Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()]);

    $test->callAction('addSection', ['title' => ['en' => 'Advanced', 'ar' => 'متقدم']])->assertHasNoActionErrors();
    $test->callAction('addLesson', ['section_id' => $sections[0]->id, 'title' => ['en' => 'Perspective'], 'type' => 'video', 'duration_minutes' => 18, 'is_preview' => true])->assertHasNoActionErrors();

    $item = CurriculumItem::query()->where('section_id', $sections[0]->id)->orderByDesc('id')->first();
    expect($item->status)->toBe('draft')->and($item->itemable->duration_seconds)->toBe(1080)->and($item->itemable->is_preview)->toBeTrue();

    $test->callAction(TestAction::make('editLesson')->arguments(['lesson' => $item->itemable_id]), ['title' => ['en' => 'Perspective drawing'], 'type' => 'video', 'duration_minutes' => 20])->assertHasNoActionErrors();
    expect($item->itemable->refresh()->getTranslation('title', 'en'))->toBe('Perspective drawing')->and($item->itemable->duration_seconds)->toBe(1200);
})->group('T-P3-04', 'A4');

it('publishes the selection in bulk, audits it and archives with confirmation', function (): void {
    [$course, $instructor, , $items] = cpBuilt();
    $this->actingAs($instructor);
    $test = Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()]);

    $test->set('selected', [$items[0]->id, $items[1]->id])->assertSee(__('curriculum.selected', ['count' => 2]))->call('bulkStatus', 'published');
    expect(CurriculumItem::query()->where('status', 'published')->count())->toBe(2)->and($test->get('selected'))->toBe([])
        ->and(Activity::query()->where('event', 'curriculum.items_published')->count())->toBe(1);

    $test->call('setItemStatus', $items[0]->id, 'draft');
    expect(CurriculumItem::query()->find($items[0]->id)->status)->toBe('draft');

    $test->callAction(TestAction::make('archiveLesson')->arguments(['lesson' => $items[1]->itemable_id]));
    expect($items[1]->itemable->refresh()->archived_at)->not->toBeNull();
    $test->assertSee(__('curriculum.archived'));
})->group('T-P3-04', 'A2', 'A4');

it('shows the student preview from the same query and hides drafts from it', function (): void {
    [$course, $instructor, $sections, $items] = cpBuilt();
    $this->actingAs($instructor);
    $test = Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()])->callAction('studentPreview')->assertSee(__('curriculum.preview_empty'));

    $test->set('selected', [$items[0]->id])->call('bulkStatus', 'published')->call('setSectionPublished', $sections[0]->id, true);
    $test->assertSee(__('curriculum.preview_note'))->assertSee('Tools');
})->group('T-P3-04', 'A1', 'ED-024');

it('refuses page actions for a different course', function (): void {
    [$course, $instructor] = cpBuilt();
    [, , , $other] = cpBuilt();
    $this->actingAs($instructor);

    $before = CurriculumItem::query()->orderBy('id')->pluck('sort', 'id')->all();

    Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()])->call('moveItem', $other[1]->id, 'up')->assertStatus(404);

    expect(CurriculumItem::query()->orderBy('id')->pluck('sort', 'id')->all())->toBe($before);
})->group('T-P3-04', 'A3', 'I02');

it('shows each lesson’s protection tier in the manager', function (): void {
    [$course, $instructor, , $items] = cpBuilt();
    app(AttachLessonMedia::class)($instructor, $items[0]->itemable, ['provider' => 'youtube', 'url' => 'https://youtu.be/dQw4w9WgXcQ']);
    app(AttachLessonMedia::class)($instructor, $items[1]->itemable, ['provider' => 'vimeo', 'url' => 'https://vimeo.com/123456789']);
    $this->actingAs($instructor);

    Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()])
        ->assertSee(__('media.tiers.T3'))->assertSee(__('media.tiers.T2'));
})->group('T-P3-05', 'A1', 'YG-GAP-VID-001');

it('saves a lesson with a private upload from the lesson editor and shows it in the manager', function (): void {
    Storage::fake('local');
    [$course, $instructor, $sections] = cpBuilt();
    $this->actingAs($instructor);
    $mp4 = "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom".str_repeat("\0", 64);

    Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()])
        ->callAction('addLesson', [
            'section_id' => $sections[0]->id, 'title' => ['en' => 'Uploaded lesson', 'ar' => 'درس مرفوع'], 'type' => 'video', 'duration_minutes' => 7,
            'description' => ['en' => 'About the lesson'], 'media_provider' => 'signed_storage',
            'media_file' => UploadedFile::fake()->createWithContent('clip.mp4', $mp4),
        ])->assertHasNoActionErrors()->assertSee('Uploaded lesson')->assertSee(__('media.tiers.T1'));

    $lesson = Lesson::query()->latest('id')->first();
    expect($lesson->media->provider)->toBe('signed_storage')->and($lesson->media->protection_tier)->toBe('T1')
        ->and($lesson->getTranslation('description', 'en'))->toBe('About the lesson')->and($lesson->duration_seconds)->toBe(420);
    Storage::disk('local')->assertExists($lesson->media->path);
    expect(Storage::disk('local')->allFiles('media/uploads'))->toBe([]);
})->group('T-P3-06', 'A2');

it('rejects a wrong file type from the lesson editor and saves links and text sources', function (): void {
    Storage::fake('local');
    [$course, $instructor, $sections, $items] = cpBuilt();
    $this->actingAs($instructor);
    $test = Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()]);

    $test->callAction('addLesson', [
        'section_id' => $sections[0]->id, 'title' => ['en' => 'Bad file'], 'type' => 'video', 'media_provider' => 'signed_storage',
        'media_file' => UploadedFile::fake()->createWithContent('clip.mp4', "MZ\x90\x00 not a video"),
    ]);
    $test->assertNotified()->unmountAction();
    expect(MediaAsset::query()->count())->toBe(0)->and(Lesson::query()->where('title->en', 'Bad file')->exists())->toBeFalse();

    $test->callAction(TestAction::make('editLesson')->arguments(['lesson' => $items[0]->itemable_id]), [
        'title' => ['en' => 'Tools'], 'type' => 'video', 'media_provider' => 'youtube', 'media_url' => 'https://youtu.be/dQw4w9WgXcQ',
    ])->assertHasNoActionErrors();
    expect($items[0]->itemable->refresh()->media->protection_tier)->toBe('T3');

    $test->callAction(TestAction::make('editLesson')->arguments(['lesson' => $items[1]->itemable_id]), [
        'title' => ['en' => 'Lines'], 'type' => 'text', 'media_provider' => 'rich_text', 'media_text' => 'Draw straight lines.',
    ])->assertHasNoActionErrors();
    expect($items[1]->itemable->refresh()->media->metadata['text'])->toBe('Draw straight lines.');

    $test->callAction(TestAction::make('editLesson')->arguments(['lesson' => $items[0]->itemable_id]), ['title' => ['en' => 'Tools renamed'], 'type' => 'video']);
    expect($items[0]->itemable->refresh()->media->provider)->toBe('youtube');
})->group('T-P3-06', 'A1', 'A2');

it('saves a curriculum PDF and lesson material from the lesson editor and shows them on the row', function (): void {
    Storage::fake('local');
    [$course, $instructor, , $items] = cpBuilt();
    $this->actingAs($instructor);
    $pdf = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n2 0 obj<</Type/Pages/Count 2>>endobj\n3 0 obj<</Type/Page/Parent 2 0 R>>endobj\n4 0 obj<</Type/Page/Parent 2 0 R>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n";

    Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()])
        ->callAction(TestAction::make('editLesson')->arguments(['lesson' => $items[0]->itemable_id]), [
            'title' => ['en' => 'Tools'], 'type' => 'video',
            'curriculum_pdf' => UploadedFile::fake()->createWithContent('plan.pdf', $pdf),
            'resource_file' => UploadedFile::fake()->createWithContent('sheet.pdf', $pdf), 'resource_title' => ['en' => 'Practice sheet'], 'resource_downloadable' => true,
        ])->assertHasNoActionErrors()->assertSee(__('curriculum.pdf_version', ['version' => 1]))->assertSee(__('curriculum.resources_count', ['count' => 1]));

    $lesson = $items[0]->itemable->refresh();
    expect($lesson->curriculumDocuments)->toHaveCount(1)->and($lesson->resources)->toHaveCount(1)->and($lesson->resources->first()->getTranslation('title', 'en'))->toBe('Practice sheet')
        ->and(Storage::disk('local')->allFiles('documents/uploads'))->toBe([])
        ->and(Storage::disk('local')->allFiles('resources/uploads'))->toBe([]);
})->group('T-P3-08', 'A2');

it('refuses an encrypted curriculum PDF from the editor and keeps nothing', function (): void {
    Storage::fake('local');
    [$course, $instructor, , $items] = cpBuilt();
    $this->actingAs($instructor);
    $bad = "%PDF-1.4\n1 0 obj<</Type/Page>>endobj\ntrailer<</Root 1 0 R/Encrypt 9 0 R>>\n%%EOF\n";

    Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()])
        ->callAction(TestAction::make('editLesson')->arguments(['lesson' => $items[0]->itemable_id]), [
            'title' => ['en' => 'Tools renamed'], 'type' => 'video', 'curriculum_pdf' => UploadedFile::fake()->createWithContent('plan.pdf', $bad),
        ])->assertNotified();

    expect($items[0]->itemable->refresh()->curriculumDocuments)->toHaveCount(0)->and($items[0]->itemable->getTranslation('title', 'en'))->toBe('Tools')
        ->and(Storage::disk('local')->allFiles())->toBe([]);
})->group('T-P3-08', 'A3');
