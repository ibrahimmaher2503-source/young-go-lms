<?php

declare(strict_types=1);

// T-P5-05 | PRD A11, ED-032 | A1 a join is a presence mark, A2 late after the configured threshold, A3 never overwrites a staff mark

use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
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
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2027-03-10 18:00:00', 'UTC'));
});

/** @return array{0: LiveSession, 1: User, 2: User} an open-by-action-free session at 18:00 UTC, a learner and the admin */
function rjBuild(): array
{
    $admin = User::factory()->create()->assignRole('admin');
    $course = Course::query()->create(['title' => ['en' => 'Joins', 'ar' => 'الدخول'], 'slug_ar' => 'rj-'.uniqid(), 'slug_en' => 'rj-'.uniqid(), 'status' => 'published', 'access_mode' => 'paid', 'format' => 'live']);
    $section = app(ManageSections::class)->add($admin, $course, ['title' => ['en' => 'Week 1', 'ar' => 'الأسبوع الأول']]);
    $session = app(ScheduleLiveSession::class)($admin, $section, [
        'title' => ['en' => 'Kickoff', 'ar' => 'الانطلاق'], 'starts_at' => '2027-03-10 18:00', 'ends_at' => '2027-03-10 19:00',
        'timezone' => 'UTC', 'provider' => 'manual_zoom', 'meeting_url' => 'https://zoom.example.com/j/rj',
    ])->itemable;
    $learner = User::factory()->create()->assignRole('student');
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);

    return [$session, $learner, $admin];
}

it('records a join as present at the start', function (): void {
    [$session, $learner] = rjBuild();

    app(RecordJoin::class)($session, $learner);

    $mark = LiveAttendance::query()->sole();
    expect($mark->status)->toBe(LiveAttendance::PRESENT)->and($mark->source)->toBe(LiveAttendance::SOURCE_JOIN)
        ->and($mark->marked_by)->toBeNull()->and($mark->marked_at->utc()->format('Y-m-d H:i'))->toBe('2027-03-10 18:00');
});

it('records a join as late only after the configured grace period', function (): void {
    [$session, $learner] = rjBuild();

    $this->travelTo(now()->addMinutes(10));
    app(RecordJoin::class)($session, $learner);
    expect(LiveAttendance::query()->sole()->status)->toBe(LiveAttendance::PRESENT);

    LiveAttendance::query()->delete();
    $this->travelTo(now()->addMinute()); // 18:11, one minute past the grace period
    app(RecordJoin::class)($session, $learner);
    expect(LiveAttendance::query()->sole()->status)->toBe(LiveAttendance::LATE);
});

it('uses the configured late threshold', function (): void {
    [$session, $learner] = rjBuild();
    config()->set('live.late_after_minutes', 0);

    app(RecordJoin::class)($session->refresh(), $learner);

    expect(LiveAttendance::query()->sole()->status)->toBe(LiveAttendance::PRESENT); // exactly at the start is never late

    LiveAttendance::query()->delete();
    $this->travelTo(now()->addSecond());
    app(RecordJoin::class)($session, $learner);
    expect(LiveAttendance::query()->sole()->status)->toBe(LiveAttendance::LATE);
});

it('does not overwrite a mark made or corrected by staff', function (): void {
    [$session, $learner, $admin] = rjBuild();
    app(OpenLiveSession::class)($admin, $session);
    $mark = app(MarkAttendance::class)($admin, $session->refresh(), $learner, LiveAttendance::ABSENT, 'Did not attend');

    $this->travelTo(now()->addMinutes(30));
    app(RecordJoin::class)($session, $learner);

    expect($mark->refresh()->status)->toBe(LiveAttendance::ABSENT)->and($mark->source)->toBe(LiveAttendance::SOURCE_MANUAL)
        ->and($mark->marked_by)->toBe($admin->getKey())->and($mark->note)->toBe('Did not attend');
});

it('keeps the first join mark and never downgrades present to late', function (): void {
    [$session, $learner] = rjBuild();
    app(RecordJoin::class)($session, $learner);

    $this->travelTo(now()->addHour());
    app(RecordJoin::class)($session, $learner);

    $mark = LiveAttendance::query()->sole();
    expect($mark->status)->toBe(LiveAttendance::PRESENT)->and($mark->marked_at->utc()->format('H:i'))->toBe('18:00');
});

it('records attendance via HTTP join endpoint inside the window and ignores refused joins', function (): void {
    [$session, $learner, $admin] = rjBuild();
    $item = $session->item()->firstOrFail();
    $item->section->update(['published' => true]);
    app(SetCurriculumStatus::class)($admin, $item->course, [$item->id], 'published');

    // Stranger gets 404 and creates no attendance row
    $stranger = User::factory()->create()->assignRole('student');
    $this->actingAs($stranger)
        ->post(route('learn.join', ['course' => $item->course_id, 'item' => $item->id]))
        ->assertNotFound();
    expect(LiveAttendance::query()->count())->toBe(0);

    // Entitled learner joins at start + 11m -> recorded as late
    $this->travelTo(now()->addMinutes(11));
    $this->actingAs($learner)
        ->post(route('learn.join', ['course' => $item->course_id, 'item' => $item->id]))
        ->assertRedirect('https://zoom.example.com/j/rj');

    expect(LiveAttendance::query()->sole()->status)->toBe(LiveAttendance::LATE);
})->group('T-P5-05', 'A1');
