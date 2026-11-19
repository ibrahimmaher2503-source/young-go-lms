<?php

declare(strict_types=1);

// T-P9-05 | PRD 20, Checklist 11 | A1 A2

use App\Domain\Commerce\Actions\CreateOrder;
use App\Domain\Commerce\CoursePrice;
use App\Domain\Commerce\Events\ManualPaymentApproved;
use App\Domain\Commerce\Events\ManualPaymentProofSubmitted;
use App\Domain\Commerce\Events\PaymentVerified;
use App\Domain\Commerce\ManualPaymentProof;
use App\Domain\Commerce\Order;
use App\Domain\Commerce\OrderLine;
use App\Domain\Commerce\PaymentEvent;
use App\Domain\Commerce\ProductType;
use App\Domain\Curriculum\Actions\ManageSections;
use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Live\Actions\ScheduleLiveSession;
use App\Domain\Live\Notifications\LiveSessionNotice;
use App\Domain\Notifications\Actions\PublishAnnouncement;
use App\Domain\Notifications\Actions\SaveAnnouncement;
use App\Domain\Notifications\Actions\SaveNotificationTemplate;
use App\Domain\Notifications\Actions\UpdateNotificationPreferences;
use App\Domain\Notifications\Announcement;
use App\Domain\Notifications\GuardianCopyNotification;
use App\Domain\Notifications\Listeners\NotifyPayerOfPayment;
use App\Domain\Notifications\NotificationDispatch;
use App\Domain\Notifications\NotificationDispatcher;
use App\Domain\Notifications\NotificationEventDefinition;
use App\Domain\Notifications\NotificationMatrix;
use App\Domain\Notifications\NotificationOptOut;
use App\Domain\Notifications\Notifications\AnnouncementNotice;
use App\Domain\Notifications\Notifications\TemplatedNotice;
use App\Domain\Notifications\NotificationTemplate;
use App\Domain\Notifications\NotificationTemplates;
use App\Domain\Shared\Markets\Market;
use App\Models\Course;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    app()->setLocale('en');
    $this->travelTo(CarbonImmutable::parse('2027-03-10 10:00:00', 'UTC'));
});

function t905Admin(): User
{
    return User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('admin');
}

/** Holds system.settings.manage, which SaveNotificationTemplate requires (config/permissions.php grants it to super_admin only). */
function t905SuperAdmin(): User
{
    return User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('super_admin');
}

function t905Learner(string $locale = 'en', array $attributes = []): User
{
    return User::factory()->create(array_merge(['status' => 'active', 'locale' => $locale, 'timezone' => 'UTC'], $attributes))->assignRole('student');
}

function t905Course(string $suffix, string $access = 'paid'): Course
{
    return Course::query()->create([
        'title' => ['en' => 'T905 '.$suffix, 'ar' => 'T905 '.$suffix],
        'slug_ar' => 't905-ar-'.$suffix.'-'.uniqid(),
        'slug_en' => 't905-en-'.$suffix.'-'.uniqid(),
        'status' => 'published',
        'access_mode' => $access,
    ]);
}

function t905Instructor(Course $course): User
{
    $instructor = User::factory()->create(['status' => 'active', 'locale' => 'en'])->assignRole('instructor_academy');
    $course->instructors()->attach($instructor->getKey(), ['role' => 'instructor', 'scope' => Course::INSTRUCTOR_SCOPE_MANAGE]);

    return $instructor;
}

function t905Draft(User $actor, Course $course, array $overrides = []): Announcement
{
    return app(SaveAnnouncement::class)($actor, array_merge([
        'course_id' => $course->getKey(),
        'cohort_id' => null,
        'title_ar' => 'إعلان',
        'title_en' => 'Heads up',
        'body_ar' => 'نص الإعلان',
        'body_en' => 'Class moves to Monday',
    ], $overrides));
}

function t905SetTemplate(User $admin, string $event, array $data): NotificationTemplate
{
    return app(SaveNotificationTemplate::class)($admin, $event, $data);
}

