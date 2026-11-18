<?php

declare(strict_types=1);

// T-P9-04 | YG-GAP-NTF-001 O-021 O-015 | A1

use App\Domain\Identity\Models\GuardianChildLink;
use App\Domain\Notifications\Actions\SendPhoneReminder;
use App\Domain\Notifications\Actions\SetPhoneReminderOptIn;
use App\Domain\Notifications\FakeNotificationChannel;
use App\Domain\Notifications\LocalizedNotification;
use App\Domain\Notifications\NotificationChannel;
use App\Domain\Notifications\NullNotificationChannel;
use App\Domain\Notifications\PhoneChannelResolver;
use App\Domain\Notifications\PhoneTemplates;
use App\Domain\Notifications\QuietHours;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

/** Records every call a registered provider receives (test double for a real WhatsApp/SMS driver). */
final class T904RecordingChannel implements NotificationChannel
{
    /** @var list<array{channel: string, phone: string, locale: string, template: string, message: string, key: string}> */
    public static array $calls = [];

    public function send(string $channel, string $phone, string $locale, string $template, string $message, string $idempotencyKey): void
    {
        self::$calls[] = ['channel' => $channel, 'phone' => $phone, 'locale' => $locale, 'template' => $template, 'message' => $message, 'key' => $idempotencyKey];
    }
}

/** A reminder-like notice whose phone template is configurable, so the test controls the copy and the template. */
final class T904ReminderNotice extends LocalizedNotification
{
    public function __construct(
        string $id,
        private readonly ?string $template = 'live_session_reminder',
        private readonly string $copy = 'Painting starts in 1 hour',
    ) {
        $this->id = $id;
    }

    public function dedupeKey(): string
    {
        return 't904:'.$this->id;
    }

    public function phoneTemplate(): ?string
    {
        return $this->template;
    }

    protected function title(User $recipient): string
    {
        return 'Session starting';
    }

    protected function body(User $recipient): string
    {
        return $this->copy;
    }
}

function t904Adult(array $attributes = []): User
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

function t904Fake(): FakeNotificationChannel
{
    return app(FakeNotificationChannel::class);
}

beforeEach(function (): void {
    T904RecordingChannel::$calls = [];
    Config::set('live.phone_reminders', ['whatsapp' => null, 'sms' => null]);
    Config::set('phone_channels.providers', []);
    Config::set('phone_channels.quiet_hours', ['start' => '21:00', 'end' => '08:00']);
    Config::set('ai.adult_age', 18);
    // 10:00 UTC is 12:00 in Africa/Cairo (UTC+2 in March): outside quiet hours unless a test moves the clock.
    $this->travelTo(CarbonImmutable::parse('2027-03-10 10:00:00', 'UTC'));
});

it('sends nothing when both phone channels are disabled, even for an opted-in adult', function (): void {
    $adult = t904Adult();
    app(SetPhoneReminderOptIn::class)($adult, 'whatsapp', true);
    app(SetPhoneReminderOptIn::class)($adult->fresh(), 'sms', true);

    app(SendPhoneReminder::class)($adult->fresh(), new T904ReminderNotice('t904-off'));

    expect(t904Fake()->messages)->toBe([])
        ->and(app(PhoneChannelResolver::class)->for('sms'))->toBeInstanceOf(NullNotificationChannel::class);
})->group('T-P9-04', 'A1');

it('keeps the fake driver out of production even when it is configured', function (): void {
    Config::set('live.phone_reminders', ['whatsapp' => 'fake', 'sms' => 'fake']);
    app()->detectEnvironment(fn (): string => 'production');

    $adult = t904Adult();
    app(SetPhoneReminderOptIn::class)($adult, 'whatsapp', true);
    app(SendPhoneReminder::class)($adult->fresh(), new T904ReminderNotice('t904-prod'));

    expect(app(PhoneChannelResolver::class)->for('whatsapp'))->toBeInstanceOf(NullNotificationChannel::class)
        ->and(t904Fake()->messages)->toBe([]);
})->group('T-P9-04', 'A1');

