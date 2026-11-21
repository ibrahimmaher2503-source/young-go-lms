<?php

// T-P3-07 | PRD 29.5 YG-GAP-CHG-001..003 | A1 A2 A3

declare(strict_types=1);

use App\Domain\Catalog\Actions\SaveCourseWizard;
use App\Domain\Catalog\CourseStatus;
use App\Domain\Catalog\Revisions\Actions\ApproveCourseRevision;
use App\Domain\Catalog\Revisions\Actions\DiscardCourseRevision;
use App\Domain\Catalog\Revisions\Actions\OpenCourseRevision;
use App\Domain\Catalog\Revisions\Actions\ReviewCourseRevision;
use App\Domain\Catalog\Revisions\Actions\SaveCourseRevision;
use App\Domain\Catalog\Revisions\Actions\SubmitCourseRevision;
use App\Domain\Catalog\Revisions\CourseRevisionStatus;
use App\Domain\Catalog\Revisions\Events\CourseRevisionReviewed;
use App\Domain\Catalog\Revisions\RevisionAccess;
use App\Domain\Curriculum\Actions\ManageAssessmentItems;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\ReorderCurriculum;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Notifications\Listeners\NotifyOfCourseRevision;
use App\Domain\Notifications\Notifications\TemplatedNotice;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseRevision;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\Lesson;
use App\Models\MediaAsset;
use App\Models\User;
use Closure;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    config(['markets.EG.active' => true]);
});

function t307User(string $role): User
{
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole($role);

    return $user;
}

/** A published course with one section and one published lesson, owned by an independent instructor (or the academy). */
function t307Published(?User $instructor = null, array $fields = []): Course
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
    t307Lesson($course, $section, 'Old lesson', 'published', 1);

    return $course;
}

function t307Lesson(Course $course, CourseSection $section, string $title, string $status, int $sort): CurriculumItem
{
    $lesson = Lesson::query()->create(['title' => ['en' => $title], 'type' => 'text', 'is_preview' => false]);

    return $lesson->item()->create(['course_id' => $course->getKey(), 'section_id' => $section->getKey(), 'sort' => $sort, 'status' => $status]);
}

/** The base snapshot of a revision as editable input: what the author's form would send before any change. */
function t307Input(CourseRevision $revision): array
{
    return ['course' => $revision->base['course'], 'sections' => $revision->base['sections']];
}

function t307Open(User $author, Course $course, ?CourseRevision $continueFrom = null): CourseRevision
{
    return app(OpenCourseRevision::class)($author, $course, $continueFrom);
}

function t307Section(Course $course): CourseSection
{
    return CourseSection::query()->where('course_id', $course->getKey())->orderBy('sort')->orderBy('id')->firstOrFail();
}

/** A one-change revision that renames the course, ready to submit. */
function t307Submitted(User $author, Course $course, string $title = 'New title'): CourseRevision
{
    $revision = t307Open($author, $course);
    $input = t307Input($revision);
    $input['course']['title'] = ['en' => $title];
    app(SaveCourseRevision::class)($author, $revision, $input);

    return app(SubmitCourseRevision::class)($author, $revision->fresh());
}

function t307Audit(string $event, Course $course): int
{
    return Activity::query()->where('event', $event)->where('subject_id', $course->getKey())->count();
}

// ---------- A1 students see old content until approval ----------

it('keeps the old title and curriculum on the public page until an admin approves the revision', function (): void {
    $instructor = t307User('instructor_independent');
    $admin = t307User('admin');
    $course = t307Published($instructor);
    $slug = $course->slug_en;

    $revision = t307Open($instructor, $course);
    $input = t307Input($revision);
    $input['course']['title'] = ['en' => 'Brand new title'];
    $input['sections'][0]['items'][] = ['kind' => 'lesson', 'title' => ['en' => 'Brand new lesson'], 'description' => ['en' => 'Fresh'], 'lesson_type' => 'text', 'status' => 'published'];
    app(SaveCourseRevision::class)($instructor, $revision, $input);
    app(SubmitCourseRevision::class)($instructor, $revision->fresh());

    expect($course->fresh()->getTranslation('title', 'en'))->toBe('Old title');
    $this->get('/en/courses/'.$slug)->assertOk()->assertSee('Old title')->assertSee('Old lesson')
        ->assertDontSee('Brand new title')->assertDontSee('Brand new lesson');

    app(ReviewCourseRevision::class)($admin, $revision->fresh(), 'approved');

    expect($course->fresh()->getTranslation('title', 'en'))->toBe('Brand new title');
    $this->get('/en/courses/'.$slug)->assertOk()->assertSee('Brand new title')->assertSee('Brand new lesson');
})->group('T-P3-07', 'A1', 'YG-GAP-CHG-001');

