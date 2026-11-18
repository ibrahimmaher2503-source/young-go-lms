<?php

// T-P3-03 | PRD 9.2 9.4 | A1

declare(strict_types=1);

use App\Domain\Catalog\Actions\PublishReadyCourse;
use App\Domain\Catalog\Actions\ReviewCourse;
use App\Domain\Catalog\Actions\SaveCourseWizard;
use App\Domain\Catalog\Actions\SubmitCourseForReview;
use App\Domain\Catalog\CourseStatus;
use App\Models\Category;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    config(['markets.EG.active' => true]);
});

function t303rUser(string $role): User
{
    $user = User::factory()->create(['status' => 'active']);
    $user->assignRole($role);

    return $user;
}

/** An independent instructor's course with a complete Basic step, saved through the wizard. */
function t303rDraft(User $instructor): Course
{
    $category = Category::query()->create(['name' => ['ar' => 'فنون', 'en' => 'Arts'], 'slug_ar' => 'فنون-'.uniqid(), 'slug_en' => 'arts-'.uniqid(), 'sort' => 1, 'status' => 'active']);

    return app(SaveCourseWizard::class)($instructor, [
        'title' => ['ar' => 'الرسم', 'en' => 'Painting basics'],
        'short_description' => ['ar' => 'مقدمة', 'en' => 'Intro'],
        'category_id' => $category->id, 'level' => 'beginner', 'content_language' => 'en', 'format' => 'recorded',
        'min_age' => 7, 'max_age' => 12,
    ], null, 1, true);
}

function t303rLesson(Course $course, string $title = 'Lesson one'): Lesson
{
    $section = CourseSection::query()->create(['course_id' => $course->getKey(), 'published' => true, 'sort' => 1, 'title' => ['en' => 'Section']]);
    $lesson = Lesson::query()->create(['title' => ['en' => $title], 'type' => 'text', 'is_preview' => false]);
    $lesson->item()->create(['course_id' => $course->getKey(), 'section_id' => $section->getKey(), 'sort' => 1, 'status' => 'published']);

    return $lesson;
}

function t303rPrice(User $admin, Course $course): void
{
    app(SaveCourseWizard::class)($admin, ['access_mode' => 'paid', 'market_prices' => ['EG' => '150']], $course, 3, true);
}

function t303rAudit(string $event, Course $course): int
{
    return Activity::query()->where('event', $event)->where('subject_id', $course->getKey())->count();
}

it('submits a complete independent course for review, locks it for the instructor and keeps publishing with the admin', function (): void {
    $instructor = t303rUser('instructor_independent');
    $admin = t303rUser('admin');
    $course = t303rDraft($instructor);
    t303rLesson($course);

    $submitted = app(SubmitCourseForReview::class)($instructor, $course);

    expect($submitted->status)->toBe(CourseStatus::PendingReview)
        ->and($submitted->submitted_at)->not->toBeNull()
        ->and(t303rAudit('course.submitted_for_review', $course))->toBe(1);

    expect(fn () => app(SaveCourseWizard::class)($instructor, ['title' => ['en' => 'Renamed while pending']], $submitted, 1, false))
        ->toThrow(ValidationException::class);
    expect(fn () => app(PublishReadyCourse::class)($instructor, $submitted))->toThrow(AuthorizationException::class);
    expect($submitted->fresh()->status)->toBe(CourseStatus::PendingReview);
})->group('T-P3-03', 'A1');

it('approves a pending independent course once the admin has set pricing, and audits the decision', function (): void {
    $instructor = t303rUser('instructor_independent');
    $admin = t303rUser('admin');
    $course = t303rDraft($instructor);
    t303rLesson($course);
    app(SubmitCourseForReview::class)($instructor, $course);

    t303rPrice($admin, $course);
    app(ReviewCourse::class)($admin, $course->fresh(), 'approved');

    expect($course->fresh()->status)->toBe(CourseStatus::Published)
        ->and(t303rAudit('course.review.approved', $course))->toBe(1);
})->group('T-P3-03', 'A1');

it('refuses to approve without pricing set, naming the status key', function (): void {
    $instructor = t303rUser('instructor_independent');
    $admin = t303rUser('admin');
    $course = t303rDraft($instructor);
    t303rLesson($course);
    app(SubmitCourseForReview::class)($instructor, $course);

    expect(fn () => app(ReviewCourse::class)($admin, $course->fresh(), 'approved'))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('status'));

    expect($course->fresh()->status)->toBe(CourseStatus::PendingReview)
        ->and(t303rAudit('course.review.approved', $course))->toBe(0);
})->group('T-P3-03', 'A1');

it('rejects a review without a reason or with fewer than 10 characters and leaves the course pending without audit', function (?string $reason): void {
    $instructor = t303rUser('instructor_independent');
    $admin = t303rUser('admin');
    $course = t303rDraft($instructor);
    t303rLesson($course);
    app(SubmitCourseForReview::class)($instructor, $course);

    expect(fn () => app(ReviewCourse::class)($admin, $course->fresh(), 'rejected', $reason))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('reason'));

    expect($course->fresh()->status)->toBe(CourseStatus::PendingReview)
        ->and(t303rAudit('course.review.rejected', $course))->toBe(0);
})->with([null, 'too short'])->group('T-P3-03', 'A1');