/** A matrix-named notice whose text does not depend on any live session (used where a real LiveSession is not needed). */
function t905Notice(string $event, string $dedupe): TemplatedNotice
{
    return new TemplatedNotice($event, ['title' => 'Algebra', 'time' => 'Mon 18:00', 'course' => 'Algebra 1', 'message' => 'Hi'], $dedupe);
}

/** Creates a published live session in a course the learner can access; returns [course, live item]. */
function t905LiveSession(): array
{
    $admin = t905SuperAdmin();
    $course = t905Course('live', 'free');
    $section = app(ManageSections::class)->add($admin, $course, ['title' => ['en' => 'Week 1', 'ar' => 'الأسبوع الأول']]);
    $section->update(['published' => true]);
    $item = app(ScheduleLiveSession::class)($admin, $section, [
        'title' => ['en' => 'Kickoff', 'ar' => 'الانطلاق'], 'starts_at' => '2027-03-12 18:00', 'ends_at' => '2027-03-12 19:00', 'timezone' => 'UTC',
        'provider' => 'manual_zoom', 'meeting_url' => 'https://zoom.example.com/j/905', 'join_window_minutes' => 15, 'cohort_id' => null,
    ]);

    return [$course->refresh(), $item->refresh()];
}

// ---------------------------------------------------------------- A1 matrix

it('gives every matrix event with wording AR and EN text whose tokens are all allowed placeholders', function (): void {
    $checked = 0;
    foreach (NotificationMatrix::all() as $row) {
        if ($row->titleKey === null || $row->bodyKey === null) {
            continue;
        }
        foreach (['title' => $row->titleKey, 'body' => $row->bodyKey] as $part => $key) {
            foreach (['ar', 'en'] as $locale) {
                expect(Lang::has($key, $locale))->toBeTrue("{$row->key} {$part} {$locale}");
                $text = trans($key, [], $locale);
                expect(is_string($text) && trim($text) !== '')->toBeTrue("{$row->key} {$part} {$locale} is empty");
            }
            preg_match_all('/(?<![\p{L}\p{N}]):([a-z][a-z_]*)/u', (string) trans($key, [], 'en'), $found);
            expect(array_diff(array_unique($found[1]), $row->placeholders))->toBe([], "{$row->key} {$part} uses a token not in placeholders");
            $checked++;
        }
    }

    expect($checked)->toBeGreaterThan(40);
})->group('T-P9-05', 'A1');

it('lists every matrix event and group in both locales for the preferences form', function (): void {
    foreach (NotificationMatrix::all() as $row) {
        foreach (['ar', 'en'] as $locale) {
            expect(Lang::has('notification_events.events.'.$row->formKey(), $locale))->toBeTrue("{$row->key} {$locale}")
                ->and(Lang::has('notification_events.groups.'.$row->group, $locale))->toBeTrue("{$row->group} {$locale}");
        }
    }
})->group('T-P9-05', 'A1');

it('covers the PRD section 20 event keys and marks pending rows with a note', function (): void {
    $required = [
        'account.verify', 'account.password_reset', 'access.granted', 'access.expiring', 'access.revoked',
        'payment.pending', 'payment.review', 'payment.approved', 'payment.failed', 'payment.refunded',
        'live.scheduled', 'live.reminder', 'live.rescheduled', 'live.cancelled',
        'assignment.published', 'assignment.due', 'assignment.submitted', 'assignment.graded', 'assignment.revision',
        'quiz.result', 'certificate.issued', 'certificate.revoked', 'course.announcement',
        'help.created', 'help.reply', 'settlement.statement', 'settlement.status',
    ];
    $matrix = NotificationMatrix::all();

    foreach ($required as $key) {
        expect($matrix)->toHaveKey($key);
    }
    foreach ($matrix as $row) {
        if ($row->status === NotificationEventDefinition::PENDING) {
            expect($row->note)->not->toBeNull("{$row->key} is pending without a note");
        }
    }
    expect(NotificationMatrix::find('account.verify')?->editable())->toBeFalse()
        ->and(NotificationMatrix::find('account.password_reset')?->editable())->toBeFalse();
})->group('T-P9-05', 'A1');

// ---------------------------------------------------------------- A2 opt-outs

