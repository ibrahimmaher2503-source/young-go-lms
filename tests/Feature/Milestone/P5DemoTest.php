<?php

declare(strict_types=1);

use App\Domain\Cohorts\Actions\ManageCohorts;
use App\Domain\Cohorts\Actions\ReserveCohortSeat;
use App\Domain\Cohorts\Cohort;
use App\Domain\Cohorts\CohortAvailability;
use App\Domain\Cohorts\CohortFullException;
use App\Domain\Cohorts\CohortMember;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Live\Actions\CompleteLiveSession;
use App\Domain\Live\Actions\CorrectAttendance;
use App\Domain\Live\Actions\MarkAttendance;
use App\Domain\Live\Actions\OpenLiveSession;
use App\Domain\Live\Actions\PublishLiveReplay;
use App\Domain\Live\Actions\RescheduleLiveSession;
use App\Domain\Live\Actions\ScheduleLiveSession;
use App\Domain\Live\Actions\SendLiveReminders;
use App\Domain\Live\Notifications\LiveSessionNotice;
use App\Domain\Media\Actions\AttachLessonMedia;
use App\Models\Course;
use App\Models\LiveAttendance;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    // A unique fake disk per run, removed after each test, so concurrent runs never share a testing directory.
    config(['media.disk' => 'p5demo-'.uniqid()]);
    Storage::fake(config('media.disk'));
});

afterEach(function (): void {
    File::deleteDirectory(storage_path('framework/testing/disks/'.config('media.disk')));
});

/** Acts as one user: a fresh session per actor, the way a person switching accounts gets one. */
function p5As($test, User $user)
{
    $test->flushSession();
    app('auth')->forgetGuards();

    return $test->actingAs($user);
}

/**
 * T-P5-MG demo script, executed over HTTP and through the real actions:
 * a live course with a cohort of capacity 2 → two learners take the seats, the third is refused and waitlists over HTTP
 * → the session is scheduled in Cairo time (stored in UTC) → the reschedule notice goes out → the 60 minute reminder goes
 * out once → the join link is masked and opens only for an entitled, seated learner inside the join window → attendance is
 * recorded (join) and corrected (audited) → the session completes and its replay is attached and visible to the learner.
 */
