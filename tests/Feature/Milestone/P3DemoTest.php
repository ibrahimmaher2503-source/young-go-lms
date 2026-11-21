<?php

declare(strict_types=1);

use App\Domain\AI\Actions\IngestCurriculumDocument;
use App\Domain\AI\Contracts\AiProviderInterface;
use App\Domain\AI\Ingestion\Contracts\PdfTextExtractor;
use App\Domain\AI\Providers\NullAiProvider;
use App\Domain\Catalog\Actions\DeleteCourse;
use App\Domain\Catalog\Actions\DuplicateCourse;
use App\Domain\Catalog\Actions\ReviewCourse;
use App\Domain\Catalog\Actions\SaveCourseWizard;
use App\Domain\Catalog\Actions\SubmitCourseForReview;
use App\Domain\Catalog\CourseStatus;
use App\Domain\Catalog\Revisions\Actions\OpenCourseRevision;
use App\Domain\Catalog\Revisions\Actions\ReviewCourseRevision;
use App\Domain\Catalog\Revisions\Actions\SaveCourseRevision;
use App\Domain\Catalog\Revisions\Actions\SubmitCourseRevision;
use App\Domain\Curriculum\Actions\ManageAssessmentItems;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Media\Actions\ApproveCurriculumDocument;
use App\Domain\Media\Actions\AttachLessonMedia;
use App\Domain\Media\Actions\UploadCurriculumDocument;
use App\Models\Category;
use App\Models\Course;
use App\Models\LessonCurriculumDocument;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\File\File as UploadFile;
use Tests\Support\PdfFixture;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    config(['markets.EG.active' => true]);

    // A unique local disk per run, rooted under the testing disks folder and removed afterwards.
    $name = 'p3demo-'.uniqid();
    $this->p3Root = storage_path('framework/testing/disks/'.$name);
    config(["filesystems.disks.{$name}" => ['driver' => 'local', 'root' => $this->p3Root, 'throw' => false]]);
    config(['media.disk' => $name]);
});

afterEach(function (): void {
    if (isset($this->p3Root)) {
        File::deleteDirectory($this->p3Root);
    }
});

function p3User(string $role): User
{
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole($role);

    return $user;
}

/** A tiny extractor that returns two readable pages, so the PDF can be approved without a real OCR engine. */
function p3Extractor(): void
{
    App::instance(PdfTextExtractor::class, new class implements PdfTextExtractor
    {
        public function extract(string $absolutePath): array
        {
            return [
                'Colour mixing: primary colours combine into secondary colours with a clear step by step example.',
                'Shapes and shading: the teacher explains light and shadow with a short exercise for learners.',
            ];
        }
    });
}

/**
 * T-P3-MG demo script, run through the domain actions and HTTP:
 * an independent instructor creates a bilingual course in the wizard -> pricing -> sections and lessons with a T1 video
 * and a PDF (ingestion approved) -> quiz shell -> submit -> admin approves and publishes -> public page -> revision opened,
 * submitted and approved -> duplicate -> delete blocked once history exists.
 */
