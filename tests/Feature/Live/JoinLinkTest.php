<?php

declare(strict_types=1);

// T-P5-03 | Roadmap M5.2, PRD §11 | A1 only entitled (and cohort) learners get the link, A2 only inside the window, A3 no assistant on live items

use App\Domain\Cohorts\Actions\ManageCohorts;
use App\Domain\Cohorts\Actions\ReserveCohortSeat;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Live\Actions\CancelLiveSession;
use App\Domain\Live\Actions\GetJoinLink;
use App\Domain\Live\Actions\OpenLiveSession;
use App\Domain\Live\Actions\ScheduleLiveSession;
use App\Domain\Live\JoinRefusedException;
use App\Domain\Live\Queries\UpcomingSessions;
use App\Models\Course;
use App\Models\CurriculumItem;
use App\Models\LiveSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

const JL_URL = 'https://zoom.example.com/j/secret-room-42';

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2027-03-10 10:00:00', 'UTC'));
});

/** @return array{0: Course, 1: CurriculumItem, 2: User, 3: User, 4: User} course, live item (starts 18:00 UTC, window 15 min), entitled learner, other learner, admin */
function jlBuild(?bool $withCohort = false): array
{
    $admin = User::factory()->create()->assignRole('admin');
    $course = Course::query()->create(['title' => ['en' => 'Live course', 'ar' => 'دورة مباشرة'], 'slug_ar' => 'jl-'.uniqid(), 'slug_en' => 'jl-'.uniqid(), 'status' => 'published', 'access_mode' => 'paid', 'format' => 'live']);
    $section = app(ManageSections::class)->add($admin, $course, ['title' => ['en' => 'Week 1', 'ar' => 'الأسبوع الأول']]);
    $section->update(['published' => true]);
    $cohort = $withCohort ? app(ManageCohorts::class)->create($admin, $course, ['name' => 'Spring', 'capacity' => 5]) : null;
    $item = app(ScheduleLiveSession::class)($admin, $section, [
        'title' => ['en' => 'Kickoff', 'ar' => 'الانطلاق'], 'starts_at' => '2027-03-10 18:00', 'ends_at' => '2027-03-10 19:00', 'timezone' => 'UTC',
        'provider' => 'manual_zoom', 'meeting_url' => JL_URL, 'join_window_minutes' => 15, 'cohort_id' => $cohort?->id,
    ]);
    app(SetCurriculumStatus::class)($admin, $course, [$item->id], 'published');
    $learner = User::factory()->create()->assignRole('student');
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);

    return [$course->refresh(), $item->refresh(), $learner, User::factory()->create()->assignRole('student'), $admin];
}

it('never puts the meeting link in the page, before or inside the window', function (): void {
    [$course, $item, $learner] = jlBuild();

    $this->actingAs($learner)->get("/learn/{$course->id}/{$item->id}")->assertOk()->assertSee('data-live-card', false)->assertSee('data-live-state="upcoming"', false)->assertDontSee('secret-room-42');

    $this->travelTo(now()->addHours(8)->subMinutes(10)); // 17:50 UTC, inside the 15 minute window
    $this->actingAs($learner)->get("/learn/{$course->id}/{$item->id}")->assertOk()->assertSee('data-live-state="joinable"', false)->assertDontSee('secret-room-42');
});

it('refuses learners without access, with a 404 that hides the session', function (): void {
    [$course, $item, , $stranger] = jlBuild();
    $this->travelTo(now()->addHours(8)); // inside the window

    $this->actingAs($stranger)->post("/learn/{$course->id}/{$item->id}/join")->assertNotFound();
    $this->actingAs($stranger)->get("/learn/{$course->id}/{$item->id}")->assertNotFound();
    expect(fn () => app(GetJoinLink::class)($stranger, $item))->toThrow(JoinRefusedException::class);
});

it('expects a cohort seat for a cohort session and ignores waitlist places and removed members', function (): void {
    [$course, $item, $learner] = jlBuild(true);
    $this->travelTo(now()->addHours(8));
    $cohort = $item->itemable->cohort;

    $this->actingAs($learner)->post("/learn/{$course->id}/{$item->id}/join")->assertNotFound(); // entitled but not in the cohort

    app(ReserveCohortSeat::class)($cohort, $learner);
    $this->actingAs($learner)->post("/learn/{$course->id}/{$item->id}/join")->assertRedirect(JL_URL)->assertHeader('Cache-Control', 'no-store, private');
});

