<?php

declare(strict_types=1);

use App\Domain\Assignments\Actions\ConfigureAssignment;
use App\Domain\Assignments\Actions\ManageAssignmentAttachments;
use App\Domain\Curriculum\Actions\ManageAssessmentItems;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Filament\Resources\Courses\Pages\ManageCurriculum;
use App\Models\Assignment;
use App\Models\AssignmentAttachment;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

afterEach(function (): void {
    // Each test gets its own disk root; remove only the roots this file created.
    foreach ($GLOBALS['t603_roots'] ?? [] as $root) {
        if (is_dir($root)) {
            File::deleteDirectory($root);
        }
    }
    $GLOBALS['t603_roots'] = [];
});

/** A private disk with its own root, so concurrent test runs in this checkout never share files. */
function t603IsolatedDisk(): string
{
    $name = 't603-'.uniqid();
    $root = storage_path('framework/testing/disks/'.$name);
    config(["filesystems.disks.{$name}" => ['driver' => 'local', 'root' => $root, 'throw' => false]]);
    config(['media.disk' => $name]);
    $GLOBALS['t603_roots'][] = $root;

    return $name;
}

/** @return array{course: Course, instructor: User, assignment: Assignment, item: CurriculumItem} */
function t603Course(): array
{
    t603IsolatedDisk();
    $instructor = User::factory()->create(['status' => 'active', 'timezone' => 'Africa/Cairo'])->assignRole('instructor_academy');
    $course = Course::query()->create([
        'title' => ['ar' => 'واجب الرسم', 'en' => 'Assignment Settings Course'],
        'slug_ar' => 't603-ar-'.uniqid(),
        'slug_en' => 't603-en-'.uniqid(),
        'status' => 'published',
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['ar' => 'القسم', 'en' => 'Section']]);
    $section->update(['published' => true]);

    $item = app(ManageAssessmentItems::class)->add($instructor, $section, 'assignment', ['title' => ['ar' => 'لوحة', 'en' => 'Still Life']]);
    /** @var Assignment $assignment */
    $assignment = $item->itemable;
    app(ConfigureAssignment::class)($instructor, $assignment, [
        'allowed_types' => ['text'],
        'late_policy' => Assignment::LATE_NOT_ALLOWED,
    ]);

    return compact('course', 'instructor', 'assignment', 'item');
}

it('saves bilingual instructions, a Cairo due date stored in UTC, the MB limit as KB, revisions and status from the settings modal', function (): void {
    ['course' => $course, 'instructor' => $instructor, 'assignment' => $assignment, 'item' => $item] = t603Course();
    $this->actingAs($instructor);

    Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()])
        ->callAction(TestAction::make('configureAssignment')->arguments(['assignment' => $assignment->id]), [
            'instructions' => ['ar' => 'ارسم طبيعة صامتة', 'en' => 'Paint a still life.'],
            'due_at' => '2026-12-01 10:00:00',
            'allowed_types' => ['text', 'image'],
            'max_size_mb' => 5,
            'late_policy' => Assignment::LATE_ALLOWED,
            'max_revisions' => 2,
            'status' => CurriculumItem::STATUS_PUBLISHED,
        ])
        ->assertHasNoActionErrors();

    $fresh = $assignment->fresh();
    expect($fresh->getTranslation('instructions', 'ar'))->toBe('ارسم طبيعة صامتة')
        ->and($fresh->getTranslation('instructions', 'en'))->toBe('Paint a still life.')
        // Africa/Cairo is UTC+2 in December, so 10:00 local is 08:00 UTC.
        ->and($fresh->due_at->format('Y-m-d H:i:s'))->toBe('2026-12-01 08:00:00')
        ->and($fresh->max_size_kb)->toBe(5 * 1024)
        ->and($fresh->allowed_types)->toEqualCanonicalizing(['text', 'image'])
        ->and($fresh->late_policy)->toBe(Assignment::LATE_ALLOWED)
        ->and($fresh->late_penalty_percent)->toBeNull()
        ->and($fresh->max_revisions)->toBe(2)
        ->and($item->fresh()->status)->toBe(CurriculumItem::STATUS_PUBLISHED);
})->group('T-P6-03', 'A1', 'A3', 'REQ-AS-007', 'REQ-AS-008');