// ---------- A2 approval applies every change in one transaction ----------

it('applies a mixed change set in one approval and keeps existing row ids, archiving rather than deleting', function (): void {
    $instructor = t307User('instructor_independent');
    $admin = t307User('admin');
    $course = t307Published($instructor);
    $sectionA = t307Section($course);
    $keep = $sectionA->items()->firstOrFail();
    $removeLesson = t307Lesson($course, $sectionA, 'Doomed lesson', 'published', 2);
    $removedLessonId = $removeLesson->itemable_id;
    $draftItem = t307Lesson($course, CourseSection::query()->create(['course_id' => $course->getKey(), 'published' => true, 'sort' => 2, 'title' => ['en' => 'Section B']]), 'Draft lesson', 'draft', 1);

    $revision = t307Open($instructor, $course);
    $input = t307Input($revision);
    $input['course']['title'] = ['en' => 'Changed course'];
    $input['sections'][0]['title'] = ['en' => 'Renamed A'];
    $input['sections'][0]['items'] = array_values(array_filter($input['sections'][0]['items'], static fn (array $item): bool => (int) $item['id'] !== (int) $removeLesson->id));
    $input['sections'][0]['items'] = array_reverse($input['sections'][0]['items']); // reorder
    $input['sections'][] = ['title' => ['en' => 'Fresh section'], 'published' => true, 'items' => [
        ['kind' => 'lesson', 'title' => ['en' => 'Fresh lesson'], 'description' => ['en' => 'New'], 'lesson_type' => 'text', 'status' => 'published'],
    ]];
    // Move the draft item into section A and publish it.
    $input['sections'][0]['items'][] = ['id' => $draftItem->id, 'kind' => 'lesson', 'title' => ['en' => 'Draft lesson'], 'description' => ['en' => ''], 'lesson_type' => 'text', 'status' => 'published'];
    $input['sections'][1]['items'] = array_values(array_filter($input['sections'][1]['items'] ?? [], static fn (array $item): bool => (int) $item['id'] !== (int) $draftItem->id));
    app(SaveCourseRevision::class)($instructor, $revision, $input);
    app(SubmitCourseRevision::class)($instructor, $revision->fresh());

    app(ReviewCourseRevision::class)($admin, $revision->fresh(), 'approved');

    $live = $course->fresh();
    expect($live->getTranslation('title', 'en'))->toBe('Changed course')
        ->and($sectionA->fresh()->getTranslation('title', 'en'))->toBe('Renamed A')
        ->and($keep->fresh())->not->toBeNull()
        ->and($keep->fresh()->section_id)->toBe($sectionA->getKey())
        ->and(CourseSection::query()->where('course_id', $live->getKey())->where('title->en', 'Fresh section')->exists())->toBeTrue()
        ->and(Lesson::query()->where('title->en', 'Fresh lesson')->exists())->toBeTrue()
        ->and(Lesson::query()->find($removedLessonId))->not->toBeNull()
        ->and(Lesson::query()->find($removedLessonId)->archived_at)->not->toBeNull()
        ->and($draftItem->fresh()->section_id)->toBe($sectionA->getKey())
        ->and($draftItem->fresh()->status)->toBe('published')
        ->and($revision->fresh()->status)->toBe(CourseRevisionStatus::Approved)
        ->and(t307Audit('course.revision_applied', $course))->toBe(1);
})->group('T-P3-07', 'A2');

