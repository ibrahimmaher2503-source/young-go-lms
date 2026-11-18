<?php

declare(strict_types=1);

// T-P5-05 | PRD A11, ED-032 | A1 one mark per learner and audited corrections, A2 only the session audience, A3 staff only

use App\Domain\Cohorts\Actions\JoinWaitlist;
use App\Domain\Cohorts\Actions\ManageCohorts;
use App\Domain\Cohorts\Actions\RemoveFromCohort;
use App\Domain\Cohorts\Actions\ReserveCohortSeat;
use App\Domain\Cohorts\Cohort;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Live\Actions\CancelLiveSession;
use App\Domain\Live\Actions\CompleteLiveSession;
use App\Domain\Live\Actions\CorrectAttendance;
use App\Domain\Live\Actions\MarkAttendance;
use App\Domain\Live\Actions\OpenLiveSession;
use App\Domain\Live\Actions\RecordJoin;
use App\Domain\Live\Actions\ScheduleLiveSession;
use App\Models\Course;
use App\Models\LiveAttendance;
use App\Models\LiveSession;
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

/**
 * A live course with a scheduled session in the requested lifecycle state and one entitled learner.
 *
 * @return array{0: Course, 1: LiveSession, 2: User, 3: User, 4: Cohort|null}
 */
function attBuild(?bool $withCohort = false, string $status = LiveSession::OPEN): array
{
    $admin = User::factory()->create()->assignRole('admin');
    $course = Course::query()->create(['title' => ['en' => 'Attendance', 'ar' => 'الحضور'], 'slug_ar' => 'att-'.uniqid(), 'slug_en' => 'att-'.uniqid(), 'status' => 'published', 'access_mode' => 'paid', 'format' => 'live']);
    $section = app(ManageSections::class)->add($admin, $course, ['title' => ['en' => 'Week 1', 'ar' => 'الأسبوع الأول']]);
    $cohort = $withCohort ? app(ManageCohorts::class)->create($admin, $course, ['name' => 'Spring', 'capacity' => 1]) : null;
    $session = app(ScheduleLiveSession::class)($admin, $section, [
        'title' => ['en' => 'Kickoff', 'ar' => 'الانطلاق'], 'starts_at' => '2027-03-10 18:00', 'ends_at' => '2027-03-10 19:00',
        'timezone' => 'UTC', 'provider' => 'manual_zoom', 'meeting_url' => 'https://zoom.example.com/j/att', 'cohort_id' => $cohort?->id,
    ])->itemable;

    if ($status !== LiveSession::SCHEDULED) {
        app(OpenLiveSession::class)($admin, $session);
        if ($status === LiveSession::COMPLETED) {
            app(CompleteLiveSession::class)($admin, $session);
        }
        $session->refresh();
    }

    $learner = User::factory()->create()->assignRole('student');
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);

    return [$course->refresh(), $session, $admin, $learner, $cohort];
}