it('prefills the settings modal with the stored due date in the instructor zone and the limit in whole MB', function (): void {
    ['course' => $course, 'instructor' => $instructor, 'assignment' => $assignment] = t603Course();
    app(ConfigureAssignment::class)($instructor, $assignment->fresh(), [
        'allowed_types' => ['text', 'document'],
        'max_size_kb' => 3072,
        'due_at' => '2026-12-01 08:00:00',
        'timezone' => 'UTC',
        'late_policy' => Assignment::LATE_PENALTY,
        'late_penalty_percent' => 20,
        'max_revisions' => 1,
    ]);
    $this->actingAs($instructor);

    // The picker has seconds disabled, so its state is Y-m-d H:i.
    Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()])
        ->mountAction(TestAction::make('configureAssignment')->arguments(['assignment' => $assignment->id]))
        ->assertSchemaStateSet([
            'due_at' => '2026-12-01 10:00',
            'allowed_types' => ['text', 'document'],
            'max_size_mb' => 3,
            'late_policy' => Assignment::LATE_PENALTY,
            'late_penalty_percent' => 20,
            'max_revisions' => 1,
            'status' => CurriculumItem::STATUS_DRAFT,
        ]);
})->group('T-P6-03', 'A1', 'A3', 'REQ-AS-007');

it('requires a penalty percentage for the penalty policy and refuses values outside 1 to 100', function (): void {
    ['course' => $course, 'instructor' => $instructor, 'assignment' => $assignment] = t603Course();
    $this->actingAs($instructor);
    $before = $assignment->fresh()->only(['late_policy', 'late_penalty_percent']);

    $base = [
        'allowed_types' => ['text'],
        'due_at' => '2026-12-01 10:00:00',
        'late_policy' => Assignment::LATE_PENALTY,
        'status' => CurriculumItem::STATUS_DRAFT,
    ];
    // A fresh page per attempt: a rejected modal stays open and cannot take a second call.
    $attempt = fn (array $data) => Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()])
        ->callAction(TestAction::make('configureAssignment')->arguments(['assignment' => $assignment->id]), $data);

    // Form rules reject a missing or out-of-range penalty (rejected, not clamped).
    $attempt($base + ['late_penalty_percent' => null])->assertHasActionErrors(['late_penalty_percent' => ['required']]);
    $attempt($base + ['late_penalty_percent' => 0])->assertHasActionErrors(['late_penalty_percent']);
    $attempt($base + ['late_penalty_percent' => 101])->assertHasActionErrors(['late_penalty_percent']);

    expect($assignment->fresh()->only(['late_policy', 'late_penalty_percent']))->toBe($before);
})->group('T-P6-03', 'A3', 'REQ-AS-008');

it('keeps the penalty percentage only while the penalty policy is selected', function (): void {
    ['course' => $course, 'instructor' => $instructor, 'assignment' => $assignment] = t603Course();
    $this->actingAs($instructor);

    Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()])
        ->callAction(TestAction::make('configureAssignment')->arguments(['assignment' => $assignment->id]), [
            'allowed_types' => ['text'],
            'due_at' => '2026-12-01 10:00:00',
            'late_policy' => Assignment::LATE_PENALTY,
            'late_penalty_percent' => 15,
            'status' => CurriculumItem::STATUS_DRAFT,
        ])->assertHasNoActionErrors();
    expect($assignment->fresh()->late_penalty_percent)->toBe(15);

    Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()])
        ->callAction(TestAction::make('configureAssignment')->arguments(['assignment' => $assignment->id]), [
            'allowed_types' => ['text'],
            'late_policy' => Assignment::LATE_NOT_ALLOWED,
            'late_penalty_percent' => 15,
            'status' => CurriculumItem::STATUS_DRAFT,
        ])->assertHasNoActionErrors();

    expect($assignment->fresh()->late_policy)->toBe(Assignment::LATE_NOT_ALLOWED)
        ->and($assignment->fresh()->late_penalty_percent)->toBeNull();
})->group('T-P6-03', 'A3', 'REQ-AS-008');

