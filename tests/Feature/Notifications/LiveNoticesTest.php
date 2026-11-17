<?php

declare(strict_types=1);

// T-P5-04 | Roadmap M5.3, PRD §20 | A1 retries never duplicate, A2 each recipient gets their own locale

use App\Domain\Cohorts\Actions\ManageCohorts;
use App\Domain\Cohorts\Actions\ReserveCohortSeat;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Live\Actions\CancelLiveSession;
use App\Domain\Live\Actions\RescheduleLiveSession;
use App\Domain\Live\Actions\ScheduleLiveSession;
use App\Domain\Live\Actions\SendLiveReminders;
use App\Domain\Notifications\LocalizedNotification;
use App\Domain\Notifications\NotificationDispatch;
use App\Domain\Notifications\NotificationDispatcher;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2027-03-10 10:00:00', 'UTC'));
});

/** @return array{0: Course, 1: CurriculumItem, 2: User, 3: User, 4: User, 5: CourseSection} starts 2027-03-12 18:00 UTC; an Arabic and an English learner, both entitled */
function lnBuild(bool $publish = true, ?int $cohortId = null, bool $withCohort = false): array
{
    $admin = User::factory()->create()->assignRole('admin');
    $course = Course::query()->create(['title' => ['en' => 'Live', 'ar' => 'مباشر'], 'slug_ar' => 'ln-'.uniqid(), 'slug_en' => 'ln-'.uniqid(), 'status' => 'published', 'access_mode' => 'paid', 'format' => 'live']);
    $section = app(ManageSections::class)->add($admin, $course, ['title' => ['en' => 'Week 1', 'ar' => 'الأسبوع الأول']]);
    $section->update(['published' => true]);
    $cohort = $withCohort ? app(ManageCohorts::class)->create($admin, $course, ['name' => 'Spring', 'capacity' => 5]) : null;
    $item = app(ScheduleLiveSession::class)($admin, $section, [
        'title' => ['en' => 'Kickoff', 'ar' => 'الانطلاق'], 'starts_at' => '2027-03-12 18:00', 'ends_at' => '2027-03-12 19:00', 'timezone' => 'UTC',
        'provider' => 'manual_zoom', 'meeting_url' => 'https://zoom.example.com/j/1', 'join_window_minutes' => 15, 'cohort_id' => $cohort?->id,
    ]);
    $arabic = User::factory()->create(['locale' => 'ar', 'timezone' => 'Asia/Riyadh'])->assignRole('student');
    $english = User::factory()->create(['locale' => 'en', 'timezone' => 'America/New_York'])->assignRole('student');
    foreach ([$arabic, $english] as $learner) {
        app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);
    }
    if ($publish) {
        app(SetCurriculumStatus::class)($admin, $course, [$item->id], 'published');
    }

    return [$course->refresh(), $item->refresh(), $arabic, $english, $admin, $section];
}

it('announces a published live session once, in each recipient own language and timezone', function (): void {
    [, $item, $arabic, $english] = lnBuild();
    $session = $item->itemable;

    $ar = $arabic->notifications()->get();
    $en = $english->notifications()->get();

    expect($ar)->toHaveCount(1)->and($en)->toHaveCount(1)
        ->and($ar[0]->data['title'])->toBe(__('live.notice.scheduled.title', ['title' => 'الانطلاق'], 'ar'))
        ->and($en[0]->data['title'])->toBe(__('live.notice.scheduled.title', ['title' => 'Kickoff'], 'en'))
        ->and($ar[0]->data['message'])->toContain('21:00')->and($en[0]->data['message'])->toContain('1:00 PM')
        ->and($ar[0]->data['url'])->toContain("/learn/{$item->course_id}/{$item->id}")
        ->and($session->title)->not->toBe('');
});

it('does not announce again when the item is published a second time', function (): void {
    [$course, $item, $arabic, , $admin] = lnBuild();
    app(SetCurriculumStatus::class)($admin, $course, [$item->id], 'draft');
    app(SetCurriculumStatus::class)($admin, $course, [$item->id], 'published');

    expect($arabic->notifications()->count())->toBe(1);
});

it('says nothing about a draft session', function (): void {
    [, , $arabic, $english] = lnBuild(publish: false);

    expect($arabic->notifications()->count())->toBe(0)->and($english->notifications()->count())->toBe(0);
});