it('runs the P5 demo script end to end', function (): void {
    $this->travelTo(CarbonImmutable::parse('2027-03-10 10:00:00', 'UTC'));
    Notification::fake();

    // Accounts: a staff admin, two seated learners, a waitlisted learner (entitled but no seat) and an unentitled learner.
    $admin = User::factory()->create()->assignRole('admin');
    // Learner one lives in Cairo, so the session is shown in Cairo time (accounts default to UTC).
    $seatedOne = User::factory()->create(['name' => 'P5 Learner One', 'timezone' => 'Africa/Cairo'])->assignRole('student');
    $seatedTwo = User::factory()->create(['name' => 'P5 Learner Two'])->assignRole('student');
    $waitlisted = User::factory()->create(['name' => 'P5 Waitlisted'])->assignRole('student');
    $unentitled = User::factory()->create(['name' => 'P5 Outsider'])->assignRole('student');

    // 1. A live course with a cohort of capacity 2 and an open enrolment window.
    $course = Course::query()->create([
        'title' => ['en' => 'P5 Live Course', 'ar' => 'دورة مباشرة تجريبية'], 'slug_ar' => 'p5-ar-'.uniqid(), 'slug_en' => 'p5-en-'.uniqid(),
        'status' => 'published', 'access_mode' => 'paid', 'format' => 'live',
    ]);
    $cohort = app(ManageCohorts::class)->create($admin, $course, ['name' => 'P5 spring cohort', 'capacity' => 2]);
    expect($cohort->capacity)->toBe(2)->and($cohort->status)->toBe(Cohort::OPEN);

    foreach ([$seatedOne, $seatedTwo, $waitlisted, $unentitled] as $learner) {
        if ($learner !== $unentitled) {
            app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);
        }
    }

    // 2. Enrol learners until the cohort is full, then the third learner is refused and waitlists from the course page.
    app(ReserveCohortSeat::class)($cohort, $seatedOne);
    app(ReserveCohortSeat::class)($cohort, $seatedTwo);
    expect(fn () => app(ReserveCohortSeat::class)($cohort, $waitlisted))->toThrow(CohortFullException::class);
    expect(CohortAvailability::stateFor($course, $waitlisted))->toMatchArray(['full' => true, 'seats_left' => 0]);

    p5As($this, $waitlisted)->post(route('catalog.waitlist', ['course' => $course->id]))
        ->assertRedirect()->assertSessionHas('waitlist_notice');
    expect(CohortMember::query()->where('cohort_id', $cohort->id)->where('learner_id', $waitlisted->id)->value('status'))->toBe(CohortMember::WAITLISTED);
    expect($cohort->refresh()->seatedCount())->toBe(2);

    // 3. Schedule the live session: 20:00 Cairo wall-clock is stored as 18:00 UTC, and the live item is never AI-eligible.
    $section = app(ManageSections::class)->add($admin, $course, ['title' => ['en' => 'Week 1', 'ar' => 'الأسبوع الأول']]);
    $section->update(['published' => true]);
    $item = app(ScheduleLiveSession::class)($admin, $section, [
        'title' => ['en' => 'Kickoff', 'ar' => 'الانطلاق'], 'starts_at' => '2027-03-10 20:00', 'ends_at' => '2027-03-10 21:00',
        'timezone' => 'Africa/Cairo', 'provider' => 'manual_zoom', 'meeting_url' => 'https://zoom.example.com/j/p5-secret-room',
        'join_window_minutes' => 15, 'cohort_id' => $cohort->id,
    ]);
    app(SetCurriculumStatus::class)($admin, $course, [$item->id], 'published');
    $session = $item->itemable;
    expect($session->timezone)->toBe('Africa/Cairo')
        ->and($session->getRawOriginal('starts_at'))->toBe('2027-03-10 18:00:00')
        ->and($item->ai_enabled)->toBeFalse();

    // 4. Reschedule to 21:00 Cairo (19:00 UTC): the seated learners are told, the waitlisted learner is not.
    app(RescheduleLiveSession::class)($admin, $session, ['starts_at' => '2027-03-10 21:00', 'ends_at' => '2027-03-10 22:00', 'timezone' => 'Africa/Cairo']);
    $session->refresh();
    expect($session->getRawOriginal('starts_at'))->toBe('2027-03-10 19:00:00');
    Notification::assertSentTo($seatedOne, LiveSessionNotice::class);
    Notification::assertSentTo($seatedTwo, LiveSessionNotice::class);
    Notification::assertNotSentTo($waitlisted, LiveSessionNotice::class);
    Notification::assertNotSentTo($unentitled, LiveSessionNotice::class);

    // 5. Reminders: at 18:30 UTC the one-hour reminder goes out once per seated learner; the one-day offset is skipped because the session was created later.
    $this->travelTo(CarbonImmutable::parse('2027-03-10 18:30:00', 'UTC'));
    expect(app(SendLiveReminders::class)())->toBe(2)
        ->and(app(SendLiveReminders::class)())->toBe(0);
    expect(collect(Notification::sent($seatedOne, LiveSessionNotice::class))->filter(fn (LiveSessionNotice $notice): bool => str_contains($notice->dedupeKey(), ':reminder:'))->count())->toBe(1);
    Notification::assertNotSentTo($waitlisted, LiveSessionNotice::class, fn (LiveSessionNotice $notice): bool => str_contains($notice->dedupeKey(), ':reminder:'));

    // 6. Join window: the session is opened for joining, and the link never appears in the page.
    app(OpenLiveSession::class)($admin, $session->refresh());

    p5As($this, $seatedOne)->get(route('learn.show', ['course' => $course->id, 'item' => $item->id]))
        ->assertOk()->assertSee('data-live-state="upcoming"', false)->assertSee('Africa/Cairo')->assertDontSee('p5-secret-room');
    p5As($this, $seatedOne)->post(route('learn.join', ['course' => $course->id, 'item' => $item->id]))
        ->assertRedirect(route('learn.show', ['course' => $course->id, 'item' => $item->id]))->assertSessionHas('join_error');
    p5As($this, $waitlisted)->post(route('learn.join', ['course' => $course->id, 'item' => $item->id]))->assertNotFound();

    $this->travelTo(CarbonImmutable::parse('2027-03-10 18:50:00', 'UTC'));
    p5As($this, $seatedOne)->get(route('learn.show', ['course' => $course->id, 'item' => $item->id]))
        ->assertOk()->assertSee('data-live-state="joinable"', false)->assertDontSee('p5-secret-room');
    p5As($this, $unentitled)->post(route('learn.join', ['course' => $course->id, 'item' => $item->id]))->assertNotFound();
    p5As($this, $seatedOne)->post(route('learn.join', ['course' => $course->id, 'item' => $item->id]))
        ->assertRedirect('https://zoom.example.com/j/p5-secret-room')
        ->assertHeader('Cache-Control', 'no-store, private');

    // Joining records attendance: seated learner one is present (the session started at 19:00 UTC).
    expect(LiveAttendance::query()->where('live_session_id', $session->id)->where('learner_id', $seatedOne->id)->first())
        ->status->toBe(LiveAttendance::PRESENT)
        ->source->toBe(LiveAttendance::SOURCE_JOIN);

    // 7. Attendance by staff: learner two never joined, so staff mark them absent and then correct it to excused with a reason.
    $this->travelTo(CarbonImmutable::parse('2027-03-10 19:20:00', 'UTC'));
    $absent = app(MarkAttendance::class)($admin, $session->refresh(), $seatedTwo, LiveAttendance::ABSENT, 'No show');
    $excused = app(CorrectAttendance::class)($admin, $absent, LiveAttendance::EXCUSED, 'Medical appointment');
    expect($excused->status)->toBe(LiveAttendance::EXCUSED)->and($excused->note)->toBe('Medical appointment');
    expect(Activity::query()->where('log_name', 'audit')->where('event', 'live_attendance.corrected')->exists())->toBeTrue();

    // 8. The session finishes and its replay becomes a recorded lesson in the same section, with media, published and visible.
    $this->travelTo(CarbonImmutable::parse('2027-03-10 20:05:00', 'UTC'));
    app(CompleteLiveSession::class)($admin, $session->refresh());
    $replay = app(PublishLiveReplay::class)($admin, $session->refresh());
    expect($session->refresh()->replay_item_id)->toBe($replay->id)
        ->and($replay->itemable_type)->toBe('lesson');

    Storage::disk(config('media.disk'))->put('media/p5-replay.mp4', 'v');
    app(AttachLessonMedia::class)($admin, $replay->itemable, ['provider' => 'signed_storage', 'path' => 'media/p5-replay.mp4', 'duration_seconds' => 3600]);
    app(SetCurriculumStatus::class)($admin, $course, [$replay->id], 'published');

    p5As($this, $seatedOne)->get(route('learn.show', ['course' => $course->id, 'item' => $replay->id]))
        ->assertOk()->assertSee($replay->itemable->getTranslation('title', 'en'))->assertSee('<video', false);
    p5As($this, $unentitled)->get(route('learn.show', ['course' => $course->id, 'item' => $replay->id]))->assertNotFound();
})->group('T-P5-MG', 'A1');
