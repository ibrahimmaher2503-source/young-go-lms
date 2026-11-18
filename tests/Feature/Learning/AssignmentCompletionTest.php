<?php

declare(strict_types=1);

use App\Domain\Assignments\Actions\AddSubmissionFeedback;
use App\Domain\Assignments\Actions\DecideSubmission;
use App\Domain\Completion\CompletionReport;
use App\Domain\Completion\CourseCompletionRule;
use App\Domain\Curriculum\Actions\ManageAssessmentItems;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Models\AssignmentSubmission;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('requires explicit assignment acceptance for completion', function (string $status, bool $eligible): void {
    config()->set('learning.completion.graded_counts_as_accepted', true);
    $this->seed(RolePermissionSeeder::class);
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $learner = User::factory()->create()->assignRole('student');
    $course = Course::query()->create(['title' => ['en' => 'Completion'], 'slug_ar' => 'completion', 'slug_en' => 'completion', 'status' => 'published']);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Section']]);
    $section->update(['published' => true]);
    $item = app(ManageAssessmentItems::class)->add($instructor, $section, 'assignment', ['title' => ['en' => 'Required work']]);
    app(SetCurriculumStatus::class)($instructor, $course, [$item->id], 'published');
    AssignmentSubmission::query()->create(['assignment_id' => $item->itemable_id, 'learner_id' => $learner->id, 'status' => $status]);
    $rules = new CourseCompletionRule(['require_assignments' => true]);

    $report = CompletionReport::measure($learner, $course, $rules);
    expect($report->eligible())->toBe($eligible)
        ->and($report->criteria[CompletionReport::ASSIGNMENTS]['have'])->toBe($eligible ? 1 : 0);
})->with([
    'graded is not accepted' => [AssignmentSubmission::GRADED, false],
    'submitted is not accepted' => [AssignmentSubmission::SUBMITTED, false],
    'rejected is not accepted' => [AssignmentSubmission::REJECTED, false],
    'accepted satisfies the rule' => [AssignmentSubmission::ACCEPTED, true],
])->group('T-P6-05', 'A1', 'PRD-14');

it('records repeated grades but only notifies for changed review decisions', function (): void {
    $this->seed(RolePermissionSeeder::class);
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $learner = User::factory()->create()->assignRole('student');
    $course = Course::query()->create(['title' => ['en' => 'Review'], 'slug_ar' => 'review', 'slug_en' => 'review', 'status' => 'published']);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Section']]);
    $item = app(ManageAssessmentItems::class)->add($instructor, $section, 'assignment', ['title' => ['en' => 'Required work']]);
    $submission = AssignmentSubmission::query()->create([
        'assignment_id' => $item->itemable_id, 'learner_id' => $learner->id, 'status' => AssignmentSubmission::SUBMITTED,
    ]);
    $decide = app(DecideSubmission::class);
    $decide($instructor, $submission, 'graded', 50, 'Feedback');
    expect($learner->notifications()->count())->toBe(1);
    $decide($instructor, $submission, 'graded', 50, 'Feedback');
    expect($learner->notifications()->count())->toBe(1)->and($submission->decisions()->count())->toBe(2);
    $decide($instructor, $submission, 'graded', 60, 'Feedback');
    expect($learner->notifications()->count())->toBe(2);
})->group('T-P6-04', 'PRD-13');

it('rolls back instructor feedback when audit recording is unavailable', function (): void {
    $this->seed(RolePermissionSeeder::class);
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $learner = User::factory()->create()->assignRole('student');
    $course = Course::query()->create(['title' => ['en' => 'Review'], 'slug_ar' => 'feedback', 'slug_en' => 'feedback', 'status' => 'published']);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Section']]);
    $item = app(ManageAssessmentItems::class)->add($instructor, $section, 'assignment', ['title' => ['en' => 'Required work']]);
    $submission = AssignmentSubmission::query()->create([
        'assignment_id' => $item->itemable_id, 'learner_id' => $learner->id, 'status' => AssignmentSubmission::SUBMITTED,
    ]);

    activity()->disableLogging();
    try {
        expect(fn () => app(AddSubmissionFeedback::class)($instructor, $submission, 'Feedback'))
            ->toThrow(RuntimeException::class);
    } finally {
        activity()->enableLogging();
    }
    expect($submission->feedback()->count())->toBe(0);
})->group('T-P6-04', 'PRD-13');
