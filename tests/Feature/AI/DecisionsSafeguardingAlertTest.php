<?php

declare(strict_types=1);

// T-DECISIONS-2 | O-028 | A1 A2

use App\Domain\AI\Safeguarding\SafeguardingNotifier;
use App\Domain\Notifications\NotificationMatrix;
use App\Domain\Notifications\NotificationOptOut;
use App\Domain\Notifications\NotificationPreferences;
use App\Domain\Notifications\Notifications\TemplatedNotice;
use App\Models\AiSafeguardingFlag;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

/** An active user holding one role, with an email so the mail copy is sent. */
function dsaUser(string $role): User
{
    return User::factory()->create(['status' => 'active'])->assignRole($role);
}

/** A learner with an open safeguarding flag raised by the assistant guard. */
function dsaFlag(): AiSafeguardingFlag
{
    $learner = dsaUser('student');

    return AiSafeguardingFlag::query()->create([
        'student_id' => $learner->id, 'conversation_id' => null, 'category' => 'self_harm', 'status' => 'open',
    ]);
}

// ---------------------------------------------------------------- A1: alert notices

it('notifies each safeguarding recipient once with the alert event, a mail copy and the help request link', function (): void {
    Notification::fake();
    $admin = dsaUser('admin');
    $support = dsaUser('support');
    $flag = dsaFlag();

    $sent = app(SafeguardingNotifier::class)->flagRaised($flag);

    expect($sent)->toBe(2);
    foreach ([$admin, $support] as $recipient) {
        Notification::assertSentToTimes($recipient, TemplatedNotice::class, 1);
        Notification::assertSentTo($recipient, TemplatedNotice::class, function (TemplatedNotice $notice, array $channels) use ($recipient): bool {
            $data = $notice->toArray($recipient);

            return $notice->event() === 'safeguarding.alert'
                && in_array('mail', $channels, true)
                && str_contains((string) $data['url'], '/admin/help-requests');
        });
    }
})->group('T-DECISIONS-2', 'O-028', 'A1');

it('keeps the learner text out of the notice data', function (): void {
    Notification::fake();
    $admin = dsaUser('admin');
    $flag = dsaFlag();

    app(SafeguardingNotifier::class)->flagRaised($flag);

    Notification::assertSentTo($admin, TemplatedNotice::class, function (TemplatedNotice $notice) use ($admin): bool {
        $encoded = json_encode($notice->toArray($admin), JSON_UNESCAPED_UNICODE);

        // The service passes no learner text; the notice must not echo a message or the category.
        return ! str_contains((string) $encoded, 'hurt') && ! str_contains((string) $encoded, 'self_harm');
    });
})->group('T-DECISIONS-2', 'O-028', 'A1');

it('sends nothing a second time for the same flag and records the second run as zero notified', function (): void {
    Notification::fake();
    $admin = dsaUser('admin');
    $flag = dsaFlag();
    $notifier = app(SafeguardingNotifier::class);

    $first = $notifier->flagRaised($flag);
    $second = $notifier->flagRaised($flag);

    expect($first)->toBe(1)
        ->and($second)->toBe(0);
    Notification::assertSentToTimes($admin, TemplatedNotice::class, 1);
})->group('T-DECISIONS-2', 'O-028', 'A1');

it('writes the safeguarding.notified audit with source, case, recipients and sent count and no learner text', function (): void {
    Notification::fake();
    dsaUser('admin');
    $flag = dsaFlag();

    app(SafeguardingNotifier::class)->flagRaised($flag);

    $activity = Activity::query()->where('event', 'safeguarding.notified')->sole();
    $payload = json_encode($activity->properties, JSON_UNESCAPED_UNICODE);
    expect($payload)->toContain('ai_flag')
        ->and($payload)->toContain('"case_id":'.$flag->id)
        ->and($payload)->toContain('"recipients":1')
        ->and($payload)->toContain('"sent_now":1');
})->group('T-DECISIONS-2', 'O-028', 'A1');

it('reports a failing channel but keeps the flag open and audits the run with sent_now 0', function (): void {
    dsaUser('admin');
    $flag = dsaFlag();

    // A mailer whose transport always fails: the database inbox row is written, the mail channel throws.
    config(['mail.default' => 'broken', 'mail.mailers.broken' => ['transport' => 'broken']]);
    Mail::extend('broken', fn () => new class extends AbstractTransport
    {
        protected function doSend(SentMessage $message): void
        {
            throw new RuntimeException('Mail transport unavailable for test.');
        }

        public function __toString(): string
        {
            return 'broken://test';
        }
    });

    $sent = app(SafeguardingNotifier::class)->flagRaised($flag);

    expect($sent)->toBe(0)
        ->and($flag->fresh()->status)->toBe('open')
        ->and(Activity::query()->where('event', 'safeguarding.notified')->sole()->properties->toJson())->toContain('"sent_now":0');
})->group('T-DECISIONS-2', 'O-028', 'A1');

// ---------------------------------------------------------------- A2: mandatory matrix and wording

it('marks safeguarding.alert mandatory and keeps it on for every recipient even with an opt-out row', function (): void {
    $admin = dsaUser('admin');
    $definition = NotificationMatrix::find('safeguarding.alert');
    NotificationOptOut::query()->create(['user_id' => $admin->id, 'event_key' => 'safeguarding.alert']);

    expect($definition)->not->toBeNull()
        ->and($definition->mandatory)->toBeTrue()
        ->and(NotificationPreferences::allows($admin, 'safeguarding.alert'))->toBeTrue();
})->group('T-DECISIONS-2', 'O-028', 'A2');

it('has AR and EN title, body, event label and group label for the safeguarding alert', function (): void {
    $definition = NotificationMatrix::find('safeguarding.alert');
    $keys = [$definition->titleKey, $definition->bodyKey, 'notification_events.events.safeguarding_alert', 'notification_events.groups.safeguarding'];

    foreach (['ar', 'en'] as $locale) {
        app()->setLocale($locale);
        foreach ($keys as $key) {
            expect(__($key))->not->toBe($key);
        }
    }
    app()->setLocale('en');
})->group('T-DECISIONS-2', 'O-028', 'A2');

it('has AR and EN labels for the safeguarding.notified audit event', function (): void {
    $ar = require base_path('lang/ar/audit-events.php');
    $en = require base_path('lang/en/audit-events.php');

    expect($ar)->toHaveKey('safeguarding.notified')
        ->and($en)->toHaveKey('safeguarding.notified');
})->group('T-DECISIONS-2', 'O-028', 'A2');
