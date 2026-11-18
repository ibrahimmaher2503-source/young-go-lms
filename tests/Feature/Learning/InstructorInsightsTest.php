<?php

declare(strict_types=1);

// T-P4-10 | YG-GAP-LRN-005, ED-024 | A1 preview shows drafts and writes nothing, A2 scope, A3 learner list and detail

use App\Domain\Curriculum\Actions\ManageAssessmentItems;
use App\Domain\Curriculum\Actions\ManageLessons;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\CourseLearners;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Progress\Actions\RecordProgress;
use App\Domain\Progress\LessonProgress;
use App\Domain\Progress\Queries\LearnerCourseDetail;
use App\Domain\Settings\PlatformFlag;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\LessonCurriculumDocument;
use App\Models\QuizAttempt;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Pennant\Feature;

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
})->group('T-P4-10', 'A2', 'YG-GAP-LRN-005', 'ED-024');

it('shows the real lock reason a learner would meet in the preview', function (): void {
    [$course, $instructor, $live] = iiBuild();
    $live->refresh()->update(['unlock_rule' => 'date', 'unlock_at' => now()->addWeek()]);

    $this->actingAs($instructor)->get("/preview/{$course->id}/{$live->id}")->assertOk()->assertSee('data-preview-reason', false);
})->group('T-P4-10', 'A2', 'YG-GAP-LRN-005', 'ED-024');

it('refuses the preview to learners and hides other courses from staff outside their scope', function (): void {
    [$course, $instructor, $live] = iiBuild();
    $learner = User::factory()->create()->assignRole('student');
    $this->actingAs($learner)->get("/preview/{$course->id}/{$live->id}")->assertForbidden();

    [$other, , $otherItem] = iiBuild();
    $this->actingAs($instructor)->get("/preview/{$other->id}/{$otherItem->id}")->assertNotFound();
    $this->actingAs($instructor)->get("/preview/{$course->id}/{$otherItem->id}")->assertNotFound();
})->group('T-P4-10', 'A1', 'A2', 'YG-GAP-LRN-005', 'ED-024');

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

it('previews assistant readiness without mounting the interactive panel', function (): void {
    [$course, $instructor, $item] = iiBuild();
    $course->update(['ai_enabled' => true]);
    $item->update(['ai_enabled' => true]);
    $item->itemable->update(['type' => 'video']);
    LessonCurriculumDocument::query()->create([
        'lesson_id' => $item->itemable_id, 'version' => 1, 'path' => 'curriculum/insights.pdf',
        'sha256' => hash('sha256', 'insights'), 'size' => 128, 'pages' => 1,
        'status' => LessonCurriculumDocument::STATUS_READY, 'active' => true,
    ]);
    Feature::for(null)->activate(PlatformFlag::AiAssistant->value);

    $this->actingAs($instructor)->get("/preview/{$course->id}/{$item->id}")
        ->assertOk()->assertSee('data-preview-assistant="available"', false)
        ->assertDontSee('data-ai-panel', false)->assertDontSee('data-ai-open', false);
});

it('shows upcoming and historical grant rows once per learner with the original enrollment date', function (): void {
    [$course] = iiBuild();
    $learner = User::factory()->create()->assignRole('student');
    $grant = app(GrantEntitlement::class);
    $start = now()->subWeek()->startOfDay();
    $grant($learner, $course, EntitlementSource::Free, 'old', $start, now()->subDay());
    $grant($learner, $course, EntitlementSource::Free, 'planned', now()->addWeek());
    expect(CourseLearners::query($course)->sole()->access_status)->toBe('ended');
    $grant($learner, $course, EntitlementSource::Free, 'current');
    $row = CourseLearners::query($course)->sole();
    expect($row->access_status)->toBe('active')->and($row->enrolled_at)->toBe($start->toDateTimeString());
})->group('T-P4-10', 'A1', 'YG-GAP-INS-004');

it('shows real assigned course progress, activity and assessment outcomes in the learners table', function (): void {
    $this->freezeTime();
    [$course, $instructor, $lesson] = iiBuild();
    [$foreign] = iiBuild();
    $learner = User::factory()->create(['name' => 'Visible Assessment Learner'])->assignRole('student');
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);
    app(RecordProgress::class)->markComplete($learner, $lesson->refresh());

    $quiz = app(ManageAssessmentItems::class)->add($instructor, $lesson->section, 'quiz', ['title' => ['en' => 'Drawing quiz']]);
    $assignment = app(ManageAssessmentItems::class)->add($instructor, $lesson->section, 'assignment', ['title' => ['en' => 'Sketch assignment']]);
    app(SetCurriculumStatus::class)($instructor, $course, [$quiz->id, $assignment->id], 'published');
    QuizAttempt::query()->create([
        'quiz_id' => $quiz->itemable_id, 'learner_id' => $learner->id, 'attempt_no' => 1, 'status' => 'submitted',
        'started_at' => now(), 'submitted_at' => now(), 'snapshot' => [], 'settings' => [], 'passed' => true, 'total' => 10,
    ]);
    AssignmentSubmission::query()->create([
        'assignment_id' => $assignment->itemable_id, 'learner_id' => $learner->id, 'status' => 'accepted',
    ]);

    $this->actingAs($instructor)->get("/instructor/courses/{$course->id}/learners")
        ->assertOk()->assertSee('Visible Assessment Learner')->assertSee('Last activity')
        ->assertSee(now()->isoFormat('LLL'))->assertSee('Completed items')->assertSee('100%')
        ->assertSee('Drawing quiz')->assertSee('Passed')->assertSee('Sketch assignment')->assertSee('Accepted');
    $this->get("/instructor/courses/{$foreign->id}/learners")->assertNotFound();
})->group('T-P4-10', 'A1', 'YG-GAP-INS-004');