/** @return array<string, list<string>> */
function attErrors(callable $callback): array
{
    try {
        $callback();
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

it('marks an audience learner once, manually and with an audit entry', function (): void {
    [, $session, $admin, $learner] = attBuild();

    $mark = app(MarkAttendance::class)($admin, $session, $learner, LiveAttendance::PRESENT);

    expect($mark->status)->toBe(LiveAttendance::PRESENT)->and($mark->source)->toBe(LiveAttendance::SOURCE_MANUAL)
        ->and($mark->marked_by)->toBe($admin->getKey())->and($mark->note)->toBeNull()
        ->and(LiveAttendance::query()->count())->toBe(1);

    $activity = Activity::query()->where('event', 'live_attendance.marked')->sole();
    expect($activity->subject_id)->toBe($session->getKey())
        ->and($activity->properties['after']['learner_id'])->toBe($learner->getKey())
        ->and($activity->properties['after']['status'])->toBe(LiveAttendance::PRESENT);
});

it('refuses a second mark for the same learner', function (): void {
    [, $session, $admin, $learner] = attBuild();
    app(MarkAttendance::class)($admin, $session, $learner, LiveAttendance::PRESENT);

    $errors = attErrors(fn () => app(MarkAttendance::class)($admin, $session, $learner, LiveAttendance::LATE));

    expect($errors['learner'][0] ?? null)->toBe(__('live.attendance.already_marked'))
        ->and(LiveAttendance::query()->count())->toBe(1);
});

it('refuses unknown statuses and sessions that have not started', function (): void {
    [$course, $session, $admin, $learner] = attBuild(false, LiveSession::SCHEDULED);

    expect(attErrors(fn () => app(MarkAttendance::class)($admin, $session, $learner, 'attended'))['status'][0] ?? null)
        ->toBe(__('live.attendance.invalid_status'))
        ->and(attErrors(fn () => app(MarkAttendance::class)($admin, $session, $learner, LiveAttendance::PRESENT))['status'][0] ?? null)
        ->toBe(__('live.attendance.session_not_started'))
        ->and(LiveAttendance::query()->count())->toBe(0)
        ->and($course->id)->toBe($session->course_id);
});

it('lets attendance be taken on a finished session', function (): void {
    [, $session, $admin, $learner] = attBuild(false, LiveSession::COMPLETED);

    expect(app(MarkAttendance::class)($admin, $session, $learner, LiveAttendance::ABSENT)->status)->toBe(LiveAttendance::ABSENT);
});

it('refuses people outside the session audience', function (): void {
    [$course, $session, $admin] = attBuild();
    $stranger = User::factory()->create()->assignRole('student');

    $errors = attErrors(fn () => app(MarkAttendance::class)($admin, $session, $stranger, LiveAttendance::PRESENT));

    expect($errors['learner'][0] ?? null)->toBe(__('live.attendance.not_in_audience'))
        ->and($course->id)->toBe($session->course_id);
});

it('never lets a learner mark attendance themselves', function (): void {
    [, $session, , $learner] = attBuild();

    expect(fn () => app(MarkAttendance::class)($learner, $session, $learner, LiveAttendance::PRESENT))->toThrow(AuthorizationException::class)
        ->and(LiveAttendance::query()->count())->toBe(0);
});

it('counts only seated cohort members as the audience', function (): void {
    [$course, $session, $admin, $seated, $cohort] = attBuild(true);
    app(ReserveCohortSeat::class)($cohort, $seated);
    $waitlisted = User::factory()->create()->assignRole('student');
    app(GrantEntitlement::class)($waitlisted, $course, EntitlementSource::Free);
    app(JoinWaitlist::class)($cohort, $waitlisted);

    expect(attErrors(fn () => app(MarkAttendance::class)($admin, $session, $waitlisted, LiveAttendance::PRESENT))['learner'][0] ?? null)
        ->toBe(__('live.attendance.not_in_audience'))
        ->and(app(MarkAttendance::class)($admin, $session, $seated, LiveAttendance::PRESENT)->status)->toBe(LiveAttendance::PRESENT);
});

it('stops counting a learner once staff remove them from the cohort', function (): void {
    [$course, $session, $admin, $learner, $cohort] = attBuild(true);
    app(ReserveCohortSeat::class)($cohort, $learner);
    app(RemoveFromCohort::class)($admin, $cohort->members()->where('learner_id', $learner->getKey())->sole());

    expect(attErrors(fn () => app(MarkAttendance::class)($admin, $session, $learner, LiveAttendance::ABSENT))['learner'][0] ?? null)
        ->toBe(__('live.attendance.not_in_audience'));
});

it('trims and caps the note at 500 characters', function (): void {
    [, $session, $admin, $learner] = attBuild();

    $mark = app(MarkAttendance::class)($admin, $session, $learner, LiveAttendance::EXCUSED, '  '.str_repeat('x', 600).'  ');

    expect($mark->note)->toHaveLength(500)->and($mark->note)->toBe(str_repeat('x', 500));
});

it('requires a 3 to 500 character reason for corrections', function (): void {
    [, $session, $admin, $learner] = attBuild();
    $mark = app(MarkAttendance::class)($admin, $session, $learner, LiveAttendance::PRESENT);

    foreach (['  a  ', str_repeat('y', 501)] as $reason) {
        expect(attErrors(fn () => app(CorrectAttendance::class)($admin, $mark, LiveAttendance::LATE, $reason))['reason'][0] ?? null)
            ->toBe(__('live.attendance.reason_required'));
    }
});

it('corrects a mark with the earlier value in the audit trail', function (): void {
    [, $session, $admin, $learner] = attBuild();
    $mark = app(MarkAttendance::class)($admin, $session, $learner, LiveAttendance::PRESENT);

    $corrected = app(CorrectAttendance::class)($admin, $mark, LiveAttendance::EXCUSED, 'Medical appointment');

    expect($corrected->status)->toBe(LiveAttendance::EXCUSED)->and($corrected->marked_by)->toBe($admin->getKey())
        ->and($corrected->note)->toBe('Medical appointment');

    $activity = Activity::query()->where('event', 'live_attendance.corrected')->sole();
    expect($activity->properties['before']['status'])->toBe(LiveAttendance::PRESENT)
        ->and($activity->properties['after']['status'])->toBe(LiveAttendance::EXCUSED)
        ->and($activity->properties['after']['reason'])->toBe('Medical appointment');
})->group('T-P5-05', 'A1');

it('refuses a correction that changes nothing and one by a learner', function (): void {
    [, $session, $admin, $learner] = attBuild();
    $mark = app(MarkAttendance::class)($admin, $session, $learner, LiveAttendance::PRESENT);

    expect(attErrors(fn () => app(CorrectAttendance::class)($admin, $mark, LiveAttendance::PRESENT, 'No change here'))['status'][0] ?? null)
        ->toBe(__('live.attendance.no_change'))
        ->and(fn () => app(CorrectAttendance::class)($learner, $mark, LiveAttendance::LATE, 'Learner attempt'))->toThrow(AuthorizationException::class)
        ->and($mark->refresh()->status)->toBe(LiveAttendance::PRESENT);
});

it('forbids foreign instructors and parents from marking or correcting and refuses cancelled sessions', function (): void {
    [, $session, $admin, $learner] = attBuild();
    $foreignInstructor = User::factory()->create()->assignRole('instructor_academy');
    $parent = User::factory()->create()->assignRole('parent');

    expect(fn () => app(MarkAttendance::class)($foreignInstructor, $session, $learner, LiveAttendance::PRESENT))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(MarkAttendance::class)($parent, $session, $learner, LiveAttendance::PRESENT))
        ->toThrow(AuthorizationException::class);

    $mark = app(MarkAttendance::class)($admin, $session, $learner, LiveAttendance::ABSENT);
    expect(fn () => app(CorrectAttendance::class)($foreignInstructor, $mark, LiveAttendance::PRESENT, 'Foreign attempt'))
        ->toThrow(AuthorizationException::class)
        ->and(fn () => app(CorrectAttendance::class)($parent, $mark, LiveAttendance::PRESENT, 'Parent attempt'))
        ->toThrow(AuthorizationException::class);

    [, $cancelled, $cAdmin, $cLearner] = attBuild(false, LiveSession::SCHEDULED);
    app(CancelLiveSession::class)($cAdmin, $cancelled);
    expect(attErrors(fn () => app(MarkAttendance::class)($cAdmin, $cancelled->refresh(), $cLearner, LiveAttendance::PRESENT))['status'][0] ?? null)
        ->toBe(__('live.attendance.session_not_started'));
})->group('T-P5-05', 'A1');

it('corrects a join-sourced mark into manual and keeps multiple audit rows in order', function (): void {
    [, $session, $admin, $learner] = attBuild();
    app(RecordJoin::class)($session, $learner);
    $mark = LiveAttendance::query()->sole();
    expect($mark->source)->toBe(LiveAttendance::SOURCE_JOIN);

    $first = app(CorrectAttendance::class)($admin, $mark, LiveAttendance::LATE, 'Joined late due to audio check');
    expect($first->source)->toBe(LiveAttendance::SOURCE_MANUAL)
        ->and($first->status)->toBe(LiveAttendance::LATE);

    $second = app(CorrectAttendance::class)($admin, $first, LiveAttendance::EXCUSED, 'Instructor verified excused');
    expect($second->status)->toBe(LiveAttendance::EXCUSED);

    $audits = Activity::query()->where('event', 'live_attendance.corrected')->orderBy('id')->get();
    expect($audits)->toHaveCount(2)
        ->and($audits[0]->properties['before']['status'])->toBe(LiveAttendance::PRESENT)
        ->and($audits[0]->properties['after']['status'])->toBe(LiveAttendance::LATE)
        ->and($audits[1]->properties['before']['status'])->toBe(LiveAttendance::LATE)
        ->and($audits[1]->properties['after']['status'])->toBe(LiveAttendance::EXCUSED);
})->group('T-P5-05', 'A1');
