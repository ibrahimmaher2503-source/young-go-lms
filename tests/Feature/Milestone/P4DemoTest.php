<?php

declare(strict_types=1);

use App\Domain\Cohorts\Cohort;
use App\Domain\Cohorts\CohortMember;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Curriculum\Queries\EntitledCurriculumQuery;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Media\Actions\AttachLessonMedia;
use App\Domain\Media\Actions\ManageLessonResources;
use App\Domain\Media\SignedFileUrl;
use App\Models\Course;
use App\Models\LessonCurriculumDocument;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpFoundation\File\File;
use Tests\Support\PdfFixture;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    // A unique fake disk per run: Storage::fake() clears a shared testing directory that concurrent runs also use.
    config(['media.disk' => 'p4demo-'.uniqid()]);
    Storage::fake(config('media.disk'));
});

/** Acts as one user: a fresh session per actor, the way a person switching accounts gets one. */
function p4As($test, User $user)
{
    $test->flushSession();
    app('auth')->forgetGuards();

    return $test->actingAs($user);
}

/**
 * T-P4-MG demo script, executed over HTTP against the seeded roles:
 * free self-enrollment (HTTP) and a manual grant → child opens the course player → protected video, private PDFs and
 * resources open only with the entitlement → heartbeats move progress and completion → learner and parent dashboards
 * show the progress → a cohort-relative drip lesson stays locked → a non-entitled learner gets 404 / 403.
 */