it('rolls the whole approval back when a publish is blocked by the lesson publish rule', function (): void {
    $instructor = t307User('instructor_independent');
    $admin = t307User('admin');
    // A paid course: a lesson on open (T3) media may not be published (LessonPublishRule).
    $course = t307Published($instructor, ['access_mode' => Course::ACCESS_PAID]);
    $section = t307Section($course);
    $blockedItem = t307Lesson($course, $section, 'Open-media lesson', 'draft', 2);
    $blockedItem->itemable->media()->create(['provider' => 'rich_text', 'kind' => 'text', 'protection_tier' => MediaAsset::TIER_OPEN, 'status' => 'ready', 'metadata' => ['text' => 'x']]);

    $revision = t307Open($instructor, $course);
    $input = t307Input($revision);
    $input['course']['title'] = ['en' => 'Should not land'];
    $input['sections'][0]['title'] = ['en' => 'Should not rename'];
    foreach ($input['sections'][0]['items'] as $index => $item) {
        if ((int) $item['id'] === (int) $blockedItem->id) {
            $input['sections'][0]['items'][$index]['status'] = 'published';
        }
    }
    app(SaveCourseRevision::class)($instructor, $revision, $input);
    app(SubmitCourseRevision::class)($instructor, $revision->fresh());

    expect(fn () => app(ReviewCourseRevision::class)($admin, $revision->fresh(), 'approved'))
        ->toThrow(ValidationException::class);

    expect($course->fresh()->getTranslation('title', 'en'))->toBe('Old title')
        ->and($section->fresh()->getTranslation('title', 'en'))->toBe('Section A')
        ->and($blockedItem->fresh()->status)->toBe('draft')
        ->and($revision->fresh()->status)->toBe(CourseRevisionStatus::Pending)
        ->and(t307Audit('course.revision_applied', $course))->toBe(0);
})->group('T-P3-07', 'A2');

// ---------- A3 rejection reason ----------

it('refuses a rejection shorter than 10 characters and leaves the revision pending', function (): void {
    $instructor = t307User('instructor_independent');
    $admin = t307User('admin');
    $course = t307Published($instructor);
    $revision = t307Submitted($instructor, $course);

    expect(fn () => app(ReviewCourseRevision::class)($admin, $revision->fresh(), 'rejected', 'too short'))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('reason'));

    expect($revision->fresh()->status)->toBe(CourseRevisionStatus::Pending)
        ->and(t307Audit('course.revision.review.rejected', $course))->toBe(0);
})->group('T-P3-07', 'A3');

it('rejects with a reason, keeps the live course, and lets the instructor reopen from the rejected payload', function (): void {
    $instructor = t307User('instructor_independent');
    $admin = t307User('admin');
    $course = t307Published($instructor);
    $revision = t307Submitted($instructor, $course, 'Proposed title');
    $reason = 'Please keep the original tone of the title.';

    app(ReviewCourseRevision::class)($admin, $revision->fresh(), 'rejected', $reason);

    $rejected = $revision->fresh();
    expect($rejected->status)->toBe(CourseRevisionStatus::Rejected)
        ->and($rejected->reason)->toBe($reason)
        ->and($course->fresh()->getTranslation('title', 'en'))->toBe('Old title')
        ->and(t307Audit('course.revision.review.rejected', $course))->toBe(1);

    $activity = Activity::query()->where('event', 'course.revision.review.rejected')->where('subject_id', $course->getKey())->firstOrFail();
    expect(json_encode($activity->properties))->toContain($reason);

    $reopened = t307Open($instructor, $course->fresh(), $rejected);
    expect($reopened->payload)->toBe($rejected->payload)
        ->and($reopened->status)->toBe(CourseRevisionStatus::Draft);

    $this->get('/en/courses/'.$course->slug_en)->assertSee('Old title')->assertDontSee('Proposed title');
})->group('T-P3-07', 'A3', 'YG-GAP-CHG-003');

// ---------- rules ----------

