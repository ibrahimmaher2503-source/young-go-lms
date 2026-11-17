<?php

declare(strict_types=1);

// T-P5-01 | YG-GAP-COH-001..003, ED-013 | A1 capacity never exceeded, A2 waitlist order, A3 recorded courses need no cohorts

use App\Domain\Catalog\Actions\DeleteCourse;
use App\Domain\Cohorts\Actions\JoinWaitlist;
use App\Domain\Cohorts\Actions\ManageCohorts;
use App\Domain\Cohorts\Actions\PromoteFromWaitlist;
use App\Domain\Cohorts\Actions\RemoveFromCohort;
use App\Domain\Cohorts\Actions\ReserveCohortSeat;
use App\Domain\Cohorts\Cohort;
use App\Domain\Cohorts\CohortFullException;
use App\Domain\Cohorts\CohortMember;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Filament\Resources\Courses\Pages\ManageCourseCohorts;
use App\Models\Course;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/** @return array{0: Course, 1: User} */
function chCourse(string $format = 'live'): array
{
    $admin = User::factory()->create()->assignRole('admin');
    $course = Course::query()->create(['title' => ['en' => 'Live course', 'ar' => 'دورة مباشرة'], 'slug_ar' => 'c-'.uniqid(), 'slug_en' => 'c-'.uniqid(), 'status' => 'published', 'access_mode' => 'paid', 'format' => $format]);

    return [$course, $admin];
}

function chLearners(int $count): array
{
    return User::factory()->count($count)->create()->each->assignRole('student')->all();
}

it('never seats more learners than the capacity, whoever asks and however often', function (): void {
    [$course, $admin] = chCourse();
    $cohort = app(ManageCohorts::class)->create($admin, $course, ['name' => 'Spring', 'capacity' => 3]);
    $learners = chLearners(6);

    $seated = 0;
    $refused = 0;
    foreach ([...$learners, ...$learners] as $learner) {
        try {
            app(ReserveCohortSeat::class)($cohort, $learner);
            $seated++;
        } catch (CohortFullException) {
            $refused++;
        }
    }

    expect($cohort->refresh()->seatedCount())->toBe(3)
        ->and(CohortMember::query()->where('cohort_id', $cohort->id)->count())->toBe(3)
        ->and($refused)->toBeGreaterThanOrEqual(3);
});

it('is idempotent for a learner who already has the seat', function (): void {
    [$course, $admin] = chCourse();
    $cohort = app(ManageCohorts::class)->create($admin, $course, ['name' => 'Spring', 'capacity' => 2]);
    [$one] = chLearners(1);

    $first = app(ReserveCohortSeat::class)($cohort, $one);
    $again = app(ReserveCohortSeat::class)($cohort, $one);

    expect($again->id)->toBe($first->id)->and($cohort->refresh()->seatedCount())->toBe(1);
});

it('seats the learner who waited longest first and only when a seat is free', function (): void {
    [$course, $admin] = chCourse();
    $cohort = app(ManageCohorts::class)->create($admin, $course, ['name' => 'Spring', 'capacity' => 1]);
    [$holder, $first, $second, $third] = chLearners(4);
    app(ReserveCohortSeat::class)($cohort, $holder);

    $this->travelTo(now()->addMinute());
    app(JoinWaitlist::class)($cohort, $first);
    $this->travelTo(now()->addMinute());
    app(JoinWaitlist::class)($cohort, $second);
    $this->travelTo(now()->addMinute());
    app(JoinWaitlist::class)($cohort, $third);
    app(JoinWaitlist::class)($cohort, $first); // joining again keeps the first place

    expect(fn () => app(PromoteFromWaitlist::class)($admin, $cohort))->toThrow(CohortFullException::class);

    $holderRow = CohortMember::query()->where('learner_id', $holder->id)->firstOrFail();
    app(RemoveFromCohort::class)($admin, $holderRow);
    $promoted = app(PromoteFromWaitlist::class)($admin, $cohort);

    expect($promoted->learner_id)->toBe($first->id)->and($promoted->status)->toBe(CohortMember::SEATED)
        ->and(fn () => app(PromoteFromWaitlist::class)($admin, $cohort))->toThrow(CohortFullException::class);

    app(RemoveFromCohort::class)($admin, $promoted);

    expect(app(PromoteFromWaitlist::class)($admin, $cohort)->learner_id)->toBe($second->id);
});

it('keeps the waitlist for full cohorts and refuses it while seats are free', function (): void {
    [$course, $admin] = chCourse();
    $cohort = app(ManageCohorts::class)->create($admin, $course, ['name' => 'Spring', 'capacity' => 2]);
    [$one] = chLearners(1);

    expect(fn () => app(JoinWaitlist::class)($cohort, $one))->toThrow(ValidationException::class);
    expect(fn () => app(PromoteFromWaitlist::class)($admin, $cohort))->toThrow(ValidationException::class);
});

it('takes no seats or waitlist places once the cohort is closed', function (): void {
    [$course, $admin] = chCourse();
    $cohort = app(ManageCohorts::class)->create($admin, $course, ['name' => 'Spring', 'capacity' => 2]);
    app(ManageCohorts::class)->setStatus($admin, $cohort, Cohort::CLOSED);
    [$one] = chLearners(1);

    expect(fn () => app(ReserveCohortSeat::class)($cohort->refresh(), $one))->toThrow(ValidationException::class)
        ->and(fn () => app(JoinWaitlist::class)($cohort, $one))->toThrow(ValidationException::class);
});

