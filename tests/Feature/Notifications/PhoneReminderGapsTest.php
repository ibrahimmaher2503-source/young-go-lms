<?php

declare(strict_types=1);

// T-P9-04 | YG-GAP-NTF-001 O-021 O-015 | A1 gap closure: re-consent audit, active-account guard, delivery isolation

use App\Domain\Notifications\Actions\SendPhoneReminder;
use App\Domain\Notifications\Actions\SetPhoneReminderOptIn;
use App\Domain\Notifications\FakeNotificationChannel;
use App\Domain\Notifications\LocalizedNotification;
use App\Domain\Notifications\NotificationChannel;
use App\Domain\Notifications\NotificationDispatch;
use App\Domain\Notifications\NotificationDispatcher;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

/** Provider double that always fails, used to prove per-channel isolation. */
final class PrgThrowingChannel implements NotificationChannel
{
    public function send(string $channel, string $phone, string $locale, string $template, string $message, string $idempotencyKey): void
    {
        throw new RuntimeException('provider unavailable');
    }
}

/** Reminder notice whose id, dedupe key and phone-template behaviour the test controls. */
final class PrgNotice extends LocalizedNotification
{
    public function __construct(
        private readonly string $key,
        ?string $id = 'prg-notice-id',
        private readonly bool $throwOnPhoneTemplate = false,
    ) {
        $this->id = $id;
    }

    public function dedupeKey(): string
    {
        return $this->key;
    }

    public function phoneTemplate(): ?string
    {
        if ($this->throwOnPhoneTemplate) {
            throw new RuntimeException('phone template failed');
        }

        return 'live_session_reminder';
    }

    protected function title(User $recipient): string
    {
        return 'Session starting';
    }

    protected function body(User $recipient): string
    {
        return 'Painting starts in 1 hour';
    }
}

function prgAdult(array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'status' => 'active',
        'phone' => '+201012345678',
        'timezone' => 'Africa/Cairo',
        'locale' => 'en',
        'birth_year' => 1990,
        'birth_month' => 5,
    ], $attributes));
}

function prgFake(): FakeNotificationChannel
{
    return app(FakeNotificationChannel::class);
}

function prgOptInRows(User $user): Collection
{
    return Activity::query()
        ->where('event', 'notifications.phone_opt_in_changed')
        ->where('subject_id', $user->getKey())
        ->orderBy('id')
        ->get();
}

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    Config::set('live.phone_reminders', ['whatsapp' => null, 'sms' => null]);
    Config::set('phone_channels.providers', ['prg_throw' => PrgThrowingChannel::class]);
    Config::set('phone_channels.quiet_hours', ['start' => '21:00', 'end' => '08:00']);
    Config::set('ai.adult_age', 18);
    // 10:00 UTC is 12:00 in Africa/Cairo: outside quiet hours.
    $this->travelTo(CarbonImmutable::parse('2027-03-10 10:00:00', 'UTC'));
});

it('re-audits consent when the phone number changes and stays silent for an unchanged re-enable', function (): void {
    $user = prgAdult(['phone' => '+201000000001']);
    $optIn = app(SetPhoneReminderOptIn::class);

    $optIn($user, 'whatsapp', true);
    expect(prgOptInRows($user))->toHaveCount(1);

    $user->forceFill(['phone' => '+201000000002'])->save();
    $optIn($user->fresh(), 'whatsapp', true);

    expect((array) $user->fresh()->getAttribute('phone_reminder_opt_ins'))->toBe(['whatsapp' => '+201000000002']);

    $rows = prgOptInRows($user);
    expect($rows)->toHaveCount(2)
        ->and($rows[0]->properties['context']['reconsented'])->toBeFalse()
        ->and($rows[1]->properties['before']['enabled'])->toBeTrue()
        ->and($rows[1]->properties['after']['enabled'])->toBeTrue()
        ->and($rows[1]->properties['context']['reconsented'])->toBeTrue()
        ->and($rows[1]->properties['context']['channel'])->toBe('whatsapp')
        ->and(json_encode($rows->pluck('properties')->all()))->not->toContain('+201000000001')
        ->and(json_encode($rows->pluck('properties')->all()))->not->toContain('+201000000002');

    // Same number again: no new audit row.
    $optIn($user->fresh(), 'whatsapp', true);
    expect(prgOptInRows($user))->toHaveCount(2);
})->group('T-P9-04', 'A1', 'YG-GAP-NTF-001');

it('refuses new consent for a suspended account but still lets it withdraw consent', function (): void {
    $user = prgAdult();
    $optIn = app(SetPhoneReminderOptIn::class);
    $optIn($user, 'whatsapp', true);

    $user->forceFill(['status' => 'suspended'])->save();

    expect(fn () => $optIn($user->fresh(), 'whatsapp', true))
        ->toThrow(InvalidArgumentException::class, 'Phone reminder consent requires an active account.')
        ->and(fn () => $optIn($user->fresh(), 'sms', true))
        ->toThrow(InvalidArgumentException::class);

    expect((array) $user->fresh()->getAttribute('phone_reminder_opt_ins'))->toBe(['whatsapp' => '+201012345678']);

    $optIn($user->fresh(), 'whatsapp', false);

    expect((array) $user->fresh()->getAttribute('phone_reminder_opt_ins'))->toBe([])
        ->and(prgOptInRows($user))->toHaveCount(2)
        ->and(prgOptInRows($user)->last()->properties['after']['enabled'])->toBeFalse();
})->group('T-P9-04', 'A1', 'YG-GAP-NTF-001');