it('allows one open revision per course, enforced in the database as well', function (): void {
    $instructor = t307User('instructor_independent');
    $course = t307Published($instructor);
    $first = t307Open($instructor, $course);

    expect(fn () => t307Open($instructor, $course))
        ->toThrow(fn (ValidationException $e) => expect($e->errors()['course'][0])->toBe(__('course_revisions.already_open')));

    expect(fn () => CourseRevision::query()->create([
        'course_id' => $course->getKey(), 'author_id' => $instructor->getKey(), 'number' => 99,
        'status' => CourseRevisionStatus::Draft, 'base' => $first->base, 'payload' => $first->base, 'open_course_id' => $course->getKey(),
    ]))->toThrow(QueryException::class);
})->group('T-P3-07', 'A1');

it('opens revisions only for published courses', function (): void {
    $instructor = t307User('instructor_independent');
    $course = t307Published($instructor, ['status' => CourseStatus::Draft]);

    expect(fn () => t307Open($instructor, $course))->toThrow(ValidationException::class);
})->group('T-P3-07', 'A1');

it('refuses to save or submit a revision that is no longer a draft, and refuses an empty submit', function (): void {
    $instructor = t307User('instructor_independent');
    $course = t307Published($instructor);
    $empty = t307Open($instructor, $course);

    expect(fn () => app(SubmitCourseRevision::class)($instructor, $empty))
        ->toThrow(fn (ValidationException $e) => expect($e->errors()['revision'][0])->toBe(__('course_revisions.no_changes')));

    app(DiscardCourseRevision::class)($instructor, $empty->fresh());
    $pending = t307Submitted($instructor, $course->fresh(), 'Another title');
    $input = t307Input($pending);
    expect(fn () => app(SaveCourseRevision::class)($instructor, $pending, $input))->toThrow(ValidationException::class);
})->group('T-P3-07', 'A1');

it('refuses section and item ids that do not belong to the base snapshot', function (): void {
    $instructor = t307User('instructor_independent');
    $course = t307Published($instructor);
    $revision = t307Open($instructor, $course);
    $input = t307Input($revision);
    $input['sections'][0]['items'][] = ['id' => 999999, 'kind' => 'lesson', 'title' => ['en' => 'Ghost'], 'lesson_type' => 'text', 'status' => 'draft'];

    expect(fn () => app(SaveCourseRevision::class)($instructor, $revision, $input))
        ->toThrow(fn (ValidationException $e) => expect(collect($e->errors())->flatten()->implode(' '))->toContain(__('course_revisions.stale_structure')));
})->group('T-P3-07', 'A1');

it('refuses an author approving or rejecting their own revision', function (): void {
    $instructor = t307User('instructor_independent');
    $course = t307Published($instructor);
    $revision = t307Submitted($instructor, $course);

    expect(fn () => app(ApproveCourseRevision::class)($revision->fresh(), $instructor))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('status'));
    expect(fn () => app(ReviewCourseRevision::class)($instructor, $revision->fresh(), 'rejected', 'Self review is never allowed'))
        ->toThrow(AuthorizationException::class);
})->group('T-P3-07', 'A2');

it('refuses a review from a user without the course publish grant', function (): void {
    $instructor = t307User('instructor_independent');
    $other = t307User('instructor_independent');
    $course = t307Published($instructor);
    $revision = t307Submitted($instructor, $course);

    expect(fn () => app(ReviewCourseRevision::class)($other, $revision->fresh(), 'approved'))
        ->toThrow(AuthorizationException::class);
    expect($revision->fresh()->status)->toBe(CourseRevisionStatus::Pending);
})->group('T-P3-07', 'A2');

