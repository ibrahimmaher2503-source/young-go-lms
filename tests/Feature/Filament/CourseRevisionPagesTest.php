<?php

// T-P3-07 | PRD 29.5 YG-GAP-CHG-001..003 | A1 A2 A3

declare(strict_types=1);

use App\Domain\Catalog\CourseStatus;
use App\Domain\Catalog\Revisions\Actions\OpenCourseRevision;
use App\Domain\Catalog\Revisions\Actions\ReviewCourseRevision;
use App\Domain\Catalog\Revisions\Actions\SaveCourseRevision;
use App\Domain\Catalog\Revisions\Actions\SubmitCourseRevision;
use App\Domain\Catalog\Revisions\CourseRevisionStatus;
use App\Filament\Resources\CourseRevisions\Pages\ListCourseRevisions;
use App\Filament\Resources\Courses\CourseResource;
use App\Filament\Resources\Courses\Pages\EditCourse;
use App\Filament\Resources\Courses\Pages\ManageCourseRevision;
use App\Filament\Resources\Courses\Pages\ManageCurriculum;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseRevision;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    config(['markets.EG.active' => true]);
});

function crpUser(string $role): User
{
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole($role);

    return $user;
}

/** A published course owned by the instructor (or by the academy when none is given), with one section and one published lesson. */
function crpPublished(?User $instructor = null, array $fields = []): Course
{
    $category = Category::query()->create(['name' => ['en' => 'Arts'], 'slug_ar' => 'ar-'.uniqid(), 'slug_en' => 'en-'.uniqid(), 'sort' => 1, 'status' => 'active']);
    $course = Course::query()->create([...[
        'title' => ['en' => 'Old title'], 'slug_ar' => 'ar-c-'.uniqid(), 'slug_en' => 'old-course-'.uniqid(),
        'status' => CourseStatus::Published, 'category_id' => $category->id, 'level' => 'beginner', 'content_language' => 'en',
        'format' => 'recorded', 'access_mode' => Course::ACCESS_FREE,
        'owner_type' => $instructor === null ? Course::OWNER_ACADEMY : Course::OWNER_INDEPENDENT,
        'owner_instructor_id' => $instructor?->getKey(),
    ], ...$fields]);
    if ($instructor !== null) {
        $course->instructors()->attach($instructor, ['role' => 'owner', 'scope' => Course::INSTRUCTOR_SCOPE_MANAGE]);
    }
    $section = CourseSection::query()->create(['course_id' => $course->getKey(), 'published' => true, 'sort' => 1, 'title' => ['en' => 'Section A']]);
    $lesson = Lesson::query()->create(['title' => ['en' => 'Old lesson'], 'type' => 'text', 'is_preview' => false]);
    $lesson->item()->create(['course_id' => $course->getKey(), 'section_id' => $section->getKey(), 'sort' => 1, 'status' => 'published']);

    return $course;
}

/** A pending revision that renames the course. */
function crpSubmitted(User $author, Course $course, string $title = 'Proposed title'): CourseRevision
{
    $revision = app(OpenCourseRevision::class)($author, $course);
    $input = ['course' => $revision->base['course'], 'sections' => $revision->base['sections']];
    $input['course']['title'] = ['en' => $title];
    app(SaveCourseRevision::class)($author, $revision, $input);

    return app(SubmitCourseRevision::class)($author, $revision->fresh());
}

// ---------- ManageCourseRevision: the author's workspace ----------