it('records exactly one fake message with channel, phone, locale, template and template wording, and dedupes a repeat run', function (): void {
    Config::set('live.phone_reminders', ['whatsapp' => 'fake', 'sms' => null]);
    $adult = t904Adult(['locale' => 'en']);
    app(SetPhoneReminderOptIn::class)($adult, 'whatsapp', true);

    $notice = new T904ReminderNotice('t904-once');
    app(SendPhoneReminder::class)($adult->fresh(), $notice);
    app(SendPhoneReminder::class)($adult->fresh(), $notice);

    $messages = t904Fake()->messages;
    expect($messages)->toHaveCount(1)
        ->and($messages)->toHaveKey('t904-once:whatsapp')
        ->and($messages['t904-once:whatsapp'])->toMatchArray([
            'channel' => 'whatsapp',
            'phone' => '+201012345678',
            'locale' => 'en',
            'template' => 'live_session_reminder',
        ])
        ->and($messages['t904-once:whatsapp']['message'])->toStartWith('Young Go reminder: ')
        ->and($messages['t904-once:whatsapp']['message'])->toContain('Painting starts in 1 hour');
})->group('T-P9-04', 'A1');

it('selects a registered provider class for a channel and resolves unregistered names to the null driver', function (): void {
    Config::set('phone_channels.providers', ['t904_recorder' => T904RecordingChannel::class]);
    Config::set('live.phone_reminders', ['whatsapp' => null, 'sms' => 't904_recorder']);

    expect(app(PhoneChannelResolver::class)->for('sms'))->toBeInstanceOf(T904RecordingChannel::class);

    $adult = t904Adult();
    app(SetPhoneReminderOptIn::class)($adult, 'sms', true);
    app(SendPhoneReminder::class)($adult->fresh(), new T904ReminderNotice('t904-provider'));

    expect(T904RecordingChannel::$calls)->toHaveCount(1)
        ->and(T904RecordingChannel::$calls[0])->toMatchArray([
            'channel' => 'sms',
            'phone' => '+201012345678',
            'locale' => 'en',
            'template' => 'live_session_reminder',
            'key' => 't904-provider:sms',
        ]);

    // An unregistered driver name does not throw and keeps nothing.
    Config::set('live.phone_reminders', ['whatsapp' => 'not_registered', 'sms' => null]);
    expect(app(PhoneChannelResolver::class)->for('whatsapp'))->toBeInstanceOf(NullNotificationChannel::class);
    app(SetPhoneReminderOptIn::class)($adult->fresh(), 'whatsapp', true);
    app(SendPhoneReminder::class)($adult->fresh(), new T904ReminderNotice('t904-unregistered'));

    expect(T904RecordingChannel::$calls)->toHaveCount(1)
        ->and(t904Fake()->messages)->toBe([]);
})->group('T-P9-04', 'A1');

it('sends nothing without consent and nothing after the phone number changes', function (): void {
    Config::set('live.phone_reminders', ['whatsapp' => 'fake', 'sms' => 'fake']);
    $adult = t904Adult();

    app(SendPhoneReminder::class)($adult, new T904ReminderNotice('t904-noconsent'));
    expect(t904Fake()->messages)->toBe([]);

    app(SetPhoneReminderOptIn::class)($adult, 'whatsapp', true);
    $adult->forceFill(['phone' => '+201044433322'])->save();
    app(SendPhoneReminder::class)($adult->fresh(), new T904ReminderNotice('t904-changed'));

    expect(t904Fake()->messages)->toBe([]);
})->group('T-P9-04', 'A1');

it('keeps consent independent per channel: whatsapp only does not send sms', function (): void {
    Config::set('live.phone_reminders', ['whatsapp' => 'fake', 'sms' => 'fake']);
    $adult = t904Adult();
    app(SetPhoneReminderOptIn::class)($adult, 'whatsapp', true);

    app(SendPhoneReminder::class)($adult->fresh(), new T904ReminderNotice('t904-channels'));

    expect(array_keys(t904Fake()->messages))->toBe(['t904-channels:whatsapp']);
})->group('T-P9-04', 'A1');