it('does not send an opted-out optional live reminder but still sends mandatory notices, and delivers again after opt-in', function (): void {
    [$course, $item] = t905LiveSession();
    $learner = t905Learner('en', ['email' => 'learner905@example.com']);
    $dispatcher = app(NotificationDispatcher::class);
    $session = $item->itemable;
    $reminder = fn (): LiveSessionNotice => new LiveSessionNotice($session, $course->getKey(), $item->getKey(), 'reminder', 'r1');

    Mail::fake();
    app(UpdateNotificationPreferences::class)($learner, ['live.reminder' => false]);
    expect(NotificationOptOut::query()->where('user_id', $learner->id)->where('event_key', 'live.reminder')->exists())->toBeTrue();

    expect($dispatcher->send($learner, $reminder()))->toBeFalse();
    expect(DatabaseNotification::query()->where('notifiable_id', $learner->id)->count())->toBe(0)
        ->and(NotificationDispatch::query()->where('recipient_id', $learner->id)->count())->toBe(0);
    Mail::assertNothingSent();

    // Mandatory notices still arrive, optional cancelled too.
    expect($dispatcher->send($learner, new LiveSessionNotice($session, $course->getKey(), $item->getKey(), 'cancelled', 'c1')))->toBeTrue()
        ->and($dispatcher->send($learner, t905Notice('certificate.issued', 'cert-905')))->toBeTrue();
    expect($learner->notifications()->count())->toBe(2);

    // Opting back in: the same dedupe key is still free.
    app(UpdateNotificationPreferences::class)($learner->fresh(), ['live.reminder' => true]);
    expect(NotificationOptOut::query()->where('user_id', $learner->id)->count())->toBe(0)
        ->and($dispatcher->send($learner->fresh(), $reminder()))->toBeTrue();
})->group('T-P9-05', 'A2');

it('refuses to opt out of a mandatory or unknown event and writes no opt-out row', function (): void {
    $learner = t905Learner();
    $update = app(UpdateNotificationPreferences::class);

    expect(fn () => $update($learner, ['payment.approved' => false]))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('enabled'));
    expect(fn () => $update($learner, ['made.up.event' => false]))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('enabled'));

    expect(NotificationOptOut::query()->where('user_id', $learner->id)->count())->toBe(0);
})->group('T-P9-05', 'A2');

// ---------------------------------------------------------------- templates

it('renders the default text without a row, with an inactive row, or with blank locale text', function (): void {
    $admin = t905SuperAdmin();
    $default = 'Default wording for :title';

    expect(NotificationTemplates::render('live.reminder', 'body', ['title' => 'X'], $default, 'en'))->toBe($default);

    t905SetTemplate($admin, 'live.reminder', ['body_en' => 'Custom :title', 'is_active' => false]);
    expect(NotificationTemplates::render('live.reminder', 'body', ['title' => 'X'], $default, 'en'))->toBe($default);

    t905SetTemplate($admin, 'live.reminder', ['body_en' => '   ', 'is_active' => true]);
    expect(NotificationTemplates::render('live.reminder', 'body', ['title' => 'X'], $default, 'en'))->toBe($default);
})->group('T-P9-05', 'A1');

it('renders the override for the requested locale with allowed tokens replaced, keeps other tokens literal and does not interpret values', function (): void {
    $admin = t905SuperAdmin();
    t905SetTemplate($admin, 'live.reminder', [
        'title_en' => 'Reminder: :title',
        'body_en' => 'At :time for :title',
        'body_ar' => 'في :time',
        'is_active' => true,
    ]);

    $values = ['title' => '<b>Algebra</b> {{ 1 }}', 'time' => 'Mon 18:00'];
    $body = NotificationTemplates::render('live.reminder', 'body', $values, 'default', 'en');

    expect($body)->toBe('At Mon 18:00 for <b>Algebra</b> {{ 1 }}');

    // A token outside the whitelist can only exist if a row was written around the validator: it must stay literal.
    NotificationTemplate::query()->where('event_key', 'live.reminder')->update(['body_en' => 'At :time, :secret']);
    expect(NotificationTemplates::render('live.reminder', 'body', $values, 'default', 'en'))->toBe('At Mon 18:00, :secret')
        ->and(NotificationTemplates::render('live.reminder', 'title', $values, 'default', 'en'))->toBe('Reminder: <b>Algebra</b> {{ 1 }}')
        ->and(NotificationTemplates::render('live.reminder', 'body', $values, 'default', 'ar'))->toBe('في Mon 18:00');
})->group('T-P9-05', 'A1');

