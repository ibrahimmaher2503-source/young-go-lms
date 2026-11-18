<?php

declare(strict_types=1);

// T-P5-04 | ED-017, YG-GAP-FAM-008, PRD A20 | A1 router keeps the learner/guardian split, A2 dedupe and locale, A3 reminders only to the right audience

use App\Domain\Cohorts\Actions\ManageCohorts;
use App\Domain\Cohorts\Actions\RemoveFromCohort;
use App\Domain\Cohorts\Actions\ReserveCohortSeat;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Curriculum\Actions\SetCurriculumStatus;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Live\Actions\OpenLiveSession;
use App\Domain\Live\Actions\ScheduleLiveSession;
use App\Domain\Live\Actions\SendLiveReminders;
use App\Domain\Live\LiveSessionNotices;
use App\Domain\Live\Notifications\LiveSessionNotice;
use App\Domain\Notifications\GuardianCopyNotification;
use App\Domain\Notifications\LearnerNotificationRouter;
use App\Domain\Notifications\LocalizedNotification;
use App\Domain\Notifications\NotificationDispatch;
use App\Domain\Notifications\NotificationDispatcher;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CurriculumItem;
use App\Models\LiveSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    $this->travelTo(CarbonImmutable::parse('2027-03-10 10:00:00', 'UTC'));
});

final class NrProbeNotification extends LocalizedNotification
{
    public function __construct(private readonly string $key = 'nr:probe') {}

    public function dedupeKey(): string
    {
        return $this->key;
    }

    protected function title(User $recipient): string
    {
        return 'Probe title';
    }

    protected function body(User $recipient): string
    {
        return 'Probe body';
    }
}

final class NrMailProbe extends Notification
{
    public function __construct(private readonly string $subject, private readonly bool $withAction = true) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)->subject($this->subject)->line('Inner line one');

        return $this->withAction ? $mail->action('Open secret', 'https://secret.example.com/token') : $mail;
    }
}

/** @return array{0: User, 1: User} a child without email and a verified, active guardian with a verified email */
function nrChildAndGuardian(): array
{
    $guardian = User::factory()->create(['locale' => 'ar'])->assignRole('parent');
    $child = User::factory()->create(['email' => null])->assignRole('student');
    GuardianChildLink::query()->create([
        'guardian_id' => $guardian->getKey(), 'child_id' => $child->getKey(), 'status' => GuardianChildLink::STATUS_VERIFIED,
        'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT, 'verified_at' => now(),
    ]);

    return [$child, $guardian];
}

/** @return array{0: Course, 1: CurriculumItem, 2: User, 3: User, 4: CourseSection} */
function nrLive(bool $withCohort = false): array
{
    $admin = User::factory()->create()->assignRole('admin');
    $course = Course::query()->create(['title' => ['en' => 'Notices', 'ar' => 'الإشعارات'], 'slug_ar' => 'nr-'.uniqid(), 'slug_en' => 'nr-'.uniqid(), 'status' => 'published', 'access_mode' => 'paid', 'format' => 'live']);
    $section = app(ManageSections::class)->add($admin, $course, ['title' => ['en' => 'Week 1', 'ar' => 'الأسبوع الأول']]);
    $section->update(['published' => true]);
    $cohort = $withCohort ? app(ManageCohorts::class)->create($admin, $course, ['name' => 'Spring', 'capacity' => 5]) : null;
    $item = app(ScheduleLiveSession::class)($admin, $section, [
        'title' => ['en' => 'Kickoff', 'ar' => 'الانطلاق'], 'starts_at' => '2027-03-12 18:00', 'ends_at' => '2027-03-12 19:00', 'timezone' => 'UTC',
        'provider' => 'manual_zoom', 'meeting_url' => 'https://zoom.example.com/j/nr', 'cohort_id' => $cohort?->id,
    ]);
    app(SetCurriculumStatus::class)($admin, $course, [$item->id], 'published');
    $learner = User::factory()->create(['locale' => 'en'])->assignRole('student');
    app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);

    return [$course->refresh(), $item->refresh(), $admin, $learner, $section];
}

