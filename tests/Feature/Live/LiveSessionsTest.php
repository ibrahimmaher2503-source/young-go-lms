<?php

declare(strict_types=1);

// T-P5-02 | PRD §11, ED-032 | A1 instructor scope, A2 UTC storage and learner timezone display, lifecycle and AI hook

use App\Domain\Cohorts\Actions\ManageCohorts;
use App\Domain\Curriculum\Actions\ManageAssessmentItems;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Queries\CurriculumOutline;
use App\Domain\Live\Actions\CancelLiveSession;
use App\Domain\Live\Actions\CompleteLiveSession;
use App\Domain\Live\Actions\OpenLiveSession;
use App\Domain\Live\Actions\RescheduleLiveSession;
use App\Domain\Live\Actions\ScheduleLiveSession;
use App\Domain\Live\LiveSessionTime;
use App\Filament\Resources\Courses\Pages\ManageCurriculum;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\LiveSession;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/** @return array{0: Course, 1: User, 2: CourseSection} a live course with its managing instructor and one section */
function lsCourse(): array
{
    $instructor = User::factory()->create()->assignRole('instructor_academy');
    $course = Course::query()->create(['title' => ['en' => 'Live', 'ar' => 'مباشر'], 'slug_ar' => 'l-'.uniqid(), 'slug_en' => 'l-'.uniqid(), 'status' => 'published', 'access_mode' => 'paid', 'format' => 'live']);
    $course->instructors()->attach($instructor->id, ['role' => 'instructor', 'scope' => 'manage']);
    $section = app(ManageSections::class)->add($instructor, $course, ['title' => ['en' => 'Week 1', 'ar' => 'الأسبوع الأول']]);

    return [$course->refresh(), $instructor, $section];
}

/** @return array<string, mixed> */
function lsData(array $over = []): array
{
    return [
        'title' => ['en' => 'Kickoff', 'ar' => 'الانطلاق'], 'starts_at' => '2027-03-10 18:00', 'ends_at' => '2027-03-10 19:30', 'timezone' => 'Africa/Cairo',
        'provider' => 'manual_zoom', 'meeting_url' => 'https://zoom.example.com/j/123', 'join_window_minutes' => 10, ...$over,
    ];
}

it('lets an instructor schedule for an assigned course only', function (): void {
    [$course, $instructor, $section] = lsCourse();
    [, , $otherSection] = lsCourse();
    $learner = User::factory()->create()->assignRole('student');

    $item = app(ScheduleLiveSession::class)($instructor, $section, lsData());

    expect($item->itemable_type)->toBe('live_session')->and($item->status)->toBe(CurriculumItem::STATUS_DRAFT)
        ->and(fn () => app(ScheduleLiveSession::class)($instructor, $otherSection, lsData()))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ScheduleLiveSession::class)($learner, $section, lsData()))->toThrow(AuthorizationException::class);
})->group('T-P5-02', 'A1');

it('stores UTC and shows the learner their own timezone', function (): void {
    [, $instructor, $section] = lsCourse();
    $item = app(ScheduleLiveSession::class)($instructor, $section, lsData());
    $session = $item->itemable;

    // 18:00 in Cairo (UTC+2 in March 2027, before DST) is 16:00 UTC.
    expect($session->starts_at->utc()->format('Y-m-d H:i'))->toBe('2027-03-10 16:00')->and($session->timezone)->toBe('Africa/Cairo');

    $riyadh = User::factory()->create(['timezone' => 'Asia/Riyadh']);
    $newYork = User::factory()->create(['timezone' => 'America/New_York']);
    $default = User::factory()->create(); // profile timezone defaults to UTC

    expect(LiveSessionTime::startsAt($session, $riyadh)->format('H:i'))->toBe('19:00')
        ->and(LiveSessionTime::startsAt($session, $newYork)->format('H:i'))->toBe('11:00')
        ->and(LiveSessionTime::startsAt($session, $default)->format('H:i'))->toBe('16:00')
        ->and(LiveSessionTime::startsAt($session, null)->format('H:i'))->toBe('18:00'); // no viewer: the scheduler zone
})->group('T-P5-02', 'A2', 'ED-032');

it('rejects bad times, links, providers and foreign cohorts or instructors', function (): void {
    [$course, $instructor, $section] = lsCourse();
    [$other] = lsCourse();
    $admin = User::factory()->create()->assignRole('admin');
    $foreignCohort = app(ManageCohorts::class)->create($admin, $other, ['name' => 'Elsewhere', 'capacity' => 5]);
    $stranger = User::factory()->create()->assignRole('instructor_academy');

    foreach ([
        ['ends_at' => '2027-03-10 17:00'], ['starts_at' => 'not a date'], ['timezone' => 'Mars/Base'], ['provider' => 'skype'],
        ['meeting_url' => 'http://insecure.example.com/x'], ['meeting_url' => 'https://user:pw@zoom.example.com/x'], ['meeting_url' => 'javascript:alert(1)'],
        ['cohort_id' => $foreignCohort->id], ['instructor_id' => $stranger->id], ['title' => []],
    ] as $bad) {
        expect(fn () => app(ScheduleLiveSession::class)($instructor, $section, lsData($bad)))->toThrow(ValidationException::class);
    }
    expect(LiveSession::query()->count())->toBe(0);
});