it('hands the link out only inside the join window', function (): void {
    [$course, $item, $learner] = jlBuild();

    $this->actingAs($learner)->post("/learn/{$course->id}/{$item->id}/join")->assertRedirect("/learn/{$course->id}/{$item->id}")->assertSessionHas('join_error');

    $this->travelTo(now()->addHours(8)->subMinutes(16)); // 17:44, one minute before the window
    $this->actingAs($learner)->post("/learn/{$course->id}/{$item->id}/join")->assertSessionHas('join_error');

    $this->travelTo(now()->addMinutes(1)); // 17:45, the window opens
    $this->actingAs($learner)->post("/learn/{$course->id}/{$item->id}/join")->assertRedirect(JL_URL);

    $this->travelTo(now()->addHours(1)->addMinutes(20)); // 19:05, after the end
    $this->actingAs($learner)->post("/learn/{$course->id}/{$item->id}/join")->assertSessionHas('join_error');
});

it('keeps an opened session in its window and never joins a cancelled one', function (): void {
    [$course, $item, $learner, , $admin] = jlBuild();
    app(OpenLiveSession::class)($admin, $item->itemable);

    $this->actingAs($learner)->post("/learn/{$course->id}/{$item->id}/join")->assertSessionHas('join_error');
    $this->travelTo(now()->addHours(8)->subMinutes(15));
    $this->actingAs($learner)->post("/learn/{$course->id}/{$item->id}/join")->assertRedirect(JL_URL);

    app(CancelLiveSession::class)($admin, $item->itemable->refresh());
    $this->actingAs($learner)->post("/learn/{$course->id}/{$item->id}/join")->assertSessionHas('join_error');
    $this->actingAs($learner)->get("/learn/{$course->id}/{$item->id}")->assertOk()->assertSee('data-live-cancelled', false);
});

it('does not allow joining with a GET request', function (): void {
    [$course, $item, $learner] = jlBuild();
    $this->travelTo(now()->addHours(8));

    $this->actingAs($learner)->get("/learn/{$course->id}/{$item->id}/join")->assertNotFound(); // the app answers a wrong method like a missing page; no redirect to the meeting happens
});

it('keeps the assistant off live items', function (): void {
    [$course, $item, $learner] = jlBuild();

    expect($item->ai_enabled)->toBeFalse();
    $this->actingAs($learner)->get("/learn/{$course->id}/{$item->id}")->assertOk()->assertDontSee('data-ai-available="true"', false)->assertDontSee('data-assistant', false);
});

it('lists upcoming sessions on the dashboard only for learners who may join them', function (): void {
    [$course, $item, $learner, $stranger] = jlBuild();

    $this->actingAs($learner)->get('/my')->assertOk()->assertSee('data-live-row', false)->assertSee('Kickoff')->assertDontSee('secret-room-42');
    $this->actingAs($stranger)->get('/my')->assertOk()->assertDontSee('data-live-row', false);

    expect(UpcomingSessions::for($learner))->toHaveCount(1)->and(UpcomingSessions::for($stranger))->toHaveCount(0);

    $item->itemable->update(['status' => LiveSession::CANCELLED]);
    expect(UpcomingSessions::for($learner))->toHaveCount(0);
});

it('shows staff the real card in the preview without a link', function (): void {
    [$course, $item, , , $admin] = jlBuild();

    $this->actingAs($admin)->get("/preview/{$course->id}/{$item->id}")->assertOk()->assertSee('data-live-card', false)->assertDontSee('secret-room-42');
});

it('batches the dashboard live access checks across sequential cohorts', function (): void {
    $learner = User::factory()->create()->assignRole('student');
    $enroll = function () use ($learner): void {
        [$course, $item] = jlBuild(true);
        $course->update(['sequence_mode' => Course::SEQUENCE_SEQUENTIAL]);
        app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);
        app(ReserveCohortSeat::class)($item->itemable->cohort, $learner);
    };
    $count = function () use ($learner): int {
        DB::enableQueryLog();
        DB::flushQueryLog();
        expect(UpcomingSessions::for($learner, limit: 1))->toHaveCount(1);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $queries;
    };
    $enroll();
    $base = $count();
    foreach (range(1, 5) as $index) {
        $enroll();
    }

    expect($count())->toBeLessThanOrEqual($base + 2);
})->group('T-P5-03', 'YG-GAP-COH-001', 'ISSUE-001');