it('sends a learner with an email their own notification and no guardian copy', function (): void {
    NotificationFacade::fake();
    [$child, $guardian] = nrChildAndGuardian();
    $child->forceFill(['email' => 'child@example.com'])->save();

    expect(app(LearnerNotificationRouter::class)->send($child, new NrProbeNotification))->toBe([]);

    NotificationFacade::assertSentTo($child, NrProbeNotification::class, fn (Notification $n, array $channels): bool => $channels === ['database', 'mail']);
    NotificationFacade::assertNotSentTo($guardian, GuardianCopyNotification::class);
});

it('gives a child without email an inbox copy and each eligible guardian a localized mail copy', function (): void {
    NotificationFacade::fake();
    [$child, $guardian] = nrChildAndGuardian();
    $second = User::factory()->create(['locale' => 'en'])->assignRole('parent');
    GuardianChildLink::query()->create([
        'guardian_id' => $second->getKey(), 'child_id' => $child->getKey(), 'status' => GuardianChildLink::STATUS_VERIFIED,
        'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT, 'verified_at' => now(),
    ]);

    $guardians = app(LearnerNotificationRouter::class)->send($child, new NrProbeNotification);

    expect($guardians)->toHaveCount(2);
    NotificationFacade::assertSentTo($child, NrProbeNotification::class, fn (Notification $n, array $channels): bool => $channels === ['database']);
    NotificationFacade::assertSentTo($guardian, GuardianCopyNotification::class, fn (GuardianCopyNotification $n, array $channels): bool => $channels === ['mail'] && $n->locale === 'ar' && $n->child->is($child));
    NotificationFacade::assertSentTo($second, GuardianCopyNotification::class, fn (GuardianCopyNotification $n, array $channels): bool => $channels === ['mail'] && $n->locale === 'en');
});

it('skips inactive, unverified, email-less guardians and unverified links', function (): void {
    NotificationFacade::fake();
    [$child, $good] = nrChildAndGuardian();
    $links = [
        User::factory()->create(['status' => 'suspended'])->assignRole('parent'),
        User::factory()->create(['email_verified_at' => null])->assignRole('parent'),
    ];
    $noEmail = User::factory()->create(['email' => null])->assignRole('parent');
    $noEmail->forceFill(['email_verified_at' => now()])->save();
    $pending = User::factory()->create()->assignRole('parent');

    foreach ([...$links, $noEmail] as $guardian) {
        GuardianChildLink::query()->create([
            'guardian_id' => $guardian->getKey(), 'child_id' => $child->getKey(), 'status' => GuardianChildLink::STATUS_VERIFIED,
            'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT, 'verified_at' => now(),
        ]);
    }
    GuardianChildLink::query()->create([
        'guardian_id' => $pending->getKey(), 'child_id' => $child->getKey(), 'status' => GuardianChildLink::STATUS_PENDING,
        'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT, 'verified_at' => null,
    ]);

    $guardians = app(LearnerNotificationRouter::class)->send($child, new NrProbeNotification);

    expect($guardians)->toHaveCount(1)->and($guardians[0]->is($good))->toBeTrue();
    foreach ([...$links, $noEmail, $pending] as $guardian) {
        NotificationFacade::assertNotSentTo($guardian, GuardianCopyNotification::class);
    }
});

it('renders a guardian copy in the guardian locale without the child action link', function (): void {
    [$child, $guardian] = nrChildAndGuardian();
    $child->forceFill(['display_name' => 'Omar'])->save();
    $original = new NrMailProbe('Lesson unlocked');
    $copy = (new GuardianCopyNotification($original, $child))->locale('ar');

    app()->setLocale('ar');
    $mail = $copy->toMail($guardian);
    app()->setLocale('en');

    expect($copy->locale)->toBe('ar')
        ->and($mail->subject)->toBe(__('notifications.guardian_copy_subject', ['child' => 'Omar', 'subject' => 'Lesson unlocked'], 'ar'))
        ->and($mail->introLines)->toContain('Inner line one')
        ->and(implode(' ', $mail->introLines))->toContain(__('notifications.guardian_copy_open_app', [], 'ar'))
        ->and((string) $mail->actionUrl)->not->toContain('secret.example.com');
});

it('falls back to the app name when the original has no usable mail subject', function (): void {
    [$child, $guardian] = nrChildAndGuardian();
    $plain = new class extends Notification {};

    $mail = (new GuardianCopyNotification($plain, $child))->toMail($guardian);

    expect($mail->subject)->toBe(__('notifications.guardian_copy_subject', ['child' => $child->name, 'subject' => config('app.name')]))
        ->and($mail->introLines)->toHaveCount(1);
});