it('keeps the meeting link encrypted and out of serialized data', function (): void {
    [, $instructor, $section] = lsCourse();
    $session = app(ScheduleLiveSession::class)($instructor, $section, lsData())->itemable;

    expect(DB::table('live_sessions')->value('meeting_url'))->not->toContain('zoom.example.com')
        ->and($session->refresh()->meeting_url)->toBe('https://zoom.example.com/j/123')
        ->and($session->toArray())->not->toHaveKey('meeting_url');
});

it('marks live items as not eligible for the assistant', function (): void {
    [, $instructor, $section] = lsCourse();
    $item = app(ScheduleLiveSession::class)($instructor, $section, lsData());

    expect($item->refresh()->ai_enabled)->toBeFalse();
})->group('T-P5-02', 'ED-032');

it('runs the lifecycle in order and audits each step', function (): void {
    [, $instructor, $section] = lsCourse();
    $session = app(ScheduleLiveSession::class)($instructor, $section, lsData())->itemable;

    expect(fn () => app(CompleteLiveSession::class)($instructor, $session))->toThrow(ValidationException::class);

    app(OpenLiveSession::class)($instructor, $session);
    expect($session->refresh()->status)->toBe(LiveSession::OPEN)
        ->and(fn () => app(RescheduleLiveSession::class)($instructor, $session, ['starts_at' => '2027-03-11 18:00', 'ends_at' => '2027-03-11 19:00', 'timezone' => 'Africa/Cairo']))->toThrow(ValidationException::class);

    app(CompleteLiveSession::class)($instructor, $session);
    expect($session->refresh()->status)->toBe(LiveSession::COMPLETED)
        ->and(fn () => app(CancelLiveSession::class)($instructor, $session))->toThrow(ValidationException::class);

    $events = Activity::query()->where('subject_type', $session->getMorphClass())->pluck('description')->all();
    expect($events)->toContain('live_session.scheduled', 'live_session.opened', 'live_session.completed');
});

it('reschedules a scheduled session in UTC and cancels it', function (): void {
    [, $instructor, $section] = lsCourse();
    $session = app(ScheduleLiveSession::class)($instructor, $section, lsData())->itemable;

    app(RescheduleLiveSession::class)($instructor, $session, ['starts_at' => '2027-03-12 20:00', 'ends_at' => '2027-03-12 21:00', 'timezone' => 'Asia/Riyadh']);
    expect($session->refresh()->starts_at->utc()->format('Y-m-d H:i'))->toBe('2027-03-12 17:00')->and($session->meeting_url)->toBe('https://zoom.example.com/j/123');

    app(CancelLiveSession::class)($instructor, $session);
    expect($session->refresh()->status)->toBe(LiveSession::CANCELLED);
});

it('will not open a session that has no meeting link yet', function (): void {
    [, $instructor, $section] = lsCourse();
    $session = app(ScheduleLiveSession::class)($instructor, $section, lsData(['meeting_url' => null]))->itemable;

    expect(fn () => app(OpenLiveSession::class)($instructor, $session))->toThrow(ValidationException::class);
});

it('can be archived from the curriculum like other items and stays out of student outlines while a draft', function (): void {
    [$course, $instructor, $section] = lsCourse();
    $item = app(ScheduleLiveSession::class)($instructor, $section, lsData());

    expect(CurriculumOutline::forManager($course)->flatMap->items)->toHaveCount(1)
        ->and(CurriculumOutline::forStudents($course)->flatMap->items)->toHaveCount(0);

    app(ManageAssessmentItems::class)->archive($instructor, $item);

    expect(CurriculumOutline::forManager($course)->flatMap->items)->toHaveCount(0);
});

it('schedules, reschedules, opens and cancels from the curriculum manager', function (): void {
    [$course, $instructor, $section] = lsCourse();
    $this->actingAs($instructor);
    $page = Livewire\Livewire::test(ManageCurriculum::class, ['record' => $course->getKey()]);

    $page->callAction('scheduleLiveSession', [
        'section_id' => $section->id, 'title' => ['en' => 'Workshop', 'ar' => 'ورشة'], 'timezone' => 'Africa/Cairo', 'starts_at' => '2027-04-01 18:00:00', 'ends_at' => '2027-04-01 19:00:00',
        'description' => ['en' => 'Bring a notebook', 'ar' => 'أحضر دفترًا'],
        'provider' => 'meet', 'meeting_url' => 'https://meet.example.com/abc', 'join_window_minutes' => 15,
    ])->assertHasNoActionErrors();

    $session = LiveSession::query()->firstOrFail();
    expect($session->starts_at->utc()->format('H:i'))->toBe('16:00')
        ->and($session->getTranslations('description'))->toMatchArray(['en' => 'Bring a notebook', 'ar' => 'أحضر دفترًا']);
    $page->assertSee('data-live-time', false)->assertSee('Workshop');

    $page->callAction('rescheduleLiveSession', ['timezone' => 'Africa/Cairo', 'starts_at' => '2027-04-02 18:00:00', 'ends_at' => '2027-04-02 19:00:00', 'meeting_url' => 'https://meet.example.com/abc'], arguments: ['session' => $session->id])->assertHasNoActionErrors()->assertNotified();
    expect($session->refresh()->starts_at->utc()->format('Y-m-d'))->toBe('2027-04-02');

    $page->call('advanceLiveSession', $session->id, 'open');
    expect($session->refresh()->status)->toBe(LiveSession::OPEN);

    $page->callAction('cancelLiveSession', arguments: ['session' => $session->id]);
    expect($session->refresh()->status)->toBe(LiveSession::CANCELLED);
});