it('refuses unknown tokens, markup and over-length text when saving a template', function (): void {
    $admin = t905SuperAdmin();

    expect(fn () => t905SetTemplate($admin, 'live.reminder', ['body_en' => 'Hi :secret']))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('body_en'));
    foreach (['<b>Hi</b>', '{{ 1 }}', '{!! 1 !!}', '@php echo 1 @endphp'] as $markup) {
        expect(fn () => t905SetTemplate($admin, 'live.reminder', ['title_en' => $markup]))
            ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('title_en'));
    }
    expect(fn () => t905SetTemplate($admin, 'live.reminder', ['title_en' => str_repeat('a', 161)]))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('title_en'))
        ->and(fn () => t905SetTemplate($admin, 'live.reminder', ['body_en' => str_repeat('a', 1001)]))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('body_en'));

    expect(NotificationTemplate::query()->where('event_key', 'live.reminder')->exists())->toBeFalse();
})->group('T-P9-05', 'A1');

// BUG: config/permissions.php 'admin' role grant list lacks 'system.settings.manage', which
// App\Domain\Notifications\Actions\SaveNotificationTemplate requires. The acceptance text says admin can edit templates, so an
// admin is refused (AuthorizationException) and the admin Filament edit action cannot save. Remove the skip once fixed.
it('lets the admin role save a notification template', function (): void {
    $admin = t905Admin();
    $saved = t905SetTemplate($admin, 'live.reminder', ['title_en' => 'Admin saved :title']);

    expect($saved->title_en)->toBe('Admin saved :title');
})->group('T-P9-05', 'A1');

it('restores the default when a template is saved blank and refuses non-admin and inactive actors', function (): void {
    $admin = t905SuperAdmin();
    t905SetTemplate($admin, 'live.reminder', ['title_en' => 'Custom :title', 'body_en' => 'Body :time']);

    $restored = t905SetTemplate($admin, 'live.reminder', ['title_en' => '', 'body_en' => null]);
    expect($restored->title_en)->toBeNull()->and($restored->body_en)->toBeNull();

    $learner = t905Learner();
    $instructor = User::factory()->create(['status' => 'active'])->assignRole('instructor_academy');
    $support = User::factory()->create(['status' => 'active'])->assignRole('support');
    $inactive = User::factory()->create(['status' => 'suspended'])->assignRole('admin');
    foreach ([$learner, $instructor, $support, $inactive] as $actor) {
        expect(fn () => t905SetTemplate($actor, 'live.reminder', ['title_en' => 'X']))->toThrow(AuthorizationException::class);
    }
})->group('T-P9-05', 'A1');

it('refuses to edit the framework account rows and writes one audit row with the editor', function (): void {
    $admin = t905SuperAdmin();

    expect(fn () => t905SetTemplate($admin, 'account.verify', ['title_en' => 'X']))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('event_key'));

    t905SetTemplate($admin, 'live.reminder', ['title_en' => 'Saved :title']);
    $template = NotificationTemplate::query()->where('event_key', 'live.reminder')->firstOrFail();
    expect((int) $template->updated_by)->toBe($admin->id);

    $rows = Activity::query()->where('event', 'notification_template.updated')->get();
    expect($rows)->toHaveCount(1);
})->group('T-P9-05', 'A1');

