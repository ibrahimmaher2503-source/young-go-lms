<?php

// T-P7-04 | AI spec §12 §16, PRD §15.5 §16, D-019..D-021 | A1 A2 A3 A4

declare(strict_types=1);

use App\Domain\AI\Help\HelpLinks;
use App\Domain\AI\Help\HelpRefusedException;
use App\Domain\AI\Help\HelpRequestAccess;
use App\Domain\AI\Help\HelpRequestStatus;
use App\Domain\AI\Help\HelpRouting;
use App\Domain\AI\Help\ReplyBody;
use App\Models\Course;
use App\Models\HelpRequest;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function hpuCourse(string $title = 'Course'): Course
{
    return Course::query()->create([
        'title' => ['en' => $title, 'ar' => $title.' ar'],
        'slug_ar' => 'slug-ar-'.uniqid(),
        'slug_en' => 'slug-en-'.uniqid(),
        'status' => 'published',
    ]);
}

it('exposes correct HelpRequestStatus collections and predicate methods', function (): void {
    expect(HelpRequestStatus::open())->toBe([
        HelpRequestStatus::New,
        HelpRequestStatus::InProgress,
        HelpRequestStatus::InstructorReplied,
        HelpRequestStatus::WaitingForStudent,
    ]);

    expect(HelpRequestStatus::openValues())->toBe([
        'new',
        'in_progress',
        'instructor_replied',
        'waiting_for_student',
    ]);

    expect(HelpRequestStatus::awaitingValues())->toBe([
        'new',
        'in_progress',
    ]);

    expect(HelpRequestStatus::New->isOpen())->toBeTrue();
    expect(HelpRequestStatus::InProgress->isOpen())->toBeTrue();
    expect(HelpRequestStatus::InstructorReplied->isOpen())->toBeTrue();
    expect(HelpRequestStatus::WaitingForStudent->isOpen())->toBeTrue();
    expect(HelpRequestStatus::Resolved->isOpen())->toBeFalse();
    expect(HelpRequestStatus::Closed->isOpen())->toBeFalse();

    expect(HelpRequestStatus::New->awaitsInstructor())->toBeTrue();
    expect(HelpRequestStatus::InProgress->awaitsInstructor())->toBeTrue();
    expect(HelpRequestStatus::InstructorReplied->awaitsInstructor())->toBeFalse();
    expect(HelpRequestStatus::WaitingForStudent->awaitsInstructor())->toBeFalse();
    expect(HelpRequestStatus::Resolved->awaitsInstructor())->toBeFalse();
    expect(HelpRequestStatus::Closed->awaitsInstructor())->toBeFalse();

    expect(HelpRequestStatus::InstructorReplied->learnerMayReply())->toBeTrue();
    expect(HelpRequestStatus::WaitingForStudent->learnerMayReply())->toBeTrue();
    expect(HelpRequestStatus::New->learnerMayReply())->toBeFalse();
    expect(HelpRequestStatus::InProgress->learnerMayReply())->toBeFalse();
    expect(HelpRequestStatus::Resolved->learnerMayReply())->toBeFalse();
    expect(HelpRequestStatus::Closed->learnerMayReply())->toBeFalse();

    expect(HelpRequestStatus::New->label())->toBe(__('help.statuses.new'));
    expect(HelpRequestStatus::InProgress->label())->toBe(__('help.statuses.in_progress'));
    expect(HelpRequestStatus::Resolved->label())->toBe(__('help.statuses.resolved'));
    expect(HelpRequestStatus::Closed->label())->toBe(__('help.statuses.closed'));

    expect(HelpRequestStatus::New->tone())->toBe('warning');
    expect(HelpRequestStatus::InProgress->tone())->toBe('info');
    expect(HelpRequestStatus::InstructorReplied->tone())->toBe('primary');
    expect(HelpRequestStatus::WaitingForStudent->tone())->toBe('primary');
    expect(HelpRequestStatus::Resolved->tone())->toBe('success');
    expect(HelpRequestStatus::Closed->tone())->toBe('gray');
})->group('T-P7-04', 'unit');