it('rejects a non-canonical phone and an unsupported channel, and revoking removes consent', function (): void {
    Config::set('live.phone_reminders', ['whatsapp' => 'fake', 'sms' => 'fake']);
    $optIn = app(SetPhoneReminderOptIn::class);

    $local = t904Adult(['phone' => '01012345678']);
    expect(fn () => $optIn($local, 'whatsapp', true))->toThrow(InvalidArgumentException::class);

    $adult = t904Adult();
    expect(fn () => $optIn($adult, 'telegram', true))->toThrow(InvalidArgumentException::class);

    $optIn($adult, 'whatsapp', true);
    $optIn($adult->fresh(), 'whatsapp', false);
    expect((array) $adult->fresh()->getAttribute('phone_reminder_opt_ins'))->not->toHaveKey('whatsapp');

    app(SendPhoneReminder::class)($adult->fresh(), new T904ReminderNotice('t904-revoked'));
    expect(t904Fake()->messages)->toBe([]);
})->group('T-P9-04', 'A1');

it('does not send inside quiet hours in the recipient timezone and sends at midday there', function (): void {
    Config::set('live.phone_reminders', ['whatsapp' => 'fake', 'sms' => null]);
    $adult = t904Adult(['timezone' => 'Africa/Cairo']);
    app(SetPhoneReminderOptIn::class)($adult, 'whatsapp', true);

    // 20:00 UTC is 22:00 Cairo: quiet.
    $this->travelTo(CarbonImmutable::parse('2027-03-10 20:00:00', 'UTC'));
    app(SendPhoneReminder::class)($adult->fresh(), new T904ReminderNotice('t904-night'));
    expect(t904Fake()->messages)->toBe([]);

    // 10:00 UTC is 12:00 Cairo: sent.
    $this->travelTo(CarbonImmutable::parse('2027-03-10 10:00:00', 'UTC'));
    app(SendPhoneReminder::class)($adult->fresh(), new T904ReminderNotice('t904-noon'));
    expect(array_keys(t904Fake()->messages))->toBe(['t904-noon:whatsapp']);
})->group('T-P9-04', 'A1');

it('evaluates quiet hours with start inclusive, end exclusive, midnight wrap and fail-safe config', function (): void {
    $cairo = 'Africa/Cairo';
    $at = fn (string $utc): CarbonImmutable => CarbonImmutable::parse("2027-03-10 {$utc}", 'UTC');

    // Default window 21:00-08:00 in Cairo (UTC+2 in March).
    expect(QuietHours::contains($at('19:00:00'), $cairo))->toBeTrue()   // 21:00 exactly: start inclusive
        ->and(QuietHours::contains($at('18:59:00'), $cairo))->toBeFalse() // 20:59
        ->and(QuietHours::contains($at('23:30:00'), $cairo))->toBeTrue()  // 01:30 next day: wraps midnight
        ->and(QuietHours::contains($at('00:30:00'), $cairo))->toBeTrue()  // 02:30
        ->and(QuietHours::contains($at('05:59:00'), $cairo))->toBeTrue()  // 07:59
        ->and(QuietHours::contains($at('06:00:00'), $cairo))->toBeFalse() // 08:00 exactly: end exclusive
        ->and(QuietHours::contains($at('10:00:00'), $cairo))->toBeFalse();

    // Equal bounds, malformed bounds and missing bounds are never quiet.
    Config::set('phone_channels.quiet_hours', ['start' => '21:00', 'end' => '21:00']);
    expect(QuietHours::contains($at('19:30:00'), $cairo))->toBeFalse();

    Config::set('phone_channels.quiet_hours', ['start' => '9pm', 'end' => '08:00']);
    expect(QuietHours::contains($at('19:30:00'), $cairo))->toBeFalse();

    Config::set('phone_channels.quiet_hours', []);
    expect(QuietHours::contains($at('19:30:00'), $cairo))->toBeFalse();

    // Unknown or missing timezone is read as UTC.
    Config::set('phone_channels.quiet_hours', ['start' => '21:00', 'end' => '08:00']);
    expect(QuietHours::contains($at('22:00:00'), 'Mars/Olympus'))->toBeTrue()
        ->and(QuietHours::contains($at('22:00:00'), null))->toBeTrue()
        ->and(QuietHours::contains($at('12:00:00'), null))->toBeFalse();
})->group('T-P9-04', 'A1');