it('refuses a late policy that accepts late work without a due date and saves nothing', function (): void {
    ['course' => $course, 'instructor' => $instructor, 'assignment' => $assignment] = t603Course();
    $this->actingAs($instructor);
    $before = $assignment->fresh()->only(['late_policy', 'due_at', 'max_size_kb']);

    Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()])
        ->callAction(TestAction::make('configureAssignment')->arguments(['assignment' => $assignment->id]), [
            'allowed_types' => ['text'],
            'due_at' => null,
            'late_policy' => Assignment::LATE_ALLOWED,
            'status' => CurriculumItem::STATUS_DRAFT,
        ])
        ->assertNotNotified(__('curriculum.saved'));

    expect($assignment->fresh()->only(['late_policy', 'due_at', 'max_size_kb']))->toBe($before);
})->group('T-P6-03', 'A3', 'REQ-AS-008');

it('refuses a file limit above the platform ceiling in the form', function (): void {
    ['course' => $course, 'instructor' => $instructor, 'assignment' => $assignment] = t603Course();
    $this->actingAs($instructor);

    Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()])
        ->callAction(TestAction::make('configureAssignment')->arguments(['assignment' => $assignment->id]), [
            'allowed_types' => ['text', 'image'],
            'max_size_mb' => intdiv((int) config('assignments.ceiling_kilobytes'), 1024) + 1,
            'late_policy' => Assignment::LATE_NOT_ALLOWED,
            'status' => CurriculumItem::STATUS_DRAFT,
        ])
        ->assertHasActionErrors(['max_size_mb']);

    expect($assignment->fresh()->max_size_kb)->toBeNull();
})->group('T-P6-03', 'A1', 'REQ-AS-007');

it('uploads a brief attachment privately with its title, audits it, and deletes the ticked attachments with their files', function (): void {
    ['course' => $course, 'instructor' => $instructor, 'assignment' => $assignment] = t603Course();
    $disk = Storage::disk(config('media.disk'));
    $this->actingAs($instructor);

    Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()])
        ->callAction(TestAction::make('configureAssignment')->arguments(['assignment' => $assignment->id]), [
            'allowed_types' => ['text'],
            'late_policy' => Assignment::LATE_NOT_ALLOWED,
            'status' => CurriculumItem::STATUS_DRAFT,
            'attachment_file' => UploadedFile::fake()->image('color-wheel.png', 120, 120),
            'attachment_title' => ['en' => 'Color wheel', 'ar' => ''],
        ])->assertHasNoActionErrors();

    $attachment = AssignmentAttachment::query()->where('assignment_id', $assignment->id)->sole();
    expect($attachment->getTranslation('title', 'en'))->toBe('Color wheel')
        ->and(str_starts_with((string) $attachment->mime, 'image/'))->toBeTrue()
        ->and($disk->exists($attachment->path))->toBeTrue()
        ->and(Activity::query()->where('event', 'assignment.attachment_added')->count())->toBe(1);

    Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()])
        ->callAction(TestAction::make('configureAssignment')->arguments(['assignment' => $assignment->id]), [
            'allowed_types' => ['text'],
            'late_policy' => Assignment::LATE_NOT_ALLOWED,
            'status' => CurriculumItem::STATUS_DRAFT,
            'remove_attachments' => [$attachment->id],
        ])->assertHasNoActionErrors();

    expect(AssignmentAttachment::query()->whereKey($attachment->id)->exists())->toBeFalse()
        ->and($disk->exists($attachment->path))->toBeFalse()
        ->and(Activity::query()->where('event', 'assignment.attachment_removed')->count())->toBe(1);
})->group('T-P6-03', 'A1', 'REQ-AS-007');

