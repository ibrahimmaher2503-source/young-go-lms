<?php

declare(strict_types=1);

use App\Domain\Assignments\Actions\ConfigureAssignment;
use App\Domain\Assignments\Actions\DecideSubmission;
use App\Domain\Assignments\Actions\ManageAssignmentAttachments;
use App\Domain\Assignments\Actions\SaveSubmissionDraft;
use App\Domain\Assignments\Actions\SubmitAssignment;
use App\Domain\Assignments\AssignmentRefusedException;
use App\Domain\Assignments\AssignmentRules;
use App\Domain\Assignments\Queries\SubmissionView;
use App\Domain\Curriculum\Actions\ManageAssessmentItems;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Media\PdfInspector;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\SubmissionDecision;
use App\Models\SubmissionFile;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\Support\PdfFixture;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

/** @return array{course: Course, instructor: User, learner: User, assignment: Assignment, item: CurriculumItem, section: CourseSection} */
function asgnSetup(): array
{
    Storage::fake(config('media.disk', 'local'));
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');
    $course = Course::query()->create([
        'title' => ['ar' => 'واجب عملي', 'en' => 'Studio Course'],
        'slug_ar' => 'asgn-ar-'.uniqid(),
        'slug_en' => 'asgn-en-'.uniqid(),
        'status' => 'published',
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['ar' => 'القسم 1', 'en' => 'Section 1']]);
    $section->update(['published' => true]);

    $item = app(ManageAssessmentItems::class)->add($instructor, $section, 'assignment', ['title' => ['ar' => 'لوحة الطبيعة', 'en' => 'Nature Study']]);
    /** @var Assignment $assignment */
    $assignment = $item->itemable;
    app(ConfigureAssignment::class)($instructor, $assignment, [
        'instructions' => ['ar' => 'ارسم مشهداً طبيعياً', 'en' => 'Paint a nature scene.'],
        'allowed_types' => ['text', 'image', 'document'],
        'max_size_kb' => 2048,
        'due_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
        'timezone' => 'Africa/Cairo',
        'late_policy' => Assignment::LATE_PENALTY,
        'late_penalty_percent' => 15,
        'max_revisions' => 1,
    ]);
    app(SetCurriculumStatus::class)($instructor, $course, [$item->id], CurriculumItem::STATUS_PUBLISHED);
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);

    return compact('course', 'instructor', 'learner', 'assignment', 'item', 'section');
}

it('validates assignment configuration, timezone due conversion, and private brief attachments', function (): void {
    ['course' => $course, 'instructor' => $instructor, 'learner' => $learner, 'assignment' => $assignment, 'item' => $item] = asgnSetup();
    $configure = app(ConfigureAssignment::class);
    $attachments = app(ManageAssignmentAttachments::class);

    // Late penalty policy requires percent and due_at; non-enrolled or foreign instructor refused
    expect(fn () => $configure($instructor, $assignment, ['allowed_types' => ['text'], 'late_policy' => Assignment::LATE_PENALTY]))
        ->toThrow(ValidationException::class);
    expect(fn () => $configure($instructor, $assignment, ['allowed_types' => ['text'], 'late_policy' => Assignment::LATE_ALLOWED]))
        ->toThrow(ValidationException::class);
    expect(fn () => $configure($learner, $assignment, ['allowed_types' => ['text'], 'late_policy' => Assignment::LATE_NOT_ALLOWED]))
        ->toThrow(AuthorizationException::class);

    expect($assignment->fresh()->due_at?->timezoneName)->toBe('UTC');
    expect(AssignmentRules::limitKilobytes($assignment->fresh()))->toBe(2048);

    // Brief attachment stored privately & served with nosniff to entitled learner; denied to stranger
    $refFile = UploadedFile::fake()->image('reference-guide.png', 120, 120);
    $attachment = $attachments->add($instructor, $assignment, $refFile, ['en' => 'Color Wheel Reference']);
    expect(Storage::disk(config('media.disk', 'local'))->exists($attachment->path))->toBeTrue();

    $stranger = User::factory()->create(['status' => 'active'])->assignRole('student');
    $this->actingAs($learner)->get(route('assignment.attachment', ['attachment' => $attachment->id]))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->actingAs($stranger)->get(route('assignment.attachment', ['attachment' => $attachment->id]))
        ->assertNotFound();

    // Remove attachment cleans storage and audits
    $oldPath = $attachment->path;
    $attachments->remove($instructor, $attachment);
    expect(Storage::disk(config('media.disk', 'local'))->exists($oldPath))->toBeFalse()
        ->and(Activity::query()->where('event', 'assignment.attachment_removed')->count())->toBe(1);
})->group('T-P6-03', 'A1', 'PRD-03', 'PRD-13');