it('shows a saved template in a real templated notice and in the live session copy', function (): void {
    [$course, $item] = t905LiveSession();
    $admin = t905SuperAdmin();
    $learner = t905Learner('en');
    t905SetTemplate($admin, 'certificate.issued', ['title_en' => 'Certificate ready: :title']);
    t905SetTemplate($admin, 'live.cancelled', ['title_en' => 'Cancelled: :title']);

    app(NotificationDispatcher::class)->send($learner, t905Notice('certificate.issued', 'cert-tpl'));
    $learner2 = t905Learner('en');
    app(NotificationDispatcher::class)->send($learner2, new LiveSessionNotice($item->itemable, $course->getKey(), $item->getKey(), 'cancelled', 'x1'));

    expect($learner->notifications()->first()->data['title'])->toBe('Certificate ready: Algebra')
        ->and($learner2->notifications()->first()->data['title'])->toBe('Cancelled: Kickoff');
})->group('T-P9-05', 'A1');

it('keeps mail only for mandatory notices when the user switched email off', function (): void {
    $learner = t905Learner('en', ['notification_preferences' => json_encode(['email' => false]), 'email' => 'mailoff905@example.com']);

    $reminder = t905Notice('live.reminder', 'via-1');
    $cancelled = t905Notice('live.cancelled', 'via-2');

    expect($reminder->via($learner))->toBe(['database'])
        ->and($cancelled->via($learner))->toBe(['database', 'mail']);
})->group('T-P9-05', 'A1');

// ---------------------------------------------------------------- preferences page and PATCH

it('renders the preferences page in AR and EN, redirects guests and lists the optional switches', function (): void {
    $learner = t905Learner('ar');

    $this->get(route('profile.notifications.show'))->assertRedirect();

    $this->actingAs($learner)->get(route('profile.notifications.show'))
        ->assertOk()
        ->assertSee('live_reminder')
        ->assertSee('assignment_due')
        ->assertSee('course_announcement');

    app()->setLocale('en');
    $english = t905Learner('en');
    $this->actingAs($english)->get(route('profile.notifications.show'))->assertOk()->assertDontSee('notification_events.', false);
})->group('T-P9-05', 'A1');

it('toggles optional rows with one audit row per real change and rejects a mandatory key with 422', function (): void {
    $learner = t905Learner('en');
    $this->actingAs($learner);

    $this->patch(route('profile.notifications.update'), ['enabled' => ['live_reminder' => '0']])->assertNoContent();
    $this->patch(route('profile.notifications.update'), ['enabled' => ['live_reminder' => '0']])->assertNoContent();
    $this->patch(route('profile.notifications.update'), ['enabled' => ['live_reminder' => '1']])->assertNoContent();

    expect(Activity::query()->where('event', 'notifications.preference_changed')->where('subject_id', $learner->id)->count())->toBe(2)
        ->and(NotificationOptOut::query()->where('user_id', $learner->id)->count())->toBe(0);

    $this->patchJson(route('profile.notifications.update'), ['enabled' => ['payment_approved' => '0']])->assertUnprocessable();
    expect(NotificationOptOut::query()->where('user_id', $learner->id)->count())->toBe(0);
})->group('T-P9-05', 'A2');

it('gives a child account no phone switches and keeps SetPhoneReminderOptIn as the only writer of phone consent', function (): void {
    $parent = t905Learner('en', ['phone' => '+201099988877']);
    $child = t905Learner('en', ['phone' => '+201011122233', 'birth_year' => 2015]);
    GuardianChildLink::query()->create([
        'guardian_id' => $parent->id, 'child_id' => $child->id, 'status' => GuardianChildLink::STATUS_VERIFIED,
        'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT, 'verified_at' => now(),
    ]);
    config(['live.phone_reminders' => ['whatsapp' => 'fake', 'sms' => 'fake']]);

    $this->actingAs($child)->get(route('profile.notifications.show'))->assertOk()->assertDontSee('phone[whatsapp]', false);

    expect(fn () => app(UpdateNotificationPreferences::class)($child, [], ['whatsapp' => true]))
        ->toThrow(ValidationException::class);
    expect((array) $child->fresh()->getAttribute('phone_reminder_opt_ins'))->toBe([]);
})->group('T-P9-05', 'A2');

it('links the notification preferences from the profile page', function (): void {
    $learner = t905Learner('en');

    $this->actingAs($learner)->get(route('profile.page'))->assertOk()->assertSee(route('profile.notifications.show'), false);
})->group('T-P9-05', 'A1');