it('skips an opted-in learner whose account is suspended and logs the reason without the phone', function (): void {
    Config::set('live.phone_reminders', ['whatsapp' => 'fake', 'sms' => null]);
    $user = prgAdult();
    app(SetPhoneReminderOptIn::class)($user, 'whatsapp', true);
    $user->forceFill(['status' => 'suspended'])->save();

    Log::spy();
    app(SendPhoneReminder::class)($user->fresh(), new PrgNotice('prg-inactive'));

    Log::shouldHaveReceived('info')
        ->with('phone_reminder.skipped', ['reason' => 'inactive', 'template' => 'live_session_reminder'])
        ->once();
    expect(prgFake()->messages)->toBe([]);
})->group('T-P9-04', 'A1', 'YG-GAP-NTF-001');

it('uses the dedupe key as the idempotency prefix when the notice has no id', function (): void {
    Config::set('live.phone_reminders', ['whatsapp' => 'fake', 'sms' => null]);
    $user = prgAdult();
    app(SetPhoneReminderOptIn::class)($user, 'whatsapp', true);

    $notice = new PrgNotice('prg-fallback-key', id: null);
    app(SendPhoneReminder::class)($user->fresh(), $notice);
    app(SendPhoneReminder::class)($user->fresh(), $notice);

    $keys = array_keys(prgFake()->messages);
    expect($keys)->toBe(['prg-fallback-key:whatsapp'])
        ->and($keys[0])->not->toStartWith(':');
})->group('T-P9-04', 'A1', 'YG-GAP-NTF-001');

it('still sends SMS when the WhatsApp driver throws, and reports the failure', function (): void {
    Config::set('live.phone_reminders', ['whatsapp' => 'prg_throw', 'sms' => 'fake']);
    $user = prgAdult();
    $optIn = app(SetPhoneReminderOptIn::class);
    $optIn($user, 'whatsapp', true);
    $optIn($user->fresh(), 'sms', true);

    Exceptions::fake();
    app(SendPhoneReminder::class)($user->fresh(), new PrgNotice('prg-isolate', id: 'prg-isolate'));

    expect(array_keys(prgFake()->messages))->toBe(['prg-isolate:sms'])
        ->and(prgFake()->messages['prg-isolate:sms']['channel'])->toBe('sms');
    Exceptions::assertReported(RuntimeException::class);
})->group('T-P9-04', 'A1', 'YG-GAP-NTF-001');

it('keeps the dispatched notice and does not duplicate email when the phone driver fails', function (): void {
    Notification::fake();
    Config::set('live.phone_reminders', ['whatsapp' => 'prg_throw', 'sms' => null]);
    $user = prgAdult(['email' => 'prg-learner@example.test']);
    app(SetPhoneReminderOptIn::class)($user, 'whatsapp', true);

    $dispatcher = app(NotificationDispatcher::class);
    $notice = new PrgNotice('prg-dispatch-driver');

    expect($dispatcher->send($user->fresh(), $notice))->toBeTrue()
        ->and(NotificationDispatch::query()->count())->toBe(1);
    Notification::assertSentToTimes($user, PrgNotice::class, 1);

    expect($dispatcher->send($user->fresh(), $notice))->toBeFalse();
    Notification::assertSentToTimes($user, PrgNotice::class, 1);
    expect(NotificationDispatch::query()->count())->toBe(1);
})->group('T-P9-04', 'A1', 'YG-GAP-NTF-001');

it('keeps the dispatched notice and does not duplicate email when SendPhoneReminder itself throws', function (): void {
    Notification::fake();
    Exceptions::fake();
    Config::set('live.phone_reminders', ['whatsapp' => 'fake', 'sms' => null]);
    $user = prgAdult(['email' => 'prg-learner2@example.test']);
    app(SetPhoneReminderOptIn::class)($user, 'whatsapp', true);

    $dispatcher = app(NotificationDispatcher::class);
    $notice = new PrgNotice('prg-dispatch-throw', throwOnPhoneTemplate: true);

    expect($dispatcher->send($user->fresh(), $notice))->toBeTrue()
        ->and(NotificationDispatch::query()->count())->toBe(1);
    Notification::assertSentToTimes($user, PrgNotice::class, 1);
    Exceptions::assertReported(RuntimeException::class);

    expect($dispatcher->send($user->fresh(), $notice))->toBeFalse();
    Notification::assertSentToTimes($user, PrgNotice::class, 1);
    expect(NotificationDispatch::query()->count())->toBe(1)
        ->and(prgFake()->messages)->toBe([]);
})->group('T-P9-04', 'A1', 'YG-GAP-NTF-001');