it('lets the independent instructor open, edit, submit and discard a revision from the revision page', function (): void {
    $instructor = crpUser('instructor_independent');
    $course = crpPublished($instructor);
    Filament::setCurrentPanel('instructor');

    $page = Livewire::actingAs($instructor)->test(ManageCourseRevision::class, ['record' => $course->getKey()])
        ->assertOk()
        ->assertSee(__('course_revisions.none_open'))
        ->assertSee(__('course_revisions.history_empty'));

    $page->callAction('openRevision')->assertHasNoActionErrors();
    $revision = CourseRevision::query()->where('course_id', $course->getKey())->where('status', CourseRevisionStatus::Draft)->firstOrFail();
    expect($revision->open_course_id)->toBe($course->getKey());

    $data = ['course' => $revision->payload['course'], 'sections' => $revision->payload['sections']];
    $data['course']['title'] = ['en' => 'Edited in the slide-over'];
    $page->callAction('editRevision', $data)->assertHasNoActionErrors();
    expect($revision->fresh()->payload['course']['title']['en'])->toBe('Edited in the slide-over')
        ->and($course->fresh()->getTranslation('title', 'en'))->toBe('Old title');

    $page->callAction('submitRevision')->assertHasNoActionErrors();
    expect($revision->fresh()->status)->toBe(CourseRevisionStatus::Pending);
    $page->assertSee(__('course_revisions.state.pending'))->assertSee(__('course_revisions.compare'));

    $page->callAction('discardRevision')->assertHasNoActionErrors();
    expect($revision->fresh()->status)->toBe(CourseRevisionStatus::Discarded)
        ->and($course->fresh()->getTranslation('title', 'en'))->toBe('Old title');
})->group('T-P3-07', 'A1', 'YG-GAP-CHG-001');

it('shows the compare summary and the recent history rows of the open revision', function (): void {
    $instructor = crpUser('instructor_independent');
    $course = crpPublished($instructor);
    $submitted = crpSubmitted($instructor, $course, 'Compared title');
    Filament::setCurrentPanel('instructor');

    Livewire::actingAs($instructor)->test(ManageCourseRevision::class, ['record' => $course->getKey()])
        ->assertOk()
        ->assertSeeHtml('data-revision-open="'.$submitted->number.'"')
        ->assertSeeHtml('data-revision-status="pending"')
        ->assertSeeHtml('data-history-row="'.$submitted->number.'"')
        ->assertSee('Compared title')
        ->assertDontSee(__('course_revisions.open'))
        ->assertDontSee(__('course_revisions.submit'));
})->group('T-P3-07', 'A1', 'YG-GAP-CHG-001');

it('refuses the revision page to an unassigned instructor and to one with view-only access', function (): void {
    $instructor = crpUser('instructor_independent');
    $course = crpPublished($instructor);

    $this->actingAs($instructor)->get("/instructor/courses/{$course->getKey()}/revision")->assertOk();

    $stranger = crpUser('instructor_independent');
    $status = $this->actingAs($stranger)->get("/instructor/courses/{$course->getKey()}/revision")->getStatusCode();
    expect(in_array($status, [403, 404], true))->toBeTrue();

    $viewer = crpUser('instructor_independent');
    $course->instructors()->attach($viewer, ['role' => 'instructor', 'scope' => 'view']);
    $status = $this->actingAs($viewer)->get("/instructor/courses/{$course->getKey()}/revision")->getStatusCode();
    expect(in_array($status, [403, 404], true))->toBeTrue();
})->group('T-P3-07', 'A3', 'YG-GAP-CHG-001');

// ---------- ListCourseRevisions: the reviewer queue ----------