// ---------------------------------------------------------------- announcements

it('lets the instructor of the course save and publish, and refuses other instructors, learners and inactive staff', function (): void {
    $course = t905Course('auth');
    $other = t905Course('other');
    $instructor = t905Instructor($course);
    $stranger = t905Instructor($other);
    $learner = t905Learner();
    $inactive = User::factory()->create(['status' => 'suspended'])->assignRole('instructor_academy');
    $course->instructors()->attach($inactive->getKey(), ['role' => 'instructor', 'scope' => Course::INSTRUCTOR_SCOPE_MANAGE]);

    $announcement = t905Draft($instructor, $course);
    expect($announcement->status)->toBe(Announcement::DRAFT);

    app(PublishAnnouncement::class)($instructor, $announcement);
    expect($announcement->fresh()->isPublished())->toBeTrue();

    foreach ([$stranger, $learner, $inactive] as $actor) {
        expect(fn () => t905Draft($actor, $course))->toThrow(AuthorizationException::class);
    }
    $draft = t905Draft(t905Admin(), $course);
    expect(fn () => app(PublishAnnouncement::class)($stranger, $draft))->toThrow(AuthorizationException::class);
})->group('T-P9-05', 'A1');

it('lets an admin save and publish for any course', function (): void {
    $course = t905Course('admin');
    $admin = t905Admin();
    $announcement = t905Draft($admin, $course);

    app(PublishAnnouncement::class)($admin, $announcement);

    expect($announcement->fresh()->isPublished())->toBeTrue();
})->group('T-P9-05', 'A1');

it('notifies only active learners with current access, not suspended, unentitled or other-course learners', function (): void {
    $course = t905Course('aud');
    $other = t905Course('aud-other');
    $admin = t905Admin();
    $instructor = t905Instructor($course);

    $active = t905Learner('en');
    $suspended = t905Learner('en', ['status' => 'suspended']);
    $unentitled = t905Learner('en');
    $otherCourse = t905Learner('en');
    app(GrantEntitlement::class)($active, $course, EntitlementSource::Free);
    app(GrantEntitlement::class)($suspended, $course, EntitlementSource::Free);
    app(GrantEntitlement::class)($otherCourse, $other, EntitlementSource::Free);

    $announcement = t905Draft($instructor, $course);
    app(PublishAnnouncement::class)($admin, $announcement);

    expect($active->notifications()->where('type', AnnouncementNotice::class)->count())->toBe(1)
        ->and($suspended->notifications()->where('type', AnnouncementNotice::class)->count())->toBe(0)
        ->and($unentitled->notifications()->where('type', AnnouncementNotice::class)->count())->toBe(0)
        ->and($otherCourse->notifications()->where('type', AnnouncementNotice::class)->count())->toBe(0)
        ->and($announcement->fresh()->recipients_count)->toBe(1);
})->group('T-P9-05', 'A1');

it('sends a publish once, counts recipients, skips opted-out learners and writes the notice in the learner language', function (): void {
    $course = t905Course('once');
    $instructor = t905Instructor($course);
    $arabic = t905Learner('ar');
    $english = t905Learner('en');
    $optedOut = t905Learner('en');
    foreach ([$arabic, $english, $optedOut] as $learner) {
        app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);
    }
    app(UpdateNotificationPreferences::class)($optedOut, ['course.announcement' => false]);

    $announcement = t905Draft($instructor, $course);
    app(PublishAnnouncement::class)($instructor, $announcement);
    app(PublishAnnouncement::class)($instructor, $announcement->fresh());
    app(PublishAnnouncement::class)($instructor, $announcement->fresh());

    expect($announcement->fresh()->recipients_count)->toBe(2)
        ->and($arabic->notifications()->where('type', AnnouncementNotice::class)->count())->toBe(1)
        ->and($english->notifications()->where('type', AnnouncementNotice::class)->count())->toBe(1)
        ->and($optedOut->notifications()->where('type', AnnouncementNotice::class)->count())->toBe(0);

    $ar = $arabic->notifications()->where('type', AnnouncementNotice::class)->first();
    $en = $english->notifications()->where('type', AnnouncementNotice::class)->first();
    expect($ar->data['title'])->toBe('إعلان')
        ->and($ar->data['message'])->toBe('نص الإعلان')
        ->and($ar->data['url'])->toBe(route('learn.course', ['course' => $course->getKey()]))
        ->and($en->data['title'])->toBe('Heads up')
        ->and($en->data['message'])->toBe('Class moves to Monday');
})->group('T-P9-05', 'A1');

