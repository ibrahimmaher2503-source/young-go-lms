<?php

declare(strict_types=1);

use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Notifications\Actions\SendPhoneReminder;
use App\Domain\Notifications\Actions\SetPhoneReminderOptIn;
use App\Domain\Notifications\FakeNotificationChannel;
use App\Domain\Notifications\LocalizedNotification;
use App\Domain\Notifications\NullNotificationChannel;
use App\Domain\Notifications\PhoneChannelResolver;
use App\Domain\Notifications\PhoneTemplates;
use App\Domain\Notifications\QuietHours;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

function phnMakeReminderNotice(string $id = 'notice-1', ?string $template = 'live_session_reminder'): LocalizedNotification
{
    $notice = new class($template) extends LocalizedNotification
    {
        public function __construct(private readonly ?string $tpl) {}

        public function dedupeKey(): string
        {
            return 'test-phone-reminder';
        }

        public function phoneTemplate(): ?string
        {
            return $this->tpl;
        }

        protected function title(User $recipient): string
        {
            return 'Session Starting';
        }

        protected function body(User $recipient): string
        {
            return 'Painting starts in 1 hour';
        }
    };
    $notice->id = $id;

    return $notice;
}

it('keeps whatsapp and sms channels disabled by default and resolves null or fake drivers safely', function (): void {
    Http::fake();
    $resolver = app(PhoneChannelResolver::class);

    expect(config('live.phone_reminders'))->toBe(['whatsapp' => null, 'sms' => null])
        ->and(config('phone_channels.providers'))->toBe([])
        ->and($resolver->for('whatsapp'))->toBeInstanceOf(NullNotificationChannel::class)
        ->and($resolver->for('sms'))->toBeInstanceOf(NullNotificationChannel::class);

    Config::set('live.phone_reminders', ['whatsapp' => 'unknown_driver', 'sms' => 'fake']);
    expect($resolver->for('whatsapp'))->toBeInstanceOf(NullNotificationChannel::class)
        ->and($resolver->for('sms'))->toBeInstanceOf(FakeNotificationChannel::class);

    // Even when opted in, default null config sends nothing (O-021)
    Config::set('live.phone_reminders', ['whatsapp' => null, 'sms' => null]);
    $adult = User::factory()->create([
        'status' => 'active',
        'phone' => '+201012345678',
        'timezone' => 'Africa/Cairo',
        'birth_year' => 1990,
        'birth_month' => 5,
    ]);
    app(SetPhoneReminderOptIn::class)($adult, 'whatsapp', true);
    app(SendPhoneReminder::class)($adult->fresh(), phnMakeReminderNotice());

    expect(app(FakeNotificationChannel::class)->messages)->toBe([]);
    Http::assertNothingSent();
})->group('T-P9-04', 'A1', 'YG-GAP-NTF-001', 'O-021');