it('lets a removed learner come back and keeps the history row', function (): void {
    [$course, $admin] = chCourse();
    $cohort = app(ManageCohorts::class)->create($admin, $course, ['name' => 'Spring', 'capacity' => 1]);
    [$one] = chLearners(1);
    $member = app(ReserveCohortSeat::class)($cohort, $one);
    app(RemoveFromCohort::class)($admin, $member);

    expect($member->refresh()->status)->toBe(CohortMember::REMOVED)->and($cohort->refresh()->seatedCount())->toBe(0);

    app(ReserveCohortSeat::class)($cohort, $one);
    expect(CohortMember::query()->count())->toBe(1)->and($cohort->refresh()->seatedCount())->toBe(1);
});

it('cannot lower the capacity under the seats already taken', function (): void {
    [$course, $admin] = chCourse();
    $cohort = app(ManageCohorts::class)->create($admin, $course, ['name' => 'Spring', 'capacity' => 3]);
    foreach (chLearners(2) as $learner) {
        app(ReserveCohortSeat::class)($cohort, $learner);
    }

    expect(fn () => app(ManageCohorts::class)->update($admin, $cohort, ['name' => 'Spring', 'capacity' => 1]))->toThrow(ValidationException::class);
    expect(app(ManageCohorts::class)->update($admin, $cohort, ['name' => 'Spring 2', 'capacity' => null])->capacity)->toBeNull();
});

it('lets recorded courses run without cohorts and refuses to create one for them', function (): void {
    [$course, $admin] = chCourse('recorded');
    [$learner] = chLearners(1);

    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);

    expect(Cohort::query()->count())->toBe(0)
        ->and(fn () => app(ManageCohorts::class)->create($admin, $course, ['name' => 'X', 'capacity' => 5]))->toThrow(ValidationException::class);
});

it('is run by staff of the course only, and instructors must belong to it', function (): void {
    [$course, $admin] = chCourse('hybrid');
    $cohort = app(ManageCohorts::class)->create($admin, $course, ['name' => 'Spring', 'capacity' => 5]);
    $outsider = User::factory()->create()->assignRole('instructor_academy');
    $learner = User::factory()->create()->assignRole('student');
    $inside = User::factory()->create()->assignRole('instructor_academy');
    $course->instructors()->attach($inside->id, ['role' => 'instructor', 'scope' => 'manage']);

    expect(fn () => app(ManageCohorts::class)->create($outsider, $course, ['name' => 'Nope']))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ManageCohorts::class)->create($learner, $course, ['name' => 'Nope']))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ManageCohorts::class)->assignInstructors($admin, $cohort, [$outsider->id]))->toThrow(ValidationException::class);

    app(ManageCohorts::class)->assignInstructors($admin, $cohort, [$inside->id]);
    expect($cohort->instructors()->pluck('users.id')->all())->toBe([$inside->id]);

    $member = app(ReserveCohortSeat::class)($cohort, $learner);
    expect(fn () => app(RemoveFromCohort::class)($outsider, $member))->toThrow(AuthorizationException::class);
    app(RemoveFromCohort::class)($inside, $member);
    expect($member->refresh()->status)->toBe(CohortMember::REMOVED);
});

it('blocks deleting a course that has cohort members', function (): void {
    [$course, $admin] = chCourse();
    $cohort = app(ManageCohorts::class)->create($admin, $course, ['name' => 'Spring', 'capacity' => 2]);
    expect(DeleteCourse::blockers($course))->toBe([]);

    app(ReserveCohortSeat::class)($cohort, chLearners(1)[0]);

    expect(DeleteCourse::blockers($course))->toContain('cohorts');
});

it('runs the cohort page: add, promote the next in line, remove, and keeps learners out', function (): void {
    [$course, $admin] = chCourse();
    $this->actingAs($admin);
    $page = Livewire::test(ManageCourseCohorts::class, ['record' => $course->getKey()]);

    $page->callAction('addCohort', ['name' => 'Evening group', 'capacity' => 1])->assertHasNoActionErrors();
    $cohort = Cohort::query()->firstOrFail();
    [$holder, $waiting] = chLearners(2);
    $member = app(ReserveCohortSeat::class)($cohort, $holder);
    app(JoinWaitlist::class)($cohort, $waiting);

    $page->call('$refresh')->assertSee('Evening group')->assertSee('data-member-row', false)->assertSee('data-promote', false)->assertSee('data-cohort-full', false);
    $page->call('promote', $cohort->id); // full: refused
    expect(CohortMember::query()->where('learner_id', $waiting->id)->value('status'))->toBe(CohortMember::WAITLISTED);

    $page->call('remove', $member->id)->call('promote', $cohort->id);
    expect(CohortMember::query()->where('learner_id', $waiting->id)->value('status'))->toBe(CohortMember::SEATED);

    $this->actingAs(User::factory()->create()->assignRole('student'));
    Livewire::test(ManageCourseCohorts::class, ['record' => $course->getKey()])->assertNotFound();
});