it('supports learner drafts, HTTP file uploads, spoofed extension rejection, late rules, and immutable revisions', function (): void {
    ['course' => $course, 'instructor' => $instructor, 'learner' => $learner, 'assignment' => $assignment, 'item' => $item] = asgnSetup();

    // Spoofed .png containing plain PHP/executable payload is rejected by content sniffing
    $spoofed = UploadedFile::fake()->createWithContent('fake.png', "<?php echo 'hack'; ?>");
    expect(fn () => app(SaveSubmissionDraft::class)($learner, $item->fresh(), 'Draft', [$spoofed]))
        ->toThrow(AssignmentRefusedException::class);

    // Disallowed kind (executable / audio when only text, image, document allowed)
    $audio = UploadedFile::fake()->createWithContent('clip.mp3', 'ID3fake');
    expect(fn () => app(SaveSubmissionDraft::class)($learner, $item->fresh(), null, [$audio]))
        ->toThrow(AssignmentRefusedException::class);

    // HTTP save & submit with real fake PNG + text
    $png = UploadedFile::fake()->image('my-sketch.png', 150, 150);
    $this->actingAs($learner)->post(route('assignment.save', ['course' => $course->id, 'item' => $item->id]), [
        'text' => 'Initial draft reflection',
        'files' => [$png],
    ])->assertRedirect(route('learn.show', ['course' => $course->id, 'item' => $item->id]));

    // Empty work cannot submit (text cleared and file removed)
    $draftSub = AssignmentSubmission::query()->sole();
    $fileId = SubmissionFile::query()->sole()->id;
    app(SaveSubmissionDraft::class)($learner, $item->fresh(), '', [], [$fileId]);
    expect(fn () => app(SubmitAssignment::class)($learner, $item->fresh()))
        ->toThrow(AssignmentRefusedException::class);

    // HTTP submit saves and submits atomically + freezes revision 1
    $png2 = UploadedFile::fake()->image('final-study.png', 160, 160);
    $this->actingAs($learner)->post(route('assignment.submit', ['course' => $course->id, 'item' => $item->id]), [
        'text' => 'Submitted revision 1',
        'files' => [$png2],
    ])->assertRedirect(route('learn.show', ['course' => $course->id, 'item' => $item->id]));

    expect($draftSub->fresh()->status)->toBe(AssignmentSubmission::SUBMITTED);

    // Submitted work is read-only until reviewer requests revision
    expect(fn () => app(SaveSubmissionDraft::class)($learner, $item->fresh(), 'Tamper submitted'))
        ->toThrow(AssignmentRefusedException::class);

    app(DecideSubmission::class)($instructor, $draftSub->fresh(), SubmissionDecision::REVISION_REQUESTED, null, 'Add highlights.');
    $assignment->forceFill(['due_at' => now()->subMinute()])->save(); // now past due -> late penalty applies on revision 2
    app(SaveSubmissionDraft::class)($learner, $item->fresh(), 'Revision 2 with highlights');
    $resubmitted = app(SubmitAssignment::class)($learner, $item->fresh());

    $rev1 = $resubmitted->revisions()->where('number', 1)->firstOrFail();
    $rev2 = $resubmitted->revisions()->where('number', 2)->firstOrFail();
    expect($rev1->text_body)->toBe('Submitted revision 1')
        ->and($rev2->text_body)->toBe('Revision 2 with highlights')
        ->and($rev2->is_late)->toBeTrue()
        ->and($rev2->late_penalty_percent)->toBe(15);

    // LATE_NOT_ALLOWED refuses submission past due
    app(ConfigureAssignment::class)($instructor, $assignment->fresh(), [
        'allowed_types' => ['text'],
        'due_at' => now()->addHour()->format('Y-m-d H:i:s'),
        'timezone' => 'UTC',
        'late_policy' => Assignment::LATE_NOT_ALLOWED,
    ]);
    $lateLearner = User::factory()->create(['status' => 'active'])->assignRole('student');
    app(GrantEntitlement::class)($lateLearner, $course, EntitlementSource::Free);
    app(SaveSubmissionDraft::class)($lateLearner, $item->fresh(), 'Too late work');
    $assignment->fresh()->forceFill(['due_at' => now()->subMinutes(5)])->save();
    expect(fn () => app(SubmitAssignment::class)($lateLearner, $item->fresh()))
        ->toThrow(AssignmentRefusedException::class);
})->group('T-P6-03', 'A1', 'A2', 'PRD-03', 'PRD-13');