it('frees the course for a new revision when a draft or a pending revision is discarded', function (): void {
    $instructor = t307User('instructor_independent');
    $course = t307Published($instructor);

    $draft = t307Open($instructor, $course);
    app(DiscardCourseRevision::class)($instructor, $draft);
    expect($draft->fresh()->status)->toBe(CourseRevisionStatus::Discarded)
        ->and($draft->fresh()->open_course_id)->toBeNull();
    expect(t307Open($instructor, $course->fresh()))->not->toBeNull();

    $pending = CourseRevision::query()->where('course_id', $course->getKey())->where('status', CourseRevisionStatus::Draft)->firstOrFail();
    app(SaveCourseRevision::class)($instructor, $pending, [...t307Input($pending), 'course' => [...$pending->base['course'], 'title' => ['en' => 'To be discarded']]]);
    app(SubmitCourseRevision::class)($instructor, $pending->fresh());
    app(DiscardCourseRevision::class)($instructor, $pending->fresh());

    expect($pending->fresh()->status)->toBe(CourseRevisionStatus::Discarded);
    expect(t307Open($instructor, $course->fresh()))->not->toBeNull();
})->group('T-P3-07', 'A2');

it('keeps a live title edit made after opening when the approved revision only changed a lesson title', function (): void {
    $instructor = t307User('instructor_independent');
    $admin = t307User('admin');
    $course = t307Published($instructor);
    $revision = t307Open($instructor, $course);
    $input = t307Input($revision);
    $input['sections'][0]['items'][0]['title'] = ['en' => 'Lesson retitled in revision'];
    app(SaveCourseRevision::class)($instructor, $revision, $input);
    app(SubmitCourseRevision::class)($instructor, $revision->fresh());

    app(SaveCourseWizard::class)($admin, ['title' => ['en' => 'Live title edit']], $course->fresh(), 1, false);
    app(ReviewCourseRevision::class)($admin, $revision->fresh(), 'approved');

    $live = $course->fresh();
    expect($live->getTranslation('title', 'en'))->toBe('Live title edit')
        ->and(Lesson::query()->where('title->en', 'Lesson retitled in revision')->exists())->toBeTrue();
})->group('T-P3-07', 'A2');

// ---------- guards ----------

it('blocks the wizard for an independent instructor on a published course, but not for an admin or a draft', function (): void {
    $instructor = t307User('instructor_independent');
    $admin = t307User('admin');
    $course = t307Published($instructor);

    expect(fn () => app(SaveCourseWizard::class)($instructor, ['title' => ['en' => 'Direct']], $course, 1, false))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('course'));

    app(SaveCourseWizard::class)($admin, ['title' => ['en' => 'Admin live edit']], $course->fresh(), 1, false);
    expect($course->fresh()->getTranslation('title', 'en'))->toBe('Admin live edit');

    $draft = t307Published($instructor, ['status' => CourseStatus::Draft]);
    app(SaveCourseWizard::class)($instructor, ['title' => ['en' => 'Draft edit']], $draft, 1, false);
    expect($draft->fresh()->getTranslation('title', 'en'))->toBe('Draft edit');
})->group('T-P3-07', 'A1');

it('does not require a revision for academy-owned published courses', function (): void {
    $instructor = t307User('instructor_academy');
    $course = t307Published(null);
    $course->instructors()->attach($instructor, ['role' => 'instructor', 'scope' => Course::INSTRUCTOR_SCOPE_MANAGE]);

    expect(RevisionAccess::requiresRevision($instructor, $course))->toBeFalse();
})->group('T-P3-07', 'A3', 'YG-GAP-CHG-002');

it('records a direct live edit of an academy course by an admin with publish, and nothing when nothing changed', function (): void {
    $admin = t307User('admin');
    $course = t307Published(null);

    app(SaveCourseWizard::class)($admin, ['title' => ['en' => 'Direct academy edit']], $course, 1, false);

    $events = Activity::query()->where('event', 'course.updated_live')->where('subject_id', $course->getKey())->get();
    expect($events)->toHaveCount(1);
    expect(json_encode($events->first()->properties))->toContain('Old title')->toContain('Direct academy edit');

    app(SaveCourseWizard::class)($admin, ['title' => ['en' => 'Direct academy edit']], $course->fresh(), 1, false);
    expect(t307Audit('course.updated_live', $course))->toBe(1);
})->group('T-P3-07', 'A3', 'YG-GAP-CHG-002');

// ---------- YG-GAP-CHG-001 curriculum guards on a published independent course ----------

