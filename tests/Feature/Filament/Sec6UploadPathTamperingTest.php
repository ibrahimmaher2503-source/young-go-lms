<?php

declare(strict_types=1);

use App\Domain\Assignments\Actions\ConfigureAssignment;
use App\Domain\Curriculum\Actions\ManageAssessmentItems;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Filament\Resources\Courses\Pages\ManageCurriculum;
use App\Filament\Resources\Library\Pages\ListLibraryItems;
use App\Models\Assignment;
use App\Models\AssignmentAttachment;
use App\Models\Course;
use App\Models\LessonCurriculumDocument;
use App\Models\LessonResource;
use App\Models\LibraryItem;
use App\Models\MediaAsset;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\PdfFixture;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
});

/**
 * Points the media disk at a fresh, uniquely named disk root and seeds the victim's private files on it.
 * A unique name keeps concurrent runs in this checkout from sharing a root.
 */
function sec6VictimDisk(): string
{
    $name = 'sec6-'.uniqid();
    Storage::fake($name);
    config(['media.disk' => $name]);

    $disk = Storage::disk($name);
    $disk->put('media/victim.pdf', PdfFixture::bytes(2));
    $disk->put('resources/secret.txt', 'SECRET-RESOURCE-CONTENT');
    $disk->put('certificates/2.pdf', PdfFixture::bytes(1));

    return $name;
}

/** An instructor who manages one course with a section, a lesson and an assignment. */
function sec6Course(): array
{
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $course = Course::query()->create([
        'title' => ['en' => 'Path Tamper Course', 'ar' => 'دورة'], 'slug_ar' => 'sec6-ar-'.uniqid(), 'slug_en' => 'sec6-en-'.uniqid(), 'status' => 'published',
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Section']]);
    $section->update(['published' => true]);

    $lessonItem = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Tools'], 'type' => 'video']);
    $assignmentItem = app(ManageAssessmentItems::class)->add($instructor, $section, 'assignment', ['title' => ['en' => 'Still Life']]);
    /** @var Assignment $assignment */
    $assignment = $assignmentItem->itemable;
    app(ConfigureAssignment::class)($instructor, $assignment, [
        'allowed_types' => ['text'], 'late_policy' => Assignment::LATE_NOT_ALLOWED,
    ]);

    return [$course, $instructor, $lessonItem->itemable, $assignment];
}

/** Counts of every record a victim path could have produced. */
function sec6Records(): array
{
    return [
        'curriculum_documents' => LessonCurriculumDocument::query()->count(),
        'lesson_resources' => LessonResource::query()->count(),
        'media_assets' => MediaAsset::query()->count(),
        'assignment_attachments' => AssignmentAttachment::query()->count(),
        'library_items' => LibraryItem::query()->count(),
    ];
}

function sec6ZeroRecords(): array
{
    return array_fill_keys(['curriculum_documents', 'lesson_resources', 'media_assets', 'assignment_attachments', 'library_items'], 0);
}

it('refuses a victim path typed into the lesson curriculum PDF field and leaves the private file alone', function (): void {
    $disk = sec6VictimDisk();
    [$course, $instructor, $lesson] = sec6Course();
    $this->actingAs($instructor);

    Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()])
        ->callAction(TestAction::make('editLesson')->arguments(['lesson' => $lesson->getKey()]), [
            'title' => ['en' => 'Tools'], 'type' => 'video', 'curriculum_pdf' => ['media/victim.pdf'],
        ]);

    expect(sec6Records())->toBe(sec6ZeroRecords());
    Storage::disk($disk)->assertExists('media/victim.pdf');
    Storage::disk($disk)->assertExists('resources/secret.txt');
})->group('T-P3-08', 'T-P11-01');

it('refuses a victim path typed into the lesson resource field and leaves the private file alone', function (): void {
    $disk = sec6VictimDisk();
    [$course, $instructor, $lesson] = sec6Course();
    $this->actingAs($instructor);

    Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()])
        ->callAction(TestAction::make('editLesson')->arguments(['lesson' => $lesson->getKey()]), [
            'title' => ['en' => 'Tools'], 'type' => 'video', 'resource_file' => ['resources/secret.txt'], 'resource_title' => ['en' => 'Stolen'],
        ]);

    expect(sec6Records())->toBe(sec6ZeroRecords());
    Storage::disk($disk)->assertExists('media/victim.pdf');
    Storage::disk($disk)->assertExists('resources/secret.txt');
})->group('T-P3-09', 'T-P11-01');

