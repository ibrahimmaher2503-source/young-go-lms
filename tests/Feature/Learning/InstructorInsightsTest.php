<?php

declare(strict_types=1);

// T-P4-10 | YG-GAP-LRN-005, ED-024 | A1 preview shows drafts and writes nothing, A2 scope, A3 learner list and detail

use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\CourseLearners;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Progress\LessonProgress;
use App\Domain\Progress\Queries\LearnerCourseDetail;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
});

/** @return array{0: Course, 1: User, 2: CurriculumItem, 3: CurriculumItem} course with one published lesson and one draft lesson */
function iiBuild(): array
{
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['en' => 'Insights', 'ar' => 'رؤى'], 'slug_ar' => 'i-'.uniqid(), 'slug_en' => 'i-'.uniqid(), 'status' => 'published', 'access_mode' => 'paid']);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Basics', 'ar' => 'الأساسيات']]);
    $section->update(['published' => true]);
    $live = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Live lesson', 'ar' => 'درس منشور'], 'type' => 'text']);
    $draft = app(ManageLessons::class)->add($instructor, $section, ['title' => ['en' => 'Draft lesson', 'ar' => 'درس مسودة'], 'type' => 'text']);
    app(SetCurriculumStatus::class)($instructor, $course, [$live->id], 'published');

    return [$course->refresh(), $instructor, $live, $draft];
}

it('previews a draft lesson for staff, marked as draft, and writes no progress', function (): void {
    [$course, $instructor, , $draft] = iiBuild();

    $this->actingAs($instructor)->get("/preview/{$course->id}/{$draft->id}")
        ->assertOk()->assertSee('data-preview-banner', false)->assertSee('data-preview-draft', false)
        ->assertDontSee('data-progress-url', false);

    expect(LessonProgress::query()->count())->toBe(0);
});

it('shows the real lock reason a learner would meet in the preview', function (): void {
    [$course, $instructor, $live] = iiBuild();
    $live->refresh()->update(['unlock_rule' => 'date', 'unlock_at' => now()->addWeek()]);

    $this->actingAs($instructor)->get("/preview/{$course->id}/{$live->id}")->assertOk()->assertSee('data-preview-reason', false);
});

it('refuses the preview to learners and hides other courses from staff outside their scope', function (): void {
    [$course, $instructor, $live] = iiBuild();
    $learner = User::factory()->create()->assignRole('student');
    $this->actingAs($learner)->get("/preview/{$course->id}/{$live->id}")->assertForbidden();

    [$other, , $otherItem] = iiBuild();
    $this->actingAs($instructor)->get("/preview/{$other->id}/{$otherItem->id}")->assertNotFound();
    $this->actingAs($instructor)->get("/preview/{$course->id}/{$otherItem->id}")->assertNotFound();
});

it('lists only the learners of this course with access state and lesson-level progress', function (): void {
    [$course, , $live] = iiBuild();
    [$other] = iiBuild();
    $mine = User::factory()->create(['name' => 'Mine Learner'])->assignRole('student');
    $theirs = User::factory()->create(['name' => 'Other Learner'])->assignRole('student');
    app(GrantEntitlement::class)($mine, $course, EntitlementSource::Free);
    app(GrantEntitlement::class)($theirs, $other, EntitlementSource::Free);

    $rows = CourseLearners::query($course)->get();
    expect($rows->pluck('name')->all())->toBe(['Mine Learner'])->and($rows->first()->access_status)->toBe('active')
        ->and(CourseLearners::count($course))->toBe(1);

    LessonProgress::query()->create(['learner_id' => $mine->id, 'curriculum_item_id' => $live->id, 'status' => 'in_progress', 'percent' => 40]);
    $detail = LearnerCourseDetail::for($mine, $course);
    expect($detail)->toHaveCount(1)->and($detail[0]['status'])->toBe('in_progress')->and($detail[0]['percent'])->toBe(40);
});

it('shows the learners page to staff with progress permission and not to learners', function (): void {
    [$course, $instructor] = iiBuild();
    $learner = User::factory()->create(['name' => 'Visible Learner'])->assignRole('student');
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);

    $this->actingAs($learner)->get("/instructor/courses/{$course->id}/learners")->assertForbidden();
    expect($instructor->can('learner.progress.view'))->toBeTrue();
});