it('returns a rejected course to draft with its reason, and the instructor can edit and resubmit', function (): void {
    $instructor = t303rUser('instructor_independent');
    $admin = t303rUser('admin');
    $course = t303rDraft($instructor);
    t303rLesson($course);
    app(SubmitCourseForReview::class)($instructor, $course);

    $reason = 'Please add a clear description of the lesson goals.';
    app(ReviewCourse::class)($admin, $course->fresh(), 'rejected', $reason);

    $rejected = $course->fresh();
    expect($rejected->status)->toBe(CourseStatus::Draft)
        ->and($rejected->review_reason)->toBe($reason)
        ->and(t303rAudit('course.review.rejected', $course))->toBe(1);

    $activity = Activity::query()->where('event', 'course.review.rejected')->where('subject_id', $course->getKey())->firstOrFail();
    expect(json_encode($activity->properties))->toContain($reason);

    app(SaveCourseWizard::class)($instructor, ['title' => ['en' => 'Painting basics revised']], $rejected, 1, false);
    expect($rejected->fresh()->getTranslation('title', 'en'))->toBe('Painting basics revised');

    $resubmitted = app(SubmitCourseForReview::class)($instructor, $rejected->fresh());
    expect($resubmitted->status)->toBe(CourseStatus::PendingReview)->and($resubmitted->review_reason)->toBeNull();
})->group('T-P3-03', 'A1');

it('refuses a review decision on a course that is not pending', function (): void {
    $instructor = t303rUser('instructor_independent');
    $admin = t303rUser('admin');
    $course = t303rDraft($instructor);
    t303rLesson($course);
    t303rPrice($admin, $course);

    expect(fn () => app(ReviewCourse::class)($admin, $course->fresh(), 'approved'))
        ->toThrow(ValidationException::class);
    expect(fn () => app(ReviewCourse::class)($admin, $course->fresh(), 'rejected', 'A long enough reason'))
        ->toThrow(ValidationException::class);
    expect($course->fresh()->status)->toBe(CourseStatus::Draft);
})->group('T-P3-03', 'A1');

it('refuses submission without a lesson and names the curriculum blocker', function (): void {
    $instructor = t303rUser('instructor_independent');
    $course = t303rDraft($instructor);

    expect(fn () => app(SubmitCourseForReview::class)($instructor, $course))
        ->toThrow(fn (ValidationException $e) => expect($e->getMessage())->toContain(__('course_wizard.check.curriculum')));

    expect($course->fresh()->status)->toBe(CourseStatus::Draft)
        ->and(t303rAudit('course.submitted_for_review', $course))->toBe(0);
})->group('T-P3-03', 'A1');

it('refuses submission of a course with missing basic data and names the basic blocker', function (): void {
    $instructor = t303rUser('instructor_independent');
    $course = Course::query()->create(['title' => ['en' => 'No category yet'], 'slug_ar' => 'x-'.uniqid(), 'slug_en' => 'x-'.uniqid(), 'owner_type' => Course::OWNER_INDEPENDENT, 'owner_instructor_id' => $instructor->id]);
    $course->instructors()->attach($instructor, ['role' => 'owner', 'scope' => Course::INSTRUCTOR_SCOPE_MANAGE]);
    t303rLesson($course);

    expect(fn () => app(SubmitCourseForReview::class)($instructor, $course))
        ->toThrow(fn (ValidationException $e) => expect($e->getMessage())->toContain(__('course_wizard.check.basic')));
})->group('T-P3-03', 'A1');

/** An academy course with a category, an academy instructor and a lesson, ready for publishing except pricing. */
function t303rAcademyCourse(User $admin): Course
{
    $academy = t303rUser('instructor_academy');
    $course = Course::query()->create(['title' => ['en' => 'Academy course'], 'slug_ar' => 'ac-'.uniqid(), 'slug_en' => 'ac-'.uniqid(), 'owner_type' => Course::OWNER_ACADEMY]);
    $category = Category::query()->create(['name' => ['en' => 'Arts'], 'slug_ar' => 'ar-'.uniqid(), 'slug_en' => 'en-'.uniqid(), 'sort' => 1, 'status' => 'active']);
    $course->forceFill(['category_id' => $category->id, 'level' => 'beginner', 'content_language' => 'en', 'format' => 'recorded'])->save();
    $course->instructors()->attach($academy, ['role' => 'instructor', 'scope' => 'manage']);
    t303rLesson($course);

    return $course;
}

it('lets an academy admin publish a ready course directly, without the review queue', function (): void {
    $admin = t303rUser('admin');
    $course = t303rAcademyCourse($admin);
    t303rPrice($admin, $course);

    $this->actingAs($admin);
    $published = app(PublishReadyCourse::class)($admin, $course->fresh());

    expect($published->status)->toBe(CourseStatus::Published)
        ->and(t303rAudit('course.published', $course))->toBe(1);
})->group('T-P3-03', 'A1');

it('publishes through PublishReadyCourse for an actor who is not the logged-in user', function (): void {
    $admin = t303rUser('admin');
    $course = t303rAcademyCourse($admin);
    t303rPrice($admin, $course);

    expect(app(PublishReadyCourse::class)($admin, $course->fresh())->status)->toBe(CourseStatus::Published);
})->group('T-P3-03', 'A1');
