<?php

declare(strict_types=1);

// T-P5-01 | YG-GAP-COH-001..003, ED-013 | A1 capacity and unlimited seats, A2 waitlist order and returns, A3 staff scope and dates

use App\Domain\Cohorts\Actions\JoinWaitlist;
use App\Domain\Cohorts\Actions\ManageCohorts;
use App\Domain\Cohorts\Actions\PromoteFromWaitlist;
use App\Domain\Cohorts\Actions\RemoveFromCohort;
use App\Domain\Cohorts\Actions\ReserveCohortSeat;
use App\Domain\Cohorts\Cohort;
use App\Domain\Cohorts\CohortFullException;
use App\Domain\Cohorts\CohortHistory;
use App\Domain\Cohorts\CohortMember;
use App\Models\Course;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2027-03-10 10:00:00', 'UTC'));
});

/** @return array{0: Course, 1: User} */
function ceCourse(string $format = 'live'): array
{
    $admin = User::factory()->create()->assignRole('admin');
    $course = Course::query()->create([
        'title' => ['en' => 'Cohort edges', 'ar' => 'حدود المجموعة'], 'slug_ar' => 'ce-'.uniqid(), 'slug_en' => 'ce-'.uniqid(),
        'status' => 'published', 'access_mode' => 'paid', 'format' => $format,
    ]);

    return [$course, $admin];
}

/** @return list<User> */
function ceLearners(int $count): array
{
    return User::factory()->count($count)->create()->each->assignRole('student')->all();
}