it('keeps attachments that are not ticked when the modal is saved for other fields', function (): void {
    ['course' => $course, 'instructor' => $instructor, 'assignment' => $assignment] = t603Course();
    $kept = app(ManageAssignmentAttachments::class)->add($instructor, $assignment, UploadedFile::fake()->image('kept.jpg', 60, 60), ['en' => 'Kept reference']);
    $this->actingAs($instructor);

    Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()])
        ->callAction(TestAction::make('configureAssignment')->arguments(['assignment' => $assignment->id]), [
            'allowed_types' => ['text'],
            'late_policy' => Assignment::LATE_NOT_ALLOWED,
            'max_revisions' => 1,
            'status' => CurriculumItem::STATUS_DRAFT,
            'remove_attachments' => [],
        ])->assertHasNoActionErrors();

    expect(AssignmentAttachment::query()->whereKey($kept->id)->exists())->toBeTrue()
        ->and($assignment->fresh()->max_revisions)->toBe(1);
})->group('T-P6-03', 'A1', 'REQ-AS-007');

it('refuses the curriculum page with a 403 for a view-only instructor and for a learner', function (): void {
    ['course' => $course, 'assignment' => $assignment] = t603Course();

    $viewOnly = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $course->instructors()->attach($viewOnly->id, ['role' => 'instructor', 'scope' => 'view']);
    $learner = User::factory()->create(['status' => 'active'])->assignRole('student');
    $url = "/instructor/courses/{$course->id}/curriculum";

    $this->actingAs($viewOnly)->get($url)->assertForbidden();
    $this->actingAs($learner)->get($url)->assertForbidden();
    expect($assignment->fresh()->late_policy)->toBe(Assignment::LATE_NOT_ALLOWED);
})->group('T-P6-03', 'A1', 'REQ-AS-007');

it('refuses a save when the actor lost the assessment permission even though the course page opens', function (): void {
    ['course' => $course, 'instructor' => $instructor, 'assignment' => $assignment] = t603Course();
    Role::findByName('instructor_academy')->revokePermissionTo('assessment.manage');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    // A fresh user instance so no permissions loaded before the revoke are reused.
    $this->actingAs(User::query()->findOrFail($instructor->id));

    // The domain action refuses the actor outright.
    expect(fn () => app(ConfigureAssignment::class)($instructor->fresh(), $assignment, [
        'allowed_types' => ['text'], 'late_policy' => Assignment::LATE_NOT_ALLOWED, 'max_revisions' => 3,
    ]))->toThrow(AuthorizationException::class);

    // Through the modal the refusal leaves the row untouched; the Filament action pipeline does not surface the exception to the test.
    $audits = Activity::query()->where('event', 'assignment.configured')->count();
    Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()])
        ->callAction(TestAction::make('configureAssignment')->arguments(['assignment' => $assignment->id]), [
            'allowed_types' => ['text'],
            'late_policy' => Assignment::LATE_NOT_ALLOWED,
            'max_revisions' => 3,
            'status' => CurriculumItem::STATUS_DRAFT,
        ]);

    expect($assignment->fresh()->max_revisions)->toBe(0)
        ->and(Activity::query()->where('event', 'assignment.configured')->count())->toBe($audits);
})->group('T-P6-03', 'A1', 'REQ-AS-007');

it('cannot configure an assignment that belongs to another course from this course page', function (): void {
    ['course' => $course, 'instructor' => $instructor] = t603Course();
    ['assignment' => $foreign] = t603Course();
    $this->actingAs($instructor);
    $audits = Activity::query()->where('event', 'assignment.configured')->where('subject_id', $foreign->id)->count();

    // Filament's test harness cannot mount the modal for a foreign id and raises an ErrorException;
    // the contract checked here is that nothing about the foreign assignment changes.
    try {
        Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()])
            ->callAction(TestAction::make('configureAssignment')->arguments(['assignment' => $foreign->id]), [
                'allowed_types' => ['text'],
                'late_policy' => Assignment::LATE_NOT_ALLOWED,
                'max_revisions' => 4,
                'status' => CurriculumItem::STATUS_DRAFT,
            ]);
    } catch (Throwable) {
        // Refused before any write.
    }

    expect($foreign->fresh()->max_revisions)->toBe(0)
        ->and(Activity::query()->where('event', 'assignment.configured')->where('subject_id', $foreign->id)->count())->toBe($audits);
})->group('T-P6-03', 'A1', 'REQ-AS-007');
