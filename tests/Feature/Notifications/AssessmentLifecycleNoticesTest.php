<?php

declare(strict_types=1);

use App\Domain\Assignments\Actions\SaveSubmissionDraft;
use App\Domain\Assignments\Actions\SubmitAssignment;
use App\Domain\Assignments\Notifications\AssignmentNotice;
use App\Domain\Certificates\Actions\IssueCertificate;
use App\Domain\Certificates\Actions\RevokeCertificate;
use App\Domain\Certificates\Notifications\CertificateRevokedNotice;
use App\Domain\Completion\CourseCompletion;
use App\Domain\Curriculum\Actions\ManageAssessmentItems;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Notifications\NotificationOptOut;
use App\Filament\Resources\SubmissionReview\SubmissionReviewResource;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

function assessmentNoticeCourse(): array
{
    $course = Course::query()->create([
        'title' => ['ar' => 'دورة', 'en' => 'Course'],
        'slug_ar' => 'notice-ar',
        'slug_en' => 'notice-en',
        'status' => 'published',
    ]);
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $learner = User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('student');
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['ar' => 'القسم', 'en' => 'Section']]);
    $item = app(ManageAssessmentItems::class)->add($instructor, $section, 'assignment', ['title' => ['ar' => 'لوحة الطبيعة', 'en' => 'Nature Study']]);

    $item->itemable->update(['allowed_types' => ['text']]);

    return [$course, $instructor, $learner, $section, $item];
}

it('notifies only entitled learners after item or section publication and deduplicates repeat publication', function (bool $publishSection): void {
    [$course, $instructor, $learner, $section, $item] = assessmentNoticeCourse();
    $outsider = User::factory()->create(['status' => 'active'])->assignRole('student');
    Notification::fake();

    $publish = function () use ($publishSection, $course, $instructor, $section, $item): void {
        if ($publishSection) {
            app(ManageSections::class)->setPublished($instructor, $section, true);
        } else {
            $section->update(['published' => true]);
            app(SetCurriculumStatus::class)($instructor, $course, [$item->id], CurriculumItem::STATUS_PUBLISHED);
        }
    };

    DB::beginTransaction();
    $publish();
    Notification::assertNothingSent();
    DB::commit();

    $publish();
    $notices = Notification::sent($learner, AssignmentNotice::class, fn ($notice) => $notice->event() === 'assignment.published');
    expect($notices)->toHaveCount(1)
        ->and($notices->sole()->locale('en')->toArray($learner)['url'])->toBe(route('learn.show', ['course' => $course->id, 'item' => $item->id]));
    Notification::assertNothingSentTo($outsider);
})->with([true, false])->group('T-P9-05', 'A1', 'PRD-20');

it('notifies only authorized course reviewers when work is handed in and links to the protected review page', function (): void {
    [$course, $instructor, $learner, $section, $item] = assessmentNoticeCourse();
    $viewOnly = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $course->instructors()->attach($viewOnly->id, ['role' => 'instructor', 'scope' => 'view']);
    app(ManageSections::class)->setPublished($instructor, $section, true);
    Notification::fake();

    app(SaveSubmissionDraft::class)($learner, $item->fresh(), 'Work', []);
    Notification::assertNothingSent();
    $submission = app(SubmitAssignment::class)($learner, $item->fresh());

    $notices = Notification::sent($instructor, AssignmentNotice::class, fn ($notice) => $notice->event() === 'assignment.submitted');
    expect($notices)->toHaveCount(1)
        ->and($notices->sole()->toArray($instructor)['url'])->toBe(SubmissionReviewResource::getUrl('review', ['record' => $submission], panel: 'instructor'));
    Notification::assertNothingSentTo($viewOnly);
    Notification::assertNotSentTo($learner, AssignmentNotice::class, fn ($notice) => $notice->event() === 'assignment.submitted');
})->group('T-P9-05', 'A1', 'PRD-20');

it('notifies the certificate holder once when revoked and keeps the staff reason private', function (): void {
    [$course, $instructor, $learner] = assessmentNoticeCourse();
    $completion = CourseCompletion::query()->create([
        'course_id' => $course->id,
        'learner_id' => $learner->id,
        'source' => CourseCompletion::AUTO,
        'completed_at' => now(),
    ]);
    $certificate = app(IssueCertificate::class)($completion);
    Notification::fake();

    $reason = 'Staff records contain a correction';
    app(RevokeCertificate::class)($instructor, $certificate, $reason);
    expect(fn () => app(RevokeCertificate::class)($instructor, $certificate, $reason))->toThrow(ValidationException::class);

    $notices = Notification::sent($learner, CertificateRevokedNotice::class);
    expect($notices)->toHaveCount(1)
        ->and($notices->sole()->toArray($learner)['message'])->not->toContain($reason);
    Notification::assertNothingSentTo($instructor);
})->group('T-P9-05', 'A1', 'PRD-20');

it('dispatches due notices at 7, 3 and 1 day and respects opt-outs and handed-in work', function (): void {
    [$course, $instructor, $learner, $section, $item] = assessmentNoticeCourse();
    $optedOut = User::factory()->create()->assignRole('student');
    $submitted = User::factory()->create()->assignRole('student');
    foreach ([$optedOut, $submitted] as $recipient) {
        app(GrantEntitlement::class)($recipient, $course, EntitlementSource::Free);
    }
    NotificationOptOut::query()->create(['user_id' => $optedOut->id, 'event_key' => 'assignment.due']);
    $this->freezeTime();
    $item->itemable->update(['due_at' => now()->addDays(7)]);
    app(ManageSections::class)->setPublished($instructor, $section, true);
    app(SaveSubmissionDraft::class)($submitted, $item->fresh(), 'Complete work', []);
    app(SubmitAssignment::class)($submitted, $item->fresh());
    Notification::fake();

    $this->artisan('assignments:send-due-notices')->assertSuccessful();
    $this->artisan('assignments:send-due-notices')->assertSuccessful();
    $this->travel(4)->days();
    $this->artisan('assignments:send-due-notices')->assertSuccessful();
    $this->travel(2)->days();
    $this->artisan('assignments:send-due-notices')->assertSuccessful();
    $this->travel(1)->day();
    $this->artisan('assignments:send-due-notices')->assertSuccessful();

    $notices = Notification::sent($learner, AssignmentNotice::class, fn ($notice) => $notice->event() === 'assignment.due');
    expect($notices)->toHaveCount(3);
    Notification::assertNothingSentTo($optedOut);
    Notification::assertNothingSentTo($submitted);
})->group('T-P9-05', 'A1', 'A3', 'PRD-20');