/** @return array<string, list<string>> */
function ceErrors(callable $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

it('keeps an unlimited cohort open however many seats are taken', function (): void {
    [$course, $admin] = ceCourse();
    $cohort = app(ManageCohorts::class)->create($admin, $course, ['name' => 'Open', 'capacity' => null]);

    foreach (ceLearners(5) as $learner) {
        app(ReserveCohortSeat::class)($cohort, $learner);
    }

    expect($cohort->refresh()->seatedCount())->toBe(5)->and($cohort->seatsLeft())->toBeNull()
        ->and($cohort->isFull())->toBeFalse()->and($cohort->capacity)->toBeNull();
});

it('keeps the cohort dates when an edit does not mention them', function (): void {
    [$course, $admin] = ceCourse();
    $cohort = app(ManageCohorts::class)->create($admin, $course, ['name' => 'Dated', 'capacity' => 4, 'starts_on' => '2027-04-01', 'ends_on' => '2027-06-30', 'enrollment_opens_on' => '2027-03-01', 'enrollment_closes_on' => '2027-03-30']);

    app(ManageCohorts::class)->update($admin, $cohort, ['name' => 'Renamed', 'capacity' => 6]);

    expect($cohort->refresh()->starts_on?->toDateString())->toBe('2027-04-01')
        ->and($cohort->ends_on?->toDateString())->toBe('2027-06-30')
        ->and($cohort->enrollment_opens_on?->toDateString())->toBe('2027-03-01')
        ->and($cohort->enrollment_closes_on?->toDateString())->toBe('2027-03-30');
})->group('T-P5-01', 'A3');

it('never lowers capacity under the learners already seated', function (): void {
    [$course, $admin] = ceCourse();
    $cohort = app(ManageCohorts::class)->create($admin, $course, ['name' => 'Tight', 'capacity' => 3]);
    foreach (ceLearners(2) as $learner) {
        app(ReserveCohortSeat::class)($cohort, $learner);
    }

    expect(ceErrors(fn () => app(ManageCohorts::class)->update($admin, $cohort->refresh(), ['name' => 'Tight', 'capacity' => 1]))['capacity'][0] ?? null)
        ->toBe(__('cohorts.capacity_below_seated', ['count' => 2]))
        ->and(app(ManageCohorts::class)->update($admin, $cohort, ['name' => 'Tight', 'capacity' => 2])->capacity)->toBe(2);
});

it('validates the cohort dates and status values', function (): void {
    [$course, $admin] = ceCourse();
    $manage = app(ManageCohorts::class);
    $cohort = $manage->create($admin, $course, ['name' => 'Dates', 'starts_on' => '2027-05-01', 'ends_on' => '2027-05-31']);

    expect(fn () => $manage->create($admin, $course, ['name' => 'Backwards', 'starts_on' => '2027-06-01', 'ends_on' => '2027-05-01']))->toThrow(ValidationException::class)
        ->and(ceErrors(fn () => $manage->setStatus($admin, $cohort, 'paused'))['status'][0] ?? null)->toBe(__('cohorts.invalid_status'));

    $manage->setStatus($admin, $cohort, Cohort::CLOSED);
    expect($cohort->refresh()->status)->toBe(Cohort::CLOSED)
        ->and(Activity::query()->where('event', 'cohort.status_changed')->count())->toBe(1);
});

it('assigns only course instructors, deduplicated, and can clear the list', function (): void {
    [$course, $admin] = ceCourse('hybrid');
    $cohort = app(ManageCohorts::class)->create($admin, $course, ['name' => 'Led', 'capacity' => 5]);
    $inside = User::factory()->create()->assignRole('instructor_academy');
    $outsider = User::factory()->create()->assignRole('instructor_academy');
    $course->instructors()->attach($inside->id, ['role' => 'instructor', 'scope' => 'manage']);

    expect(ceErrors(fn () => app(ManageCohorts::class)->assignInstructors($admin, $cohort, [$inside->id, $outsider->id]))['instructors'][0] ?? null)
        ->toBe(__('cohorts.instructor_not_on_course'))
        ->and($cohort->instructors()->count())->toBe(0); // all or nothing

    app(ManageCohorts::class)->assignInstructors($admin, $cohort, [$inside->id, $inside->id]);
    expect($cohort->instructors()->pluck('users.id')->all())->toBe([$inside->id]);

    app(ManageCohorts::class)->assignInstructors($admin, $cohort, []);
    expect($cohort->instructors()->count())->toBe(0);
});

it('seats a waitlisted learner directly when a seat becomes free', function (): void {
    [$course, $admin] = ceCourse();
    $cohort = app(ManageCohorts::class)->create($admin, $course, ['name' => 'Moves', 'capacity' => 1]);
    [$holder, $waiting] = ceLearners(2);
    $seated = app(ReserveCohortSeat::class)($cohort, $holder);
    app(JoinWaitlist::class)($cohort, $waiting);
    app(RemoveFromCohort::class)($admin, $seated);

    $promoted = app(ReserveCohortSeat::class)($cohort->refresh(), $waiting);

    expect($promoted->status)->toBe(CohortMember::SEATED)->and($promoted->queued_at)->toBeNull()
        ->and($promoted->seated_at)->not->toBeNull()->and($cohort->refresh()->seatedCount())->toBe(1);
});

it('returns a removed learner to the waitlist of a full cohort with a fresh queue time', function (): void {
    [$course, $admin] = ceCourse();
    $cohort = app(ManageCohorts::class)->create($admin, $course, ['name' => 'Return', 'capacity' => 1]);
    [$holder, $learner] = ceLearners(2);
    app(ReserveCohortSeat::class)($cohort, $holder);
    $member = app(JoinWaitlist::class)($cohort, $learner);
    app(RemoveFromCohort::class)($admin, $member);

    $this->travelTo(now()->addDay());
    $again = app(JoinWaitlist::class)($cohort, $learner);

    expect($again->status)->toBe(CohortMember::WAITLISTED)->and($again->removed_at)->toBeNull()
        ->and($again->queued_at->toDateString())->toBe(now()->toDateString());
});

it('seats the longest waiter first and breaks ties by id', function (): void {
    [$course, $admin] = ceCourse();
    $cohort = app(ManageCohorts::class)->create($admin, $course, ['name' => 'Queue', 'capacity' => 1]);
    [$holder, $first, $second] = ceLearners(3);
    $seated = app(ReserveCohortSeat::class)($cohort, $holder);
    app(JoinWaitlist::class)($cohort, $first);
    app(JoinWaitlist::class)($cohort, $second); // same queued_at, higher id
    app(RemoveFromCohort::class)($admin, $seated);

    expect(app(PromoteFromWaitlist::class)($admin, $cohort)->learner_id)->toBe($first->id);
});

it('refuses to seat anyone from the waitlist of a closed cohort', function (): void {
    [$course, $admin] = ceCourse();
    $cohort = app(ManageCohorts::class)->create($admin, $course, ['name' => 'Closed', 'capacity' => 1]);
    [$holder, $waiting] = ceLearners(2);
    $seated = app(ReserveCohortSeat::class)($cohort, $holder);
    app(JoinWaitlist::class)($cohort, $waiting);
    app(RemoveFromCohort::class)($admin, $seated);
    app(ManageCohorts::class)->setStatus($admin, $cohort->refresh(), Cohort::CLOSED);

    expect(fn () => app(PromoteFromWaitlist::class)($admin, $cohort->refresh()))->toThrow(ValidationException::class);
})->group('T-P5-01', 'YG-GAP-COH-002', 'A2');

it('keeps a waitlisted learner in place when they ask for a seat in a full cohort', function (): void {
    [$course, $admin] = ceCourse();
    $cohort = app(ManageCohorts::class)->create($admin, $course, ['name' => 'Keep', 'capacity' => 1]);
    [$holder, $waiting] = ceLearners(2);
    app(ReserveCohortSeat::class)($cohort, $holder);
    $member = app(JoinWaitlist::class)($cohort, $waiting);

    expect(fn () => app(ReserveCohortSeat::class)($cohort, $waiting))->toThrow(CohortFullException::class)
        ->and($member->refresh()->status)->toBe(CohortMember::WAITLISTED)
        ->and(app(JoinWaitlist::class)($cohort, $waiting)->id)->toBe($member->getKey());
});

it('removes a learner once and logs one removal', function (): void {
    [$course, $admin] = ceCourse();
    $cohort = app(ManageCohorts::class)->create($admin, $course, ['name' => 'Removal', 'capacity' => 1]);
    [$learner] = ceLearners(1);
    $member = app(ReserveCohortSeat::class)($cohort, $learner);

    app(RemoveFromCohort::class)($admin, $member);
    app(RemoveFromCohort::class)($admin, $member->refresh());

    expect($member->refresh()->status)->toBe(CohortMember::REMOVED)->and($member->removed_at)->not->toBeNull()
        ->and(Activity::query()->where('event', 'cohort.member_removed')->count())->toBe(1);
});

it('counts a cohort as history from its first member, including waiting and removed learners', function (): void {
    [$course, $admin] = ceCourse();
    $cohort = app(ManageCohorts::class)->create($admin, $course, ['name' => 'History', 'capacity' => 1]);
    $history = app(CohortHistory::class);

    expect($history->blocks($course))->toBeNull();

    [$holder, $waiting] = ceLearners(2);
    $seated = app(ReserveCohortSeat::class)($cohort, $holder);
    expect($history->blocks($course))->toBe('cohorts');

    app(RemoveFromCohort::class)($admin, $seated);
    expect($history->blocks($course))->toBe('cohorts');

    app(ReserveCohortSeat::class)($cohort->refresh(), $holder); // free the seat once more for the waitlist check
    app(JoinWaitlist::class)($cohort->refresh(), $waiting);
    app(RemoveFromCohort::class)($admin, $cohort->members()->where('learner_id', $waiting->getKey())->sole());
    expect($history->blocks($course))->toBe('cohorts');
});

it('refuses cohort changes from an inactive administrator', function (): void {
    [$course, $admin] = ceCourse();
    $cohort = app(ManageCohorts::class)->create($admin, $course, ['name' => 'Inactive', 'capacity' => 2]);
    $admin->forceFill(['status' => 'suspended'])->save();

    expect(fn () => app(ManageCohorts::class)->create($admin, $course, ['name' => 'Nope']))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ManageCohorts::class)->setStatus($admin, $cohort, Cohort::CLOSED))->toThrow(AuthorizationException::class);
});