it('never messages a minor: a child cannot opt in and forced consent is ignored', function (): void {
    Config::set('live.phone_reminders', ['whatsapp' => 'fake', 'sms' => 'fake']);
    $optIn = app(SetPhoneReminderOptIn::class);

    // Linked as a child of a guardian.
    $guardian = t904Adult(['phone' => '+201099988877']);
    $child = t904Adult(['phone' => '+201011122233', 'birth_year' => 2015]);
    GuardianChildLink::query()->create([
        'guardian_id' => $guardian->id,
        'child_id' => $child->id,
        'status' => GuardianChildLink::STATUS_VERIFIED,
        'method' => GuardianChildLink::METHOD_CREATED_BY_PARENT,
        'verified_at' => now(),
    ]);
    expect(fn () => $optIn($child, 'whatsapp', true))->toThrow(InvalidArgumentException::class);

    $child->forceFill(['phone_reminder_opt_ins' => ['whatsapp' => '+201011122233']])->save();
    app(SendPhoneReminder::class)($child->fresh(), new T904ReminderNotice('t904-linked'));

    // Under the adult age by birth year only.
    $young = t904Adult(['birth_year' => now()->year - 10, 'birth_month' => 1]);
    expect(fn () => $optIn($young, 'sms', true))->toThrow(InvalidArgumentException::class);

    $young->forceFill(['phone_reminder_opt_ins' => ['sms' => '+201012345678']])->save();
    app(SendPhoneReminder::class)($young->fresh(), new T904ReminderNotice('t904-young'));

    expect(t904Fake()->messages)->toBe([]);
})->group('T-P9-04', 'A1');

it('localises the template wording to the recipient language and returns null for missing keys or locales', function (): void {
    Config::set('live.phone_reminders', ['whatsapp' => 'fake', 'sms' => null]);

    $arabic = t904Adult(['locale' => 'ar']);
    $english = t904Adult(['locale' => 'en', 'phone' => '+201055566677']);
    app(SetPhoneReminderOptIn::class)($arabic, 'whatsapp', true);
    app(SetPhoneReminderOptIn::class)($english, 'whatsapp', true);

    app(SendPhoneReminder::class)($arabic->fresh(), new T904ReminderNotice('t904-ar'));
    app(SendPhoneReminder::class)($english->fresh(), new T904ReminderNotice('t904-en'));

    $messages = t904Fake()->messages;
    expect($messages['t904-ar:whatsapp']['locale'])->toBe('ar')
        ->and($messages['t904-ar:whatsapp']['message'])->toStartWith('تذكير من يونج جو: ')
        ->and($messages['t904-en:whatsapp']['locale'])->toBe('en')
        ->and($messages['t904-en:whatsapp']['message'])->toStartWith('Young Go reminder: ');

    expect(PhoneTemplates::render('missing_event', 'en', 'Hi'))->toBeNull()
        ->and(PhoneTemplates::render('live_session_reminder', 'fr', 'Hi'))->toBeNull();
})->group('T-P9-04', 'A1');

it('ships the same phone template keys in Arabic and English', function (): void {
    $ar = require base_path('lang/ar/phone_templates.php');
    $en = require base_path('lang/en/phone_templates.php');

    expect(array_keys($ar))->toEqualCanonicalizing(array_keys($en))
        ->and($ar)->not->toBeEmpty()
        ->and(PhoneTemplates::render('live_session_reminder', 'ar', 'x'))->toContain('x');
})->group('T-P9-04', 'A1');