it('requires adult canonical phone opt-in bound to current phone and rejects minors and non-canonical numbers', function (): void {
    Http::fake();
    Config::set('live.phone_reminders', ['whatsapp' => 'fake', 'sms' => 'fake']);
    $optIn = app(SetPhoneReminderOptIn::class);

    expect(fn () => $optIn(User::factory()->create(), 'telegram', true))
        ->toThrow(InvalidArgumentException::class);

    $badPhone = User::factory()->create(['phone' => '01012345678', 'birth_year' => 1990, 'birth_month' => 1]);
    expect(fn () => $optIn($badPhone, 'sms', true))
        ->toThrow(InvalidArgumentException::class);

    // Minor via birth_year (once ai.adult_age is configured) or GuardianChildLink cannot opt in or receive phone reminders
    Config::set('ai.adult_age', 18);
    $minor = User::factory()->create([
        'phone' => '+201012345678',
        'birth_year' => now()->year - 10,
        'birth_month' => 1,
    ]);
    expect(fn () => $optIn($minor, 'whatsapp', true))
        ->toThrow(InvalidArgumentException::class);

    // Under default O-015 config (ai.adult_age = null), a minor with birth_year or GuardianChildLink still cannot opt in or receive reminders
    Config::set('ai.adult_age', null);
    expect(fn () => $optIn($minor, 'whatsapp', true))
        ->toThrow(InvalidArgumentException::class);

    $parent = User::factory()->create(['phone' => '+201099988877', 'birth_year' => 1985, 'birth_month' => 1]);
    $linkedChild = User::factory()->create([
        'phone' => '+201011122233',
        'phone_reminder_opt_ins' => ['whatsapp' => '+201011122233'],
    ]);
    GuardianChildLink::query()->create(['guardian_id' => $parent->id, 'child_id' => $linkedChild->id, 'status' => GuardianChildLink::STATUS_VERIFIED, 'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT, 'verified_at' => now()]);
    app(SendPhoneReminder::class)($linkedChild, phnMakeReminderNotice('minor-notice'));
    expect(app(FakeNotificationChannel::class)->messages)->toBe([]);

    // Failed audit recording rolls back phone opt-in mutation cleanly
    $rollbackUser = User::factory()->create([
        'phone' => '+201077788899',
        'birth_year' => 1990,
        'birth_month' => 2,
    ]);
    activity()->disableLogging();
    try {
        expect(fn () => $optIn($rollbackUser, 'whatsapp', true))->toThrow(RuntimeException::class);
    } finally {
        activity()->enableLogging();
    }
    expect((array) $rollbackUser->fresh()->getAttribute('phone_reminder_opt_ins'))->toBe([]);

    // Adult opts in -> audited without exposing phone number -> phone change invalidates prior opt-in
    $adult = User::factory()->create([
        'phone' => '+201055566677',
        'birth_year' => 1991,
        'birth_month' => 3,
        'timezone' => 'UTC',
    ]);
    $optIn($adult, 'whatsapp', true);

    $audit = Activity::query()->where('event', 'notifications.phone_opt_in_changed')->latest('id')->first();
    expect($audit)->not->toBeNull()
        ->and(json_encode($audit->properties))->not->toContain('+201055566677');

    // Phone updated -> previous opt-in bound to old number no longer sends until re-consented
    $adult->forceFill(['phone' => '+201044433322'])->save();
    app(SendPhoneReminder::class)($adult->fresh(), phnMakeReminderNotice('stale-optin'));
    expect(app(FakeNotificationChannel::class)->messages)->toBe([]);

    // Opt out clears channel cleanly
    $optIn($adult->fresh(), 'whatsapp', false);
    expect((array) $adult->fresh()->getAttribute('phone_reminder_opt_ins'))->not->toHaveKey('whatsapp');
    Http::assertNothingSent();
})->group('T-P9-04', 'A1', 'YG-GAP-NTF-001', 'O-021');

it('respects midnight-wrapping quiet hours, allowlisted templates, and idempotent fake delivery', function (): void {
    Http::fake();
    Config::set('live.phone_reminders', ['whatsapp' => 'fake', 'sms' => 'fake']);
    Config::set('phone_channels.quiet_hours', ['start' => '21:00', 'end' => '08:00']);

    // Quiet hours boundary checks in Africa/Cairo
    expect(QuietHours::contains(CarbonImmutable::parse('2026-11-18 19:00:00', 'UTC'), 'Africa/Cairo'))->toBeTrue()  // 21:00 Cairo
        ->and(QuietHours::contains(CarbonImmutable::parse('2026-11-18 23:30:00', 'UTC'), 'Africa/Cairo'))->toBeTrue() // 01:30 Cairo
        ->and(QuietHours::contains(CarbonImmutable::parse('2026-11-18 06:00:00', 'UTC'), 'Africa/Cairo'))->toBeFalse() // 08:00 Cairo
        ->and(QuietHours::contains(CarbonImmutable::parse('2026-11-18 12:00:00', 'UTC'), 'Africa/Cairo'))->toBeFalse(); // 14:00 Cairo

    // Unknown templates & unsupported locales render null
    expect(PhoneTemplates::render('unknown_event', 'en', 'Hi'))->toBeNull()
        ->and(PhoneTemplates::render('live_session_reminder', 'fr', 'Hi'))->toBeNull()
        ->and(PhoneTemplates::render('live_session_reminder', 'ar', 'بدء الجلسة'))->not->toBeNull();

    $adult = User::factory()->create([
        'phone' => '+201022233344',
        'timezone' => 'Africa/Cairo',
        'locale' => 'ar',
        'birth_year' => 1990,
        'birth_month' => 6,
    ]);
    app(SetPhoneReminderOptIn::class)($adult, 'whatsapp', true);

    // Inside quiet hours (22:00 Cairo = 20:00 UTC in winter) -> skipped, not sent
    $this->travelTo(CarbonImmutable::parse('2026-11-18 20:00:00', 'UTC'));
    app(SendPhoneReminder::class)($adult->fresh(), phnMakeReminderNotice('quiet-1'));
    expect(app(FakeNotificationChannel::class)->messages)->toBe([]);

    // Outside quiet hours (14:00 Cairo = 12:00 UTC) + null template -> nothing; known template -> delivered idempotently
    $this->travelTo(CarbonImmutable::parse('2026-11-18 12:00:00', 'UTC'));
    app(SendPhoneReminder::class)($adult->fresh(), phnMakeReminderNotice('no-tpl', null));
    expect(app(FakeNotificationChannel::class)->messages)->toBe([]);

    $notice = phnMakeReminderNotice('deliver-1', 'live_session_reminder');
    app(SendPhoneReminder::class)($adult->fresh(), $notice);
    app(SendPhoneReminder::class)($adult->fresh(), $notice);

    $messages = app(FakeNotificationChannel::class)->messages;
    expect($messages)->toHaveCount(1)
        ->and($messages['deliver-1:whatsapp']['locale'])->toBe('ar')
        ->and($messages['deliver-1:whatsapp']['template'])->toBe('live_session_reminder');
    Http::assertNothingSent();
})->group('T-P9-04', 'A1', 'YG-GAP-NTF-001', 'O-021');