it('returns false without notifying when the dispatch key already exists', function (): void {
    NotificationFacade::fake();
    $user = User::factory()->create(['locale' => 'en']);
    NotificationDispatch::query()->create(['dedupe_key' => 'nr:probe', 'recipient_id' => $user->getKey(), 'notification' => 'NrProbeNotification']);

    expect(app(NotificationDispatcher::class)->send($user, new NrProbeNotification))->toBeFalse();
    NotificationFacade::assertNothingSent();
});

it('records which notification class owned a sent dedupe key', function (): void {
    NotificationFacade::fake();
    $user = User::factory()->create(['locale' => 'en']);

    expect(app(NotificationDispatcher::class)->send($user, new NrProbeNotification('nr:class')))->toBeTrue()
        ->and(NotificationDispatch::query()->where('dedupe_key', 'nr:class')->value('notification'))->toBe('NrProbeNotification');
});

it('notifies only current learners and stops when the live item is not visible', function (): void {
    [$course, $item, , $active, $section] = nrLive();
    $expired = User::factory()->create(['locale' => 'en'])->assignRole('student');
    $future = User::factory()->create(['locale' => 'en'])->assignRole('student');
    app(GrantEntitlement::class)($expired, $course, EntitlementSource::Free, '', now()->subDays(3), now()->subDay());
    app(GrantEntitlement::class)($future, $course, EntitlementSource::Free, '', now()->addDay());
    $active->notifications()->delete();
    NotificationDispatch::query()->delete();
    $notices = app(LiveSessionNotices::class);

    expect($notices->send($item->itemable, LiveSessionNotice::REMINDER, 'probe'))->toBe(1)
        ->and($active->notifications()->count())->toBe(1)
        ->and($expired->notifications()->count())->toBe(0)
        ->and($future->notifications()->count())->toBe(0);

    $section->update(['published' => false]);
    expect($notices->send($item->itemable, LiveSessionNotice::CANCELLED))->toBe(0);
});

it('drops a learner removed from the session cohort from its audience', function (): void {
    [, $item, $admin, $learner] = nrLive(withCohort: true);
    $cohort = $item->itemable->cohort;
    $member = app(ReserveCohortSeat::class)($cohort, $learner);
    expect(app(LiveSessionNotices::class)->audience($item->itemable)->pluck('id')->all())->toBe([$learner->getKey()]);

    app(RemoveFromCohort::class)($admin, $member);

    expect(app(LiveSessionNotices::class)->audience($item->itemable)->pluck('id')->all())->toBe([]);
});

it('deduplicates reminder offsets and skips archived or started sessions', function (): void {
    [$course, $item, $admin, $learner] = nrLive();
    $learner->notifications()->delete();
    NotificationDispatch::query()->delete();
    config()->set('live.reminder_offsets_minutes', [60, 60, 0, -5]);
    $this->travelTo(CarbonImmutable::parse('2027-03-12 17:30:00', 'UTC'));

    expect(app(SendLiveReminders::class)())->toBe(1)->and(app(SendLiveReminders::class)())->toBe(0);

    $item->itemable->update(['archived_at' => now()]);
    expect(app(SendLiveReminders::class)())->toBe(0);

    $item->itemable->update(['archived_at' => null]);
    $this->travelTo(CarbonImmutable::parse('2027-03-12 18:00:00', 'UTC'));
    expect(app(SendLiveReminders::class)())->toBe(0);

    $item->itemable->update(['status' => LiveSession::OPEN]);
    expect(app(SendLiveReminders::class)())->toBe(0);
});

it('reminds an opened session that has not started yet', function (): void {
    [, $item, $admin, $learner] = nrLive();
    app(OpenLiveSession::class)($admin, $item->itemable);
    $learner->notifications()->delete();
    NotificationDispatch::query()->delete();
    config()->set('live.reminder_offsets_minutes', [60]);
    $this->travelTo(CarbonImmutable::parse('2027-03-12 17:30:00', 'UTC'));

    expect(app(SendLiveReminders::class)())->toBe(1)->and($learner->notifications()->count())->toBe(1);
});