it('logs skips without any phone number or message body', function (): void {
    Config::set('live.phone_reminders', ['whatsapp' => 'fake', 'sms' => null]);
    $adult = t904Adult();
    app(SetPhoneReminderOptIn::class)($adult, 'whatsapp', true);

    Log::spy();
    $this->travelTo(CarbonImmutable::parse('2027-03-10 20:00:00', 'UTC'));
    app(SendPhoneReminder::class)($adult->fresh(), new T904ReminderNotice('t904-log', copy: 'Secret session copy'));

    Log::shouldHaveReceived('info')
        ->with('phone_reminder.skipped', ['reason' => 'quiet_hours', 'template' => 'live_session_reminder'])
        ->once();
    expect(t904Fake()->messages)->toBe([]);
})->group('T-P9-04', 'A1');

it('stores phone consent encrypted at rest', function (): void {
    $adult = t904Adult(['phone' => '+201012345678']);
    app(SetPhoneReminderOptIn::class)($adult, 'whatsapp', true);

    $raw = (string) DB::table('users')->where('id', $adult->id)->value('phone_reminder_opt_ins');

    expect($raw)->not->toBe('')
        ->and($raw)->not->toContain('+201012345678')
        ->and($adult->fresh()->getAttribute('phone_reminder_opt_ins'))->toBe(['whatsapp' => '+201012345678']);
})->group('T-P9-04', 'A1');

it('writes one audit row per real opt-in or opt-out change without the phone number', function (): void {
    $adult = t904Adult();
    $optIn = app(SetPhoneReminderOptIn::class);

    $optIn($adult, 'whatsapp', true);
    $optIn($adult->fresh(), 'whatsapp', true); // repeat: no new audit row
    $optIn($adult->fresh(), 'whatsapp', false);
    $optIn($adult->fresh(), 'whatsapp', false); // repeat: no new audit row

    $rows = Activity::query()
        ->where('event', 'notifications.phone_opt_in_changed')
        ->where('subject_id', $adult->id)
        ->orderBy('id')
        ->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('properties')->map(fn ($p) => json_encode($p))->implode(' '))->not->toContain('+201012345678')
        ->and($rows[0]->properties['attributes']['enabled'] ?? $rows[0]->properties['after']['enabled'] ?? null)->toBeTrue()
        ->and($rows[1]->properties['attributes']['enabled'] ?? $rows[1]->properties['after']['enabled'] ?? null)->toBeFalse();
})->group('T-P9-04', 'A1');

it('has Arabic and English labels for the phone opt-in audit event', function (): void {
    expect(trans('audit-events.notifications.phone_opt_in_changed', [], 'en'))
        ->not->toBe('audit-events.notifications.phone_opt_in_changed')
        ->and(trans('audit-events.notifications.phone_opt_in_changed', [], 'ar'))
        ->not->toBe('audit-events.notifications.phone_opt_in_changed');
})->group('T-P9-04', 'A1');

it('passes the translation and RTL gates for the phone channel', function (): void {
    $this->artisan('app:check-translations')->assertExitCode(0);
    $this->artisan('app:check-rtl')->assertExitCode(0);
})->group('T-P9-04', 'A1');

it('keeps the notifications domain final-action and HTTP-free', function (): void {
    foreach (['SendPhoneReminder', 'SetPhoneReminderOptIn'] as $action) {
        $class = "App\\Domain\\Notifications\\Actions\\{$action}";
        expect((new ReflectionClass($class))->isFinal())->toBeTrue();
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Domain/Notifications'), FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $fileInfo) {
        if ($fileInfo->getExtension() !== 'php') {
            continue;
        }
        $source = (string) file_get_contents($fileInfo->getPathname());
        expect($source)->toContain('declare(strict_types=1);')
            ->and($source)->not->toContain('Illuminate\\Http\\')
            ->and($source)->not->toContain('Filament\\');
    }
})->group('T-P9-04', 'A1');