it('runs the P4 demo script end to end', function (): void {
    // Accounts: instructor, admin, a verified parent with a verified child, a free self-enrolling classmate and an unentitled learner.
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $admin = User::factory()->create()->assignRole('super_admin');
    $parent = User::factory()->create(['password' => 'ParentP4demo1!'])->assignRole('parent');
    $child = User::factory()->create(['name' => 'P4 Child'])->assignRole('student');
    $classmate = User::factory()->create()->assignRole('student');
    $otherStudent = User::factory()->create()->assignRole('student');
    GuardianChildLink::query()->create(['guardian_id' => $parent->id, 'child_id' => $child->id, 'status' => 'verified', 'method' => 'created_by_parent', 'verified_at' => now()]);

    // A published free course: video lesson (protected, 120 s), a private PDF resource and curriculum PDF, a text lesson, and a drip lesson.
    $course = Course::query()->create([
        'title' => ['en' => 'P4 Demo Course', 'ar' => 'دورة تجريبية'], 'slug_ar' => 'p4-ar-'.uniqid(), 'slug_en' => 'p4-en-'.uniqid(),
        'status' => 'published', 'access_mode' => Course::ACCESS_FREE,
    ]);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Unit 1', 'ar' => 'الوحدة 1']]);
    $section->update(['published' => true]);

    Storage::disk(config('media.disk'))->put('media/p4-intro.mp4', 'v');
    $video = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Intro video', 'ar' => 'فيديو'], 'type' => 'video']);
    app(AttachLessonMedia::class)($instructor, $video->itemable, ['provider' => 'signed_storage', 'path' => 'media/p4-intro.mp4', 'duration_seconds' => 120]);
    $text = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Reading', 'ar' => 'قراءة'], 'type' => 'text']);
    $locked = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Week 3 drip', 'ar' => 'أسبوع 3'], 'type' => 'text']);
    app(SetCurriculumStatus::class)($instructor, $course, [$video->id, $text->id, $locked->id], 'published');

    $resources = app(ManageLessonResources::class);
    $pdfPath = tempnam(sys_get_temp_dir(), 'p4pdf');
    file_put_contents($pdfPath, PdfFixture::bytes(2));
    $worksheet = $resources->addFile($instructor, $video->itemable, new File($pdfPath), ['title' => ['en' => 'Worksheet'], 'downloadable' => true]);
    $document = $video->itemable->curriculumDocuments()->create([
        'version' => 1, 'path' => 'docs/p4-outline.pdf', 'sha256' => str_repeat('c', 64),
        'size' => 10, 'pages' => 1, 'status' => LessonCurriculumDocument::STATUS_READY, 'active' => true,
    ]);
    Storage::disk(config('media.disk'))->put($document->path, PdfFixture::bytes(1));

    // Before any entitlement, the signed PDF link is not even issued to the child.
    expect(app(SignedFileUrl::class)->for($child, $worksheet))->toBeNull();

    // 1. Free self-enrollment over HTTP: the classmate enrolls in the free course from the catalog CTA.
    p4As($this, $classmate)->post(route('catalog.enroll', ['course' => $course->id]))
        ->assertRedirect(route('learn.course', ['course' => $course->id]));
    expect(Entitlement::query()->where('learner_id', $classmate->id)->where('course_id', $course->id)->where('source_type', EntitlementSource::Free->value)->exists())->toBeTrue();

    // 2. Manual grant: the admin gives the child access with a reason; the grant is recorded in the audit trail.
    app(GrantEntitlement::class)($child, $course, EntitlementSource::ManualGrant, '', null, null, $admin, 'admin grant reason');
    $grant = Entitlement::query()->where('learner_id', $child->id)->where('course_id', $course->id)->firstOrFail();
    expect($grant->reason)->toBe('admin grant reason')->and($grant->isCurrent())->toBeTrue();
    expect(Activity::query()->where('log_name', 'audit')->where('event', 'entitlement.granted')->exists())->toBeTrue();

    // 3. The child opens the course: the player resumes at the first open lesson and plays the protected video.
    p4As($this, $child)->get(route('learn.course', ['course' => $course->id]))
        ->assertRedirect(route('learn.show', ['course' => $course->id, 'item' => $video->id]));
    p4As($this, $child)->get(route('learn.show', ['course' => $course->id, 'item' => $video->id]))
        ->assertOk()->assertSee('Intro video')->assertSee('<video', false);

    // 4. Private files: the entitled child gets short-lived links that download with private, no-store and nosniff headers.
    $resourceUrl = app(SignedFileUrl::class)->for($child, $worksheet);
    expect($resourceUrl)->not->toBeNull();
    $download = p4As($this, $child)->get($resourceUrl)->assertOk();
    expect($download->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and((string) $download->headers->get('Cache-Control'))->toContain('private')
        ->and((string) $download->headers->get('Cache-Control'))->toContain('no-store');
    $documentUrl = app(SignedFileUrl::class)->for($child, $document);
    p4As($this, $child)->get($documentUrl)->assertOk();

    // 5. Progress: heartbeats every 15 s of playback move the position, then the video completes at 90 %.
    $heartbeat = route('learn.progress', ['course' => $course->id, 'item' => $video->id]);
    $last = null;
    for ($position = 15; $position <= 120; $position += 15) {
        $last = p4As($this, $child)->postJson($heartbeat, ['position' => $position])->assertOk();
        $this->travel(15)->seconds();
    }
    $this->travelBack();
    $last->assertJson(['status' => 'completed', 'course_completed' => false]);
    p4As($this, $child)->postJson(route('learn.complete', ['course' => $course->id, 'item' => $text->id]))
        ->assertOk()->assertJsonPath('status', 'completed');
    expect(DB::table('lesson_progress')->where('learner_id', $child->id)->where('curriculum_item_id', $video->id)->value('status'))->toBe('completed');

    // 6. Resume and learner dashboard: the course shows up with the child's progress.
    p4As($this, $child)->get(route('learner.dashboard'))->assertOk()->assertSee('P4 Demo Course');

    // 7. Parent dashboard: the verified parent sees the child's course progress.
    $parentView = p4As($this, $parent)->get(route('parent.dashboard'))->assertOk()->assertSee('P4 Demo Course');
    expect($parentView->viewData('selected')->id)->toBe($child->id)
        ->and($parentView->viewData('summary')['lessons_done'])->toBeGreaterThanOrEqual(2);

    // The parent switches into the child's context with the password and the child's player opens for them.
    p4As($this, $parent)->postJson('/confirm-password', ['password' => 'ParentP4demo1!'])->assertNoContent();
    $this->postJson("/guardian/context/{$child->id}")->assertNoContent();
    $this->get(route('learn.show', ['course' => $course->id, 'item' => $video->id]))->assertOk()->assertSee('Intro video');
    $this->deleteJson('/guardian/context')->assertNoContent();

    // A parent cannot open a child who is not linked to them.
    p4As($this, $parent)->get(route('parent.dashboard', ['child' => $otherStudent->id]))->assertNotFound();

    // 8. Drip: the cohort-relative lesson stays locked. The child is seated in a cohort that started 10 days ago and the lesson opens 30 days after that start.
    $cohort = Cohort::query()->create([
        'course_id' => $course->id, 'name' => 'P4 cohort', 'status' => Cohort::OPEN, 'starts_on' => now()->subDays(10)->toDateString(),
    ]);
    CohortMember::query()->create(['cohort_id' => $cohort->id, 'learner_id' => $child->id, 'status' => CohortMember::SEATED]);
    $locked->refresh()->update(['unlock_rule' => 'days', 'unlock_days' => 30]);

    p4As($this, $child)->get(route('learn.show', ['course' => $course->id, 'item' => $locked->id]))
        ->assertOk()->assertSee('data-locked-reason', false)->assertDontSee('<video', false);
    $sections = app(EntitledCurriculumQuery::class)->for($child, $course);
    expect(collect($sections[0]['items'])->pluck('state', 'title')->all())
        ->toMatchArray(['Intro video' => 'open', 'Reading' => 'open', 'Week 3 drip' => 'locked']);

    // 9. Non-entitled boundary: the unentitled learner gets 404 on the course and lessons, and 403 on the child's signed file link.
    p4As($this, $otherStudent)->get(route('learn.course', ['course' => $course->id]))->assertNotFound();
    p4As($this, $otherStudent)->get(route('learn.show', ['course' => $course->id, 'item' => $video->id]))->assertNotFound();
    p4As($this, $otherStudent)->get($resourceUrl)->assertForbidden();
    p4As($this, $otherStudent)->get($documentUrl)->assertForbidden();
})->group('T-P4-MG', 'A1');