it('routes help request to the first active manage-scope instructor with reply permission', function (): void {
    $course = hpuCourse('Routing Course');

    // Suspended instructor with manage scope
    $suspendedInstructor = User::factory()->create(['status' => 'suspended'])->assignRole('instructor_academy');
    $course->instructors()->attach($suspendedInstructor->id, ['role' => 'instructor', 'scope' => 'manage']);

    // View-scope instructor
    $viewInstructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $course->instructors()->attach($viewInstructor->id, ['role' => 'instructor', 'scope' => 'view']);

    // Instructor without help_request.reply permission
    $noReplyInstructor = User::factory()->create(['status' => 'active']);
    $course->instructors()->attach($noReplyInstructor->id, ['role' => 'instructor', 'scope' => 'manage']);

    // First eligible manage-scope instructor
    $firstEligible = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $course->instructors()->attach($firstEligible->id, ['role' => 'instructor', 'scope' => 'manage']);

    // Second eligible manage-scope instructor (higher course_instructors.id)
    $secondEligible = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $course->instructors()->attach($secondEligible->id, ['role' => 'instructor', 'scope' => 'manage']);

    $routed = HelpRouting::instructorFor($course);

    expect($routed)->not->toBeNull();
    expect($routed?->id)->toBe($firstEligible->id);
})->group('T-P7-04', 'unit');

it('returns null from HelpRouting::instructorFor when no manage-scope reply instructor exists', function (): void {
    $course = hpuCourse('No Instructor Course');

    $viewInstructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $course->instructors()->attach($viewInstructor->id, ['role' => 'instructor', 'scope' => 'view']);

    expect(HelpRouting::instructorFor($course))->toBeNull();
})->group('T-P7-04', 'unit');

it('identifies managers and responders via HelpRouting', function (): void {
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $superAdmin = User::factory()->create(['status' => 'active'])->assignRole('super_admin');
    $support = User::factory()->create(['status' => 'active'])->assignRole('support');
    $inactiveAdmin = User::factory()->create(['status' => 'inactive'])->assignRole('admin');

    $managers = HelpRouting::managers();
    $managerIds = $managers->pluck('id')->all();

    expect($managerIds)->toContain($admin->id);
    expect($managerIds)->toContain($superAdmin->id);
    expect($managerIds)->toContain($support->id);
    expect($managerIds)->not->toContain($inactiveAdmin->id);
})->group('T-P7-04', 'unit');