it('localizes cohort names and enforces the enrollment dates', function (): void {
    [$course, $admin] = ceCourse();
    [$learner, $waiting] = ceLearners(2);
    $this->travelTo(CarbonImmutable::parse('2027-03-09 12:00:00', 'UTC'));
    $cohort = app(ManageCohorts::class)->create($admin, $course, [
        'name' => 'Spring', 'name_translations' => ['ar' => 'الربيع'], 'capacity' => 1,
        'enrollment_opens_on' => '2027-03-10', 'enrollment_closes_on' => '2027-03-11',
    ]);

    expect($cohort->localizedName('ar'))->toBe('الربيع')
        ->and($cohort->localizedName('en'))->toBe('Spring')
        ->and(fn () => app(ReserveCohortSeat::class)($cohort, $learner))->toThrow(ValidationException::class);

    $this->travel(1)->days();
    $seat = app(ReserveCohortSeat::class)($cohort, $learner);
    $this->travel(1)->days();
    app(JoinWaitlist::class)($cohort, $waiting);

    $this->travel(1)->days();
    app(RemoveFromCohort::class)($admin, $seat);
    expect(fn () => app(PromoteFromWaitlist::class)($admin, $cohort))->toThrow(ValidationException::class)
        ->and(fn () => app(JoinWaitlist::class)($cohort, $waiting))->toThrow(ValidationException::class);
})->group('T-P5-01', 'YG-GAP-COH-001', 'YG-GAP-COH-002');