it('lets one failing recipient not stop the rest of the publish', function (): void {
    $course = t905Course('fail');
    $instructor = t905Instructor($course);
    $first = t905Learner('en');
    $broken = t905Learner('en');
    $last = t905Learner('en');
    foreach ([$first, $broken, $last] as $learner) {
        app(GrantEntitlement::class)($learner, $course, EntitlementSource::Free);
    }
    $brokenId = $broken->getKey();

    // NotificationSending fires per recipient before the channels run; throwing there makes that one send fail.
    Event::listen(NotificationSending::class, function (NotificationSending $sending) use ($brokenId): void {
        if ($sending->notifiable instanceof User && $sending->notifiable->getKey() === $brokenId) {
            throw new RuntimeException('mailbox unavailable');
        }
    });

    $announcement = t905Draft($instructor, $course);
    app(PublishAnnouncement::class)($instructor, $announcement);

    expect($announcement->fresh()->isPublished())->toBeTrue()
        ->and($announcement->fresh()->recipients_count)->toBe(2);
})->group('T-P9-05', 'A1');

it('sends a child without email an in-app notice and an email copy to each verified guardian', function (): void {
    Notification::fake();
    $course = t905Course('child');
    $instructor = t905Instructor($course);
    $guardian = t905Learner('en', ['email' => 'guardian905@example.com', 'birth_year' => 1980]);
    $child = t905Learner('en', ['email' => null, 'birth_year' => 2015]);
    app(GrantEntitlement::class)($child, $course, EntitlementSource::Free);
    GuardianChildLink::query()->create([
        'guardian_id' => $guardian->id, 'child_id' => $child->id, 'status' => GuardianChildLink::STATUS_VERIFIED,
        'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT, 'verified_at' => now(),
    ]);

    app(PublishAnnouncement::class)($instructor, t905Draft($instructor, $course));

    Notification::assertSentTo($child, AnnouncementNotice::class, function ($notification, $channels) {
        return $channels === ['database'];
    });
    Notification::assertSentTo($guardian, GuardianCopyNotification::class);
})->group('T-P9-05', 'A1');

it('refuses to edit a published announcement, a cohort from another course, and blank or over-long text', function (): void {
    $course = t905Course('frz');
    $other = t905Course('frz-other');
    $instructor = t905Instructor($course);
    $announcement = t905Draft($instructor, $course);

    expect(fn () => t905Draft($instructor, $course, ['title_en' => '']))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('title_en'));
    expect(fn () => t905Draft($instructor, $course, ['body_en' => str_repeat('x', 2001)]))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('body_en'));
    expect(fn () => t905Draft($instructor, $course, ['cohort_id' => 999999]))
        ->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('cohort_id'));

    app(PublishAnnouncement::class)($instructor, $announcement);
    expect(fn () => app(SaveAnnouncement::class)($instructor, [
        'course_id' => $course->getKey(), 'cohort_id' => null, 'title_ar' => 'x', 'title_en' => 'Changed', 'body_ar' => 'x', 'body_en' => 'Changed',
    ], $announcement->fresh()))->toThrow(fn (ValidationException $e) => expect($e->errors())->toHaveKey('announcement'));
})->group('T-P9-05', 'A1');

// ---------------------------------------------------------------- payer notices