it('refuses a victim path typed into the lesson media field and leaves the private file alone', function (): void {
    $disk = sec6VictimDisk();
    [$course, $instructor, $lesson] = sec6Course();
    $this->actingAs($instructor);

    Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()])
        ->callAction(TestAction::make('editLesson')->arguments(['lesson' => $lesson->getKey()]), [
            'title' => ['en' => 'Tools'], 'type' => 'video', 'media_provider' => 'signed_storage', 'media_file' => ['media/victim.pdf'],
        ]);

    expect(sec6Records())->toBe(sec6ZeroRecords());
    Storage::disk($disk)->assertExists('media/victim.pdf');
    Storage::disk($disk)->assertExists('resources/secret.txt');
})->group('T-P3-08', 'T-P11-01');

it('refuses a victim path typed into the assignment attachment field and leaves the private file alone', function (): void {
    $disk = sec6VictimDisk();
    [$course, $instructor, , $assignment] = sec6Course();
    $this->actingAs($instructor);

    Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()])
        ->callAction(TestAction::make('configureAssignment')->arguments(['assignment' => $assignment->getKey()]), [
            'allowed_types' => ['text'], 'late_policy' => Assignment::LATE_NOT_ALLOWED, 'status' => 'draft',
            'attachment_file' => ['media/victim.pdf'], 'attachment_title' => ['en' => 'Stolen'],
        ]);

    expect(sec6Records())->toBe(sec6ZeroRecords());
    Storage::disk($disk)->assertExists('media/victim.pdf');
    Storage::disk($disk)->assertExists('resources/secret.txt');
})->group('T-P6-04', 'T-P11-01');

it('refuses a victim path typed into the library upload field and leaves the private file alone', function (): void {
    $disk = sec6VictimDisk();
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $this->actingAs($instructor);

    Livewire::test(ListLibraryItems::class)->callAction('add', [
        'file' => ['media/victim.pdf'], 'title' => ['en' => 'Stolen'], 'tags' => [], 'scope' => 'mine',
    ]);

    expect(sec6Records())->toBe(sec6ZeroRecords());
    Storage::disk($disk)->assertExists('media/victim.pdf');
    Storage::disk($disk)->assertExists('resources/secret.txt');
})->group('T-P3-13', 'T-P11-01');

it('still accepts genuine uploads on the curriculum manager, assignment settings and library screens', function (): void {
    $disk = sec6VictimDisk();
    [$course, $instructor, $lesson, $assignment] = sec6Course();
    $this->actingAs($instructor);
    $pdf = PdfFixture::bytes(2, 'genuine');

    Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()])
        ->callAction(TestAction::make('editLesson')->arguments(['lesson' => $lesson->getKey()]), [
            'title' => ['en' => 'Tools'], 'type' => 'video',
            'curriculum_pdf' => UploadedFile::fake()->createWithContent('plan.pdf', $pdf),
        ])->assertHasNoActionErrors();
    expect(LessonCurriculumDocument::query()->count())->toBe(1);

    Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()])
        ->callAction(TestAction::make('configureAssignment')->arguments(['assignment' => $assignment->getKey()]), [
            'allowed_types' => ['text'], 'late_policy' => Assignment::LATE_NOT_ALLOWED, 'status' => 'draft',
            'attachment_file' => UploadedFile::fake()->createWithContent('brief.pdf', $pdf), 'attachment_title' => ['en' => 'Brief'],
        ])->assertHasNoActionErrors();
    expect(AssignmentAttachment::query()->count())->toBe(1);

    Livewire::test(ListLibraryItems::class)->callAction('add', [
        'file' => UploadedFile::fake()->createWithContent('sheet.pdf', PdfFixture::bytes(1, 'library')),
        'title' => ['en' => 'Uploaded'], 'tags' => [], 'scope' => 'mine',
    ])->assertNotified(__('library.added'));
    expect(LibraryItem::query()->count())->toBe(1);

    Storage::disk($disk)->assertExists('media/victim.pdf');
    Storage::disk($disk)->assertExists('resources/secret.txt');
})->group('T-P3-08', 'T-P3-13', 'T-P6-04');