it('notifies a move and a cancellation, each once, and a second move separately', function (): void {
    [, $item, $arabic, , $admin] = lnBuild();
    $session = $item->itemable;

    app(RescheduleLiveSession::class)($admin, $session, ['starts_at' => '2027-03-13 18:00', 'ends_at' => '2027-03-13 19:00', 'timezone' => 'UTC']);
    app(RescheduleLiveSession::class)($admin, $session->refresh(), ['starts_at' => '2027-03-14 18:00', 'ends_at' => '2027-03-14 19:00', 'timezone' => 'UTC']);
    app(CancelLiveSession::class)($admin, $session->refresh());

    $types = $arabic->notifications()->get()->map(fn ($n) => $n->data['title'])->all();
    expect($types)->toHaveCount(4)
        ->and(collect($types)->filter(fn ($t) => str_contains($t, 'تغيّر موعد'))->count())->toBe(2)
        ->and(collect($types)->filter(fn ($t) => str_contains($t, 'أُلغيت'))->count())->toBe(1);
});

it('sends each configured reminder once even if the scheduler runs many times', function (): void {
    [, , $arabic, $english] = lnBuild();
    $arabic->notifications()->delete();
    $english->notifications()->delete();
    NotificationDispatch::query()->delete();

    expect(app(SendLiveReminders::class)())->toBe(0); // too early: more than a day away

    $this->travelTo(CarbonImmutable::parse('2027-03-11 18:00:00', 'UTC')); // exactly 24 hours before
    expect(app(SendLiveReminders::class)())->toBe(2)->and(app(SendLiveReminders::class)())->toBe(0);

    $this->travelTo(CarbonImmutable::parse('2027-03-12 17:00:00', 'UTC')); // one hour before
    expect(app(SendLiveReminders::class)())->toBe(2)->and(app(SendLiveReminders::class)())->toBe(0)
        ->and($arabic->notifications()->count())->toBe(2)->and($english->notifications()->count())->toBe(2);

    $this->artisan('live:send-reminders')->assertSuccessful();
    expect($arabic->notifications()->count())->toBe(2);
});

it('skips a reminder offset that had already passed when the session was created', function (): void {
    $this->travelTo(CarbonImmutable::parse('2027-03-12 17:30:00', 'UTC'));
    [, , $arabic] = lnBuild(); // created 30 minutes before the start: no 24 hour or 1 hour reminder makes sense
    $arabic->notifications()->delete();
    NotificationDispatch::query()->delete();

    expect(app(SendLiveReminders::class)())->toBe(0);
});

it('does not remind people outside the cohort or without current access, nor for a cancelled session', function (): void {
    [$course, $item, $arabic, $english, $admin] = lnBuild(withCohort: true);
    $cohort = $item->itemable->cohort;
    app(ReserveCohortSeat::class)($cohort, $arabic); // only the Arabic learner has a seat
    $outsider = User::factory()->create()->assignRole('student');
    NotificationDispatch::query()->delete();
    $arabic->notifications()->delete();

    $this->travelTo(CarbonImmutable::parse('2027-03-11 18:00:00', 'UTC'));
    app(SendLiveReminders::class)();

    expect($arabic->notifications()->count())->toBe(1)->and($english->notifications()->count())->toBe(0)->and($outsider->notifications()->count())->toBe(0);

    app(CancelLiveSession::class)($admin, $item->itemable->refresh());
    $this->travelTo(CarbonImmutable::parse('2027-03-12 17:00:00', 'UTC'));
    $before = $arabic->notifications()->count();
    app(SendLiveReminders::class)();
    expect($arabic->notifications()->count())->toBe($before);
});

it('sends one dedupe key to one recipient once, and lets a failed send be retried', function (): void {
    $user = User::factory()->create(['locale' => 'en']);
    $notice = new class extends LocalizedNotification
    {
        public static int $fail = 1;

        public function dedupeKey(): string
        {
            return 'test:retry';
        }

        protected function title(User $recipient): string
        {
            if (self::$fail-- > 0) {
                throw new RuntimeException('channel down');
            }

            return 'Hello';
        }

        protected function body(User $recipient): string
        {
            return 'Body';
        }
    };
    $dispatcher = app(NotificationDispatcher::class);

    expect(fn () => $dispatcher->send($user, $notice))->toThrow(RuntimeException::class)
        ->and(NotificationDispatch::query()->count())->toBe(0)
        ->and($dispatcher->send($user, $notice))->toBeTrue()
        ->and($dispatcher->send($user, $notice))->toBeFalse()
        ->and($user->notifications()->count())->toBe(1);

    $other = User::factory()->create();
    expect($dispatcher->send($other, $notice))->toBeTrue(); // same key, another person: still sent
});