it('creates one payment approved notice when verified and manually approved fire for the same order', function (): void {
    $payer = t905Learner('en');
    $course = t905Course('pay');
    CoursePrice::query()->create(['course_id' => $course->id, 'market' => 'EG', 'currency' => 'EGP', 'amount_minor' => 12550]);
    $order = app(CreateOrder::class)($payer, Market::Egypt, [new OrderLine(ProductType::Course, $course->getKey(), $payer)], 'p905-'.uniqid());

    $listener = app(NotifyPayerOfPayment::class);
    $listener->verified(new PaymentVerified($order, new PaymentEvent));
    $proof = new ManualPaymentProof;
    $proof->id = 905;
    $proof->setRelation('order', $order);
    $proof->setRelation('payer', $payer);
    $listener->manualApproved(new ManualPaymentApproved($proof));

    expect($payer->notifications()->where('type', 'like', '%TemplatedNotice')->count())->toBeGreaterThanOrEqual(1);
    expect(DatabaseNotification::query()->where('notifiable_id', $payer->id)->count())->toBe(1);
})->group('T-P9-05', 'A1');

it('creates a payment review notice when a manual proof is submitted', function (): void {
    $payer = t905Learner('en');
    $course = t905Course('review');
    CoursePrice::query()->create(['course_id' => $course->id, 'market' => 'EG', 'currency' => 'EGP', 'amount_minor' => 12550]);
    $order = app(CreateOrder::class)($payer, Market::Egypt, [new OrderLine(ProductType::Course, $course->getKey(), $payer)], 'p905r-'.uniqid());

    $proof = new ManualPaymentProof;
    $proof->id = 906;
    $proof->setRelation('order', $order);
    $proof->setRelation('payer', $payer);
    app(NotifyPayerOfPayment::class)->proofSubmitted(new ManualPaymentProofSubmitted($proof));

    expect(DatabaseNotification::query()->where('notifiable_id', $payer->id)->count())->toBe(1);
})->group('T-P9-05', 'A1');

it('never breaks a payment when a payer notice channel throws', function (): void {
    $payer = t905Learner('en');
    $proof = new ManualPaymentProof;
    $proof->id = 907;
    $proof->setRelation('order', new Order(['reference' => 'REF-907']));
    $proof->setRelation('payer', $payer);

    Event::listen(NotificationSending::class, function (): void {
        throw new RuntimeException('channel down');
    });

    expect(fn () => app(NotifyPayerOfPayment::class)->proofSubmitted(new ManualPaymentProofSubmitted($proof)))->not->toThrow(RuntimeException::class);
    expect(DatabaseNotification::query()->where('notifiable_id', $payer->id)->count())->toBe(0);
})->group('T-P9-05', 'A1');

// ---------------------------------------------------------------- lang, audit, arch

it('has audit labels in both locales and matching notification_events keys', function (): void {
    foreach (['notification_template.updated', 'notifications.preference_changed', 'announcement.created', 'announcement.updated', 'announcement.published'] as $event) {
        expect(trans('audit-events.'.$event, [], 'ar'))->not->toBe('audit-events.'.$event)
            ->and(trans('audit-events.'.$event, [], 'en'))->not->toBe('audit-events.'.$event);
    }

    $ar = require base_path('lang/ar/notification_events.php');
    $en = require base_path('lang/en/notification_events.php');
    expect(array_keys(Arr::dot($ar)))->toEqualCanonicalizing(array_keys(Arr::dot($en)));
})->group('T-P9-05', 'A1');

it('passes the translation and RTL gates for the notification templates and preferences', function (): void {
    $this->artisan('app:check-translations')->assertExitCode(0);
    $this->artisan('app:check-rtl')->assertExitCode(0);
})->group('T-P9-05', 'A1');

it('keeps the notifications domain free of HTTP and Filament imports and its new actions final', function (): void {
    foreach (['SaveNotificationTemplate', 'UpdateNotificationPreferences', 'SaveAnnouncement', 'PublishAnnouncement'] as $action) {
        expect((new ReflectionClass("App\\Domain\\Notifications\\Actions\\{$action}"))->isFinal())->toBeTrue();
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Domain/Notifications'), FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $fileInfo) {
        if ($fileInfo->getExtension() !== 'php') {
            continue;
        }
        $source = (string) file_get_contents($fileInfo->getPathname());
        expect($source)->not->toContain('Illuminate\\Http\\')
            ->and($source)->not->toContain('Filament\\');
    }
})->group('T-P9-05', 'A1');
