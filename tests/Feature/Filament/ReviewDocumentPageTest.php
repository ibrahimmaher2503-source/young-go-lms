<?php

declare(strict_types=1);

use App\Domain\AI\Actions\IngestCurriculumDocument;
use App\Domain\AI\Ingestion\Contracts\PdfTextExtractor;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Filament\Resources\Courses\Pages\ReviewCurriculumDocument;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonCurriculumDocument;
use App\Models\LessonKeyTerm;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    Storage::fake('local');
});

/** @return array{0: Course, 1: User, 2: Lesson, 3: LessonCurriculumDocument} a document that finished extraction */
function rvDocument(): array
{
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['en' => 'Drawing', 'ar' => 'الرسم'], 'slug_ar' => 'rv-'.uniqid(), 'slug_en' => 'rv-'.uniqid()]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'S']]);
    $item = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'L']]);
    /** @var Lesson $lesson */
    $lesson = $item->itemable;
    Storage::disk('local')->put('documents/rv.pdf', '%PDF-fake');
    $document = LessonCurriculumDocument::query()->create([
        'lesson_id' => $lesson->id, 'version' => 1, 'path' => 'documents/rv.pdf', 'sha256' => hash('sha256', uniqid()), 'size' => 9, 'pages' => 2,
        'status' => LessonCurriculumDocument::STATUS_UPLOADED, 'active' => false,
    ]);
    App::instance(PdfTextExtractor::class, new class implements PdfTextExtractor
    {
        public function extract(string $absolutePath): array
        {
            return [
                'Perspective drawing uses vanishing points. Perspective lines meet at the vanishing point, and shading adds depth.',
                'Contrast and shading make perspective drawing feel real. Light direction changes shading.',
            ];
        }
    });
    app(IngestCurriculumDocument::class)($document);

    return [$course, $instructor, $lesson, $document->refresh()];
}

it('shows the PDF beside the extracted text for an approver', function (): void {
    [$course, , , $document] = rvDocument();
    $admin = User::factory()->create()->assignRole('admin');
    $this->actingAs($admin);

    Livewire::test(ReviewCurriculumDocument::class, ['record' => $course->getKey(), 'document' => $document->getKey()])
        ->assertSee('Perspective drawing uses vanishing points')
        ->assertSee(__('media.approve'))
        ->assertSee(__('media.readiness.awaiting_approval'))
        ->assertSee('iframe', false);

    $this->get("/admin/courses/{$course->id}/curriculum/documents/{$document->id}")->assertOk();
})->group('T-P3-09', 'A5');

it('lets an approver edit a page, approve the text and choose key terms', function (): void {
    [$course, , $lesson, $document] = rvDocument();
    $this->actingAs(User::factory()->create()->assignRole('admin'));

    $test = Livewire::test(ReviewCurriculumDocument::class, ['record' => $course->getKey(), 'document' => $document->getKey()])
        ->set('texts.1', 'Edited page one text about perspective.')
        ->call('savePage', 1)
        ->assertNotified(__('media.page_saved'))
        ->call('approve')
        ->assertNotified(__('media.approved'))
        ->assertSee(__('media.key_terms'));

    expect($lesson->activeCurriculumDocument()->id)->toBe($document->id)
        ->and($document->documentPages()->where('page_no', 1)->first()->approved_text)->toBe('Edited page one text about perspective.');

    $ids = LessonKeyTerm::query()->where('document_id', $document->id)->limit(2)->pluck('id')->all();
    $test->set('terms', $ids)->call('saveTerms')->assertNotified(__('media.terms_saved'));
    expect(LessonKeyTerm::query()->where('approved', true)->count())->toBe(2);

    // Once approved the text is read-only: another edit is refused with a readable message.
    $test->set('texts.1', 'late edit')->call('savePage', 1)->assertNotified(__('media.pdf_not_ready_for_approval'));
})->group('T-P3-09', 'A3');

it('refuses people without approval rights and documents of another course', function (): void {
    [$course, $instructor, , $document] = rvDocument();

    $this->actingAs($instructor)->get("/instructor/courses/{$course->id}/curriculum/documents/{$document->id}")->assertForbidden();

    $other = Course::query()->create(['title' => ['en' => 'Other'], 'slug_ar' => 'o-'.uniqid(), 'slug_en' => 'o-'.uniqid()]);
    $this->actingAs(User::factory()->create()->assignRole('admin'))->get("/admin/courses/{$other->id}/curriculum/documents/{$document->id}")->assertNotFound();
})->group('T-P3-09', 'A3');

it('shows the readiness indicator and a review link in the curriculum manager', function (): void {
    [$course, , , $document] = rvDocument();
    $admin = User::factory()->create()->assignRole('admin');

    $this->actingAs($admin)->get("/admin/courses/{$course->id}/curriculum")->assertOk()
        ->assertSee(__('media.readiness.awaiting_approval'))->assertSee('🟠')->assertSee(__('media.review_text'))
        ->assertSee("/curriculum/documents/{$document->id}", false);
})->group('T-P3-09', 'A3');