it('correctly scopes queries and authorizes viewers in HelpRequestAccess', function (): void {
    $admin = User::factory()->create(['status' => 'active'])->assignRole('admin');
    $instructor1 = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $instructor2 = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $student = User::factory()->create(['status' => 'active'])->assignRole('student');
    $suspendedInstructor = User::factory()->create(['status' => 'suspended'])->assignRole('instructor_academy');

    expect(HelpRequestAccess::isStaff($admin))->toBeTrue();
    expect(HelpRequestAccess::isStaff($instructor1))->toBeTrue();
    expect(HelpRequestAccess::isStaff($student))->toBeFalse();
    expect(HelpRequestAccess::isStaff($suspendedInstructor))->toBeFalse();

    expect(HelpRequestAccess::manages($admin))->toBeTrue();
    expect(HelpRequestAccess::manages($instructor1))->toBeFalse();

    $request1 = new HelpRequest;
    $request1->assigned_instructor_id = $instructor1->id;

    $requestUnassigned = new HelpRequest;
    $requestUnassigned->assigned_instructor_id = null;

    expect(HelpRequestAccess::allows($admin, $request1))->toBeTrue();
    expect(HelpRequestAccess::allows($admin, $requestUnassigned))->toBeTrue();
    expect(HelpRequestAccess::allows($instructor1, $request1))->toBeTrue();
    expect(HelpRequestAccess::allows($instructor2, $request1))->toBeFalse();
    expect(HelpRequestAccess::allows($instructor1, $requestUnassigned))->toBeFalse();
    expect(HelpRequestAccess::allows($student, $request1))->toBeFalse();
    expect(HelpRequestAccess::allows($suspendedInstructor, $request1))->toBeFalse();

    // authorize method
    HelpRequestAccess::authorize($admin, $request1);
    HelpRequestAccess::authorize($instructor1, $request1);

    expect(fn () => HelpRequestAccess::authorize($instructor2, $request1))
        ->toThrow(AuthorizationException::class);
    expect(fn () => HelpRequestAccess::authorize($student, $request1))
        ->toThrow(AuthorizationException::class);

    // scope method query check
    $adminQuery = HelpRequestAccess::scope(HelpRequest::query(), $admin);
    expect($adminQuery->toSql())->toBe(HelpRequest::query()->toSql());

    $instructorQuery = HelpRequestAccess::scope(HelpRequest::query(), $instructor1);
    expect($instructorQuery->toSql())->toContain('"assigned_instructor_id" = ?');

    $studentQuery = HelpRequestAccess::scope(HelpRequest::query(), $student);
    expect($studentQuery->toSql())->toContain('1 = 0');
})->group('T-P7-04', 'unit');

it('validates and cleans reply bodies with ReplyBody', function (): void {
    expect(ReplyBody::clean('   Hello world   '))->toBe('Hello world');

    expect(fn () => ReplyBody::clean('   '))
        ->toThrow(HelpRefusedException::class, __('help.refused.body_needed', [
            'max' => (int) config('ai.help.max_open_per_lesson', 3),
            'chars' => (int) config('ai.help.reply_max_chars', 2000),
        ]));

    $tooLong = str_repeat('a', 2001);
    expect(fn () => ReplyBody::clean($tooLong))
        ->toThrow(HelpRefusedException::class, __('help.refused.body_too_long', [
            'max' => (int) config('ai.help.max_open_per_lesson', 3),
            'chars' => (int) config('ai.help.reply_max_chars', 2000),
        ]));

    $exactLimit = str_repeat('a', 2000);
    expect(ReplyBody::clean($exactLimit))->toBe($exactLimit);
})->group('T-P7-04', 'unit');

it('generates correct URLs via HelpLinks for learner and staff', function (): void {
    expect(HelpLinks::learner(42))->toBe(route('help.show', ['helpRequest' => 42]));

    $admin = User::factory()->create()->assignRole('admin');
    $instructor = User::factory()->create()->assignRole('instructor_academy');

    expect(HelpLinks::staff($admin, 42))->toBe(url('/admin/help-requests/42'));
    expect(HelpLinks::staff($instructor, 42))->toBe(url('/instructor/help-requests/42'));
})->group('T-P7-04', 'unit');

it('calculates isOverdue on HelpRequest based on status and due_at', function (): void {
    $request = new HelpRequest;

    $request->status = HelpRequestStatus::New;
    $request->due_at = now()->subHour();
    expect($request->isOverdue())->toBeTrue();

    $request->status = HelpRequestStatus::InProgress;
    $request->due_at = now()->subHour();
    expect($request->isOverdue())->toBeTrue();

    $request->due_at = now()->addHour();
    expect($request->isOverdue())->toBeFalse();

    $request->due_at = null;
    expect($request->isOverdue())->toBeFalse();

    $request->status = HelpRequestStatus::InstructorReplied;
    $request->due_at = now()->subHour();
    expect($request->isOverdue())->toBeFalse();

    $request->status = HelpRequestStatus::Resolved;
    expect($request->isOverdue())->toBeFalse();

    $request->status = HelpRequestStatus::Closed;
    expect($request->isOverdue())->toBeFalse();
})->group('T-P7-04', 'unit');