it('enforces private download ACL across owner, verified parent, scoped staff, and strangers without exposing storage paths', function (): void {
    ['course' => $course, 'instructor' => $instructor, 'learner' => $learner, 'assignment' => $assignment, 'item' => $item] = asgnSetup();

    $file = UploadedFile::fake()->image('private-drawing.png', 100, 100);
    app(SaveSubmissionDraft::class)($learner, $item->fresh(), 'Private notes', [$file]);
    $subFile = SubmissionFile::query()->sole();

    // SubmissionView never leaks raw storage path
    $view = SubmissionView::for($learner, $assignment->fresh());
    expect(json_encode($view, JSON_THROW_ON_ERROR))->not->toContain($subFile->path);

    // Owner, course instructor, and verified linked parent allowed
    $this->actingAs($learner)->get(route('assignment.file', ['file' => $subFile->id]))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    $this->actingAs($instructor)->get(route('assignment.file', ['file' => $subFile->id]))->assertOk();

    $parent = User::factory()->create(['status' => 'active'])->assignRole('parent');
    GuardianChildLink::query()->create([
        'guardian_id' => $parent->id, 'child_id' => $learner->id,
        'status' => GuardianChildLink::STATUS_VERIFIED, 'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT, 'verified_at' => now(),
    ]);
    $this->actingAs($parent)->get(route('assignment.file', ['file' => $subFile->id]))->assertOk();

    // Other student, foreign instructor, and view-only instructor denied with 404
    $otherStudent = User::factory()->create(['status' => 'active'])->assignRole('student');
    $otherInst = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $viewInst = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $course->instructors()->attach($viewInst->id, ['role' => 'instructor', 'scope' => 'view']);

    foreach ([$otherStudent, $otherInst, $viewInst] as $denied) {
        $this->actingAs($denied)->get(route('assignment.file', ['file' => $subFile->id]))->assertNotFound();
    }
})->group('T-P6-03', 'A3', 'PRD-03', 'PRD-13');

it('accepts valid PDF submission when qpdf binary runtime is healthy on the host', function (): void {
    $pdfBytes = PdfFixture::bytes(1);
    $pdfPath = tempnam(sys_get_temp_dir(), 'yg_pdf_').'.pdf';
    file_put_contents($pdfPath, $pdfBytes);
    $inspection = app(PdfInspector::class)->inspect($pdfPath);
    @unlink($pdfPath);

    expect($inspection['valid'] ?? false)->toBeTrue();

    ['learner' => $learner, 'item' => $item] = asgnSetup();
    $pdfUpload = UploadedFile::fake()->createWithContent('homework.pdf', $pdfBytes);
    app(SaveSubmissionDraft::class)($learner, $item->fresh(), null, [$pdfUpload]);
    expect(SubmissionFile::query()->sole()->mime)->toBe('application/pdf');
})->group('T-P6-03', 'A2', 'A3', 'PRD-03', 'PRD-13');