it('runs the P3 demo script end to end', function (): void {
    $admin = p3User('admin');
    $instructor = p3User('instructor_independent');
    $learner = p3User('student');
    App::instance(AiProviderInterface::class, new NullAiProvider);

    // 1. Wizard step 1 (basic information) saved and completed by the instructor.
    $category = Category::query()->create(['name' => ['en' => 'Arts', 'ar' => 'فنون'], 'slug_ar' => 'فنون-'.uniqid(), 'slug_en' => 'arts-'.uniqid(), 'sort' => 1, 'status' => 'active']);
    $course = app(SaveCourseWizard::class)($instructor, [
        'title' => ['ar' => 'أساسيات الرسم', 'en' => 'Painting demo'],
        'short_description' => ['ar' => 'مقدمة قصيرة', 'en' => 'A short intro'],
        'category_id' => $category->id, 'level' => 'beginner', 'content_language' => 'both', 'format' => 'recorded',
    ], null, 1, true);
    expect($course->status)->toBe(CourseStatus::Draft)->and($course->owner_type)->toBe(Course::OWNER_INDEPENDENT);

    // 2. Wizard step 3 (pricing) set by an admin: a paid course in Egypt, one year of access.
    $course = app(SaveCourseWizard::class)($admin, [
        'access_mode' => Course::ACCESS_PAID, 'market_prices' => ['EG' => '450.00'], 'access_duration_days' => 365,
    ], $course, SaveCourseWizard::PRICING_STEP, true);
    expect($course->access_mode)->toBe(Course::ACCESS_PAID);

    // 3. Sections and lessons: a video with T1 media, a reading lesson and a quiz shell.
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Unit 1', 'ar' => 'الوحدة 1']]);
    $section->update(['published' => true]);

    Storage::disk(config('media.disk'))->put('media/p3-intro.mp4', 'video');
    $video = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Intro video', 'ar' => 'فيديو'], 'type' => 'video', 'duration_seconds' => 120]);
    app(AttachLessonMedia::class)($instructor, $video->itemable, ['provider' => 'signed_storage', 'path' => 'media/p3-intro.mp4', 'duration_seconds' => 120]);
    $text = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Reading', 'ar' => 'قراءة'], 'type' => 'text']);
    $quiz = app(ManageAssessmentItems::class)->add($instructor, $section, 'quiz', [
        'title' => ['en' => 'Check-in quiz'], 'association' => 'lesson', 'associated_lesson_item_id' => $text->id,
    ]);

    // 4. The PDF is uploaded to the video lesson, extracted, and its ingestion approved by an admin.
    $pdfPath = tempnam(sys_get_temp_dir(), 'p3pdf');
    file_put_contents($pdfPath, PdfFixture::bytes(2));
    $document = app(UploadCurriculumDocument::class)($instructor, $video->itemable, new UploadFile($pdfPath));
    unlink($pdfPath);
    expect($document->status)->toBe(LessonCurriculumDocument::STATUS_UPLOADED)->and($document->active)->toBeFalse();

    p3Extractor();
    $extracted = app(IngestCurriculumDocument::class)($document);
    expect($extracted->status)->toBe(LessonCurriculumDocument::STATUS_AWAITING_APPROVAL);

    $approved = app(ApproveCurriculumDocument::class)($admin, $extracted->refresh());
    expect($approved->status)->toBe(LessonCurriculumDocument::STATUS_READY)->and($approved->active)->toBeTrue();

    // Publish the lessons and quiz shell, then submit the course for review.
    app(SetCurriculumStatus::class)($instructor, $course, [$video->id, $text->id, $quiz->id], 'published');
    app(SubmitCourseForReview::class)($instructor, $course->fresh());
    expect($course->fresh()->status)->toBe(CourseStatus::PendingReview);

    // 5. Admin approves; the course is published and the public page shows it over HTTP.
    app(ReviewCourse::class)($admin, $course->fresh(), 'approved');
    $course = $course->fresh();
    expect($course->status)->toBe(CourseStatus::Published);
    $this->get('/en/courses/'.$course->slug_en)->assertOk()->assertSee('Painting demo')->assertSee('Intro video');

    // 6. Revision of the published independent course: opened, edited, submitted, approved by an admin.
    $revision = app(OpenCourseRevision::class)($instructor, $course);
    $input = ['course' => $revision->base['course'], 'sections' => $revision->base['sections']];
    $input['course']['title']['en'] = 'Painting demo, revised';
    app(SaveCourseRevision::class)($instructor, $revision, $input);
    app(SubmitCourseRevision::class)($instructor, $revision->fresh());
    expect($course->fresh()->getTranslation('title', 'en'))->toBe('Painting demo');

    app(ReviewCourseRevision::class)($admin, $revision->fresh(), 'approved');
    expect($course->fresh()->getTranslation('title', 'en'))->toBe('Painting demo, revised');

    // 7. Duplicate: an admin copies the course into an independent draft with no learner data.
    $copy = app(DuplicateCourse::class)($admin, $course->fresh());
    expect($copy->id)->not->toBe($course->id)->and($copy->status)->toBe(CourseStatus::Draft)
        ->and($copy->getTranslation('title', 'en'))->toContain('(copy)');

    // 8. Delete is blocked once history exists: a learner's access row on the course.
    app(GrantEntitlement::class)($learner, $course->fresh(), EntitlementSource::Free);
    expect(DeleteCourse::blockers($course->fresh()))->toContain('entitlements');
    expect(fn () => app(DeleteCourse::class)($admin, $course->fresh()))->toThrow(ValidationException::class);
    expect(Course::query()->whereKey($course->id)->exists())->toBeTrue();
})->group('T-P3-MG', 'A1');