function t307RevisionRequired(Closure $call): void
{
    expect($call)->toThrow(fn (ValidationException $e) => expect($e->errors()['course'][0] ?? null)->toBe(__('course_revisions.use_revision')));
}

it('blocks every curriculum structure change on a published independent course until it goes through a revision', function (): void {
    $instructor = t307User('instructor_independent');
    $course = t307Published($instructor);
    $section = t307Section($course);
    $item = $section->items()->firstOrFail();
    $lesson = $item->itemable;

    t307RevisionRequired(fn () => app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Extra section']]));
    t307RevisionRequired(fn () => app(ManageSections::class)->rename($instructor, $section, ['title' => ['en' => 'Renamed live']]));
    t307RevisionRequired(fn () => app(ManageSections::class)->archive($instructor, $section));
    t307RevisionRequired(fn () => app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Extra lesson'], 'type' => 'text']));
    t307RevisionRequired(fn () => app(ManageLessons::class)->archive($instructor, $lesson));
    t307RevisionRequired(fn () => app(ManageLessons::class)->update($instructor, $lesson, ['title' => ['en' => 'Retitled live']]));
    t307RevisionRequired(fn () => app(ReorderCurriculum::class)($instructor, $course, []));
    t307RevisionRequired(fn () => app(SetCurriculumStatus::class)($instructor, $course, [$item->id], 'draft'));
    t307RevisionRequired(fn () => app(ManageAssessmentItems::class)->add($instructor, $section, 'quiz', ['title' => ['en' => 'Extra quiz']]));
    t307RevisionRequired(fn () => app(ManageAssessmentItems::class)->archive($instructor, $item));

    expect($course->fresh()->getTranslation('title', 'en'))->toBe('Old title')
        ->and($section->fresh()->getTranslation('title', 'en'))->toBe('Section A')
        ->and($section->fresh()->archived_at)->toBeNull()
        ->and($lesson->fresh()->archived_at)->toBeNull()
        ->and($lesson->fresh()->getTranslation('title', 'en'))->toBe('Old lesson');
})->group('T-P3-07', 'A1', 'YG-GAP-CHG-001');

it('lets a published independent course keep an edit that changes no lesson title, and asks no revision of academy-owned courses', function (): void {
    $instructor = t307User('instructor_independent');
    $course = t307Published($instructor);
    $lesson = t307Section($course)->items()->firstOrFail()->itemable;

    app(ManageLessons::class)->update($instructor, $lesson, ['title' => $lesson->getTranslations('title'), 'duration_seconds' => 480]);

    expect($lesson->fresh()->duration_seconds)->toBe(480)
        ->and($lesson->fresh()->getTranslation('title', 'en'))->toBe('Old lesson');

    $academyInstructor = t307User('instructor_academy');
    $academy = t307Published(null);
    $academy->instructors()->attach($academyInstructor, ['role' => 'instructor', 'scope' => Course::INSTRUCTOR_SCOPE_MANAGE]);
    $academyLesson = t307Section($academy)->items()->firstOrFail()->itemable;

    app(ManageLessons::class)->update($academyInstructor, $academyLesson, ['title' => ['en' => 'Academy retitle']]);

    expect($academyLesson->fresh()->getTranslation('title', 'en'))->toBe('Academy retitle')
        ->and(RevisionAccess::requiresRevision($academyInstructor, $academy))->toBeFalse();
})->group('T-P3-07', 'A1', 'YG-GAP-CHG-001', 'YG-GAP-CHG-002');

// ---------- Notifications (YG-GAP-CHG-001 reviewer diff, YG-GAP-CHG-003 instructor notice) ----------

it('notifies active admins who may publish the course when a revision is submitted, not the author nor a publisher without course approval', function (): void {
    Notification::fake();
    $instructor = t307User('instructor_independent');
    $admin = t307User('admin');
    $course = t307Published($instructor);
    $publisherWithoutApproval = User::factory()->create(['status' => 'active']);
    $publisherWithoutApproval->givePermissionTo(['course.publish', 'panel.admin.access']);
    $inactiveAdmin = t307User('admin');
    $inactiveAdmin->forceFill(['status' => 'suspended'])->save();

    t307Submitted($instructor, $course);

    Notification::assertSentTo($admin, TemplatedNotice::class, fn (TemplatedNotice $n): bool => $n->event() === 'course.revision_submitted');
    Notification::assertNotSentTo([$instructor, $publisherWithoutApproval, $inactiveAdmin], TemplatedNotice::class);
})->group('T-P3-07', 'A2', 'YG-GAP-CHG-001');

it('notifies the author and every course instructor of an approval, and the rejection reason reaches them', function (): void {
    Notification::fake();
    $instructor = t307User('instructor_independent');
    $coInstructor = t307User('instructor_independent');
    $admin = t307User('admin');
    $course = t307Published($instructor);
    $course->instructors()->attach($coInstructor, ['role' => 'instructor', 'scope' => Course::INSTRUCTOR_SCOPE_MANAGE]);

    $approved = t307Submitted($instructor, $course);
    app(ReviewCourseRevision::class)($admin, $approved->fresh(), 'approved');

    Notification::assertSentTo([$instructor, $coInstructor], TemplatedNotice::class, fn (TemplatedNotice $n): bool => $n->event() === 'course.revision_approved');
    Notification::assertNotSentTo($admin, TemplatedNotice::class, fn (TemplatedNotice $n): bool => $n->event() === 'course.revision_approved');

    $reason = 'Please keep the original tone of the title.';
    $rejected = t307Submitted($instructor, $course->fresh(), 'Second proposal');
    app(ReviewCourseRevision::class)($admin, $rejected->fresh(), 'rejected', $reason);

    Notification::assertSentTo([$instructor, $coInstructor], TemplatedNotice::class, fn (TemplatedNotice $n): bool => $n->event() === 'course.revision_rejected'
        && str_contains($n->toArray($instructor)['message'], $reason));
})->group('T-P3-07', 'A3', 'YG-GAP-CHG-003');

it('sends each review notice once per recipient even when the listener runs again', function (): void {
    Notification::fake();
    $instructor = t307User('instructor_independent');
    $admin = t307User('admin');
    $course = t307Published($instructor);
    $reason = 'Needs a clearer lesson description.';
    $rejected = t307Submitted($instructor, $course);
    app(ReviewCourseRevision::class)($admin, $rejected->fresh(), 'rejected', $reason);

    $sent = Notification::sent($instructor, TemplatedNotice::class)->count();
    expect($sent)->toBe(1);

    app(NotifyOfCourseRevision::class)->reviewed(new CourseRevisionReviewed($rejected->fresh(), false, $reason));
    app(NotifyOfCourseRevision::class)->reviewed(new CourseRevisionReviewed($rejected->fresh(), false, $reason));

    expect(Notification::sent($instructor, TemplatedNotice::class))->toHaveCount($sent);
})->group('T-P3-07', 'A3', 'YG-GAP-CHG-003');

it('keeps an approved revision applied when the notification channel fails', function (): void {
    $instructor = t307User('instructor_independent');
    $admin = t307User('admin');
    $course = t307Published($instructor);

    // Swapped before any notice is sent: the mail driver is cached on first use.
    $attempts = new stdClass;
    $attempts->count = 0;
    Notification::extend('mail', fn (): object => new class($attempts)
    {
        public function __construct(private readonly stdClass $attempts) {}

        public function send(object $notifiable, object $notification): void
        {
            $this->attempts->count++;

            throw new RuntimeException('Mail transport is down');
        }
    });

    $revision = t307Submitted($instructor, $course, 'Approved despite mail outage');
    $attempts->count = 0;

    app(ReviewCourseRevision::class)($admin, $revision->fresh(), 'approved');

    expect($attempts->count)->toBeGreaterThan(0)
        ->and($revision->fresh()->status)->toBe(CourseRevisionStatus::Approved)
        ->and($course->fresh()->getTranslation('title', 'en'))->toBe('Approved despite mail outage');
})->group('T-P3-07', 'A2', 'YG-GAP-CHG-001');