it('lets an admin compare, reject with a reason and approve a pending revision from the review queue', function (): void {
    $instructor = crpUser('instructor_independent');
    $admin = crpUser('admin');
    $course = crpPublished($instructor);
    $revision = crpSubmitted($instructor, $course);
    Filament::setCurrentPanel('admin');

    $queue = Livewire::actingAs($admin)->test(ListCourseRevisions::class)
        ->assertCanSeeTableRecords([$revision])
        ->assertTableActionVisible('compare', $revision)
        ->assertTableActionVisible('approve', $revision)
        ->assertTableActionVisible('reject', $revision);

    $queue->callTableAction('compare', $revision)->assertHasNoTableActionErrors()->unmountTableAction();

    $queue->callTableAction('reject', $revision, ['reason' => 'Short'])->assertHasTableActionErrors(['reason'])->unmountTableAction();
    expect($revision->fresh()->status)->toBe(CourseRevisionStatus::Pending);

    $queue->callTableAction('reject', $revision, ['reason' => 'Please keep the original tone of the title.'])->assertHasNoTableActionErrors();
    expect($revision->fresh()->status)->toBe(CourseRevisionStatus::Rejected)
        ->and($course->fresh()->getTranslation('title', 'en'))->toBe('Old title');

    $second = crpSubmitted($instructor, crpPublished($instructor), 'Approved from the queue');
    Livewire::actingAs($admin)->test(ListCourseRevisions::class)
        ->callTableAction('approve', $second)->assertHasNoTableActionErrors();
    expect($second->fresh()->status)->toBe(CourseRevisionStatus::Approved);
})->group('T-P3-07', 'A2', 'A3', 'YG-GAP-CHG-001', 'YG-GAP-CHG-003');

it('hides approve and reject for the author and for revisions that are no longer pending', function (): void {
    $admin = crpUser('admin');
    $course = crpPublished($admin);
    $authored = crpSubmitted($admin, $course, 'Admin authored proposal');
    $other = crpUser('instructor_independent');
    $otherCourse = crpPublished($other);
    $approvedByAdmin = crpSubmitted($other, $otherCourse, 'Already approved');
    app(ReviewCourseRevision::class)($admin, $approvedByAdmin->fresh(), 'approved');
    Filament::setCurrentPanel('admin');

    Livewire::actingAs($admin)->test(ListCourseRevisions::class, ['tableFilters' => ['status' => ['value' => null]]])
        ->assertTableActionHidden('approve', $authored)
        ->assertTableActionHidden('reject', $authored)
        ->assertTableActionVisible('compare', $authored)
        ->assertTableActionHidden('approve', $approvedByAdmin)
        ->assertTableActionHidden('reject', $approvedByAdmin);
})->group('T-P3-07', 'A2', 'YG-GAP-CHG-001');

// ---------- Course redirect and curriculum banner ----------

it('redirects an independent instructor from the wizard of a published course to the revision page', function (): void {
    $instructor = crpUser('instructor_independent');
    $course = crpPublished($instructor);
    Filament::setCurrentPanel('instructor');

    Livewire::actingAs($instructor)->test(EditCourse::class, ['record' => $course->getKey()])
        ->assertRedirect(CourseResource::getUrl('revision', ['record' => $course]));
})->group('T-P3-07', 'A1', 'YG-GAP-CHG-001');

it('shows the revision banner on the curriculum manager when a revision is required or open, and not for academy courses', function (): void {
    $instructor = crpUser('instructor_independent');
    $course = crpPublished($instructor);
    Filament::setCurrentPanel('instructor');

    Livewire::actingAs($instructor)->test(ManageCurriculum::class, ['record' => $course->getKey()])
        ->assertOk()
        ->assertSeeHtml('data-revision-banner="required"');

    app(OpenCourseRevision::class)($instructor, $course);

    Livewire::actingAs($instructor)->test(ManageCurriculum::class, ['record' => $course->getKey()])
        ->assertSeeHtml('data-revision-banner="draft"');

    $academyInstructor = crpUser('instructor_academy');
    $academy = crpPublished(null);
    $academy->instructors()->attach($academyInstructor, ['role' => 'instructor', 'scope' => Course::INSTRUCTOR_SCOPE_MANAGE]);
    Filament::setCurrentPanel('instructor');

    Livewire::actingAs($academyInstructor)->test(ManageCurriculum::class, ['record' => $academy->getKey()])
        ->assertOk()
        ->assertDontSeeHtml('data-revision-banner');
})->group('T-P3-07', 'A1', 'YG-GAP-CHG-001');
