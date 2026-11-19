<?php

declare(strict_types=1);

// T-PASSMARK-RECIPIENTS | O-028 | B (safeguarding extra recipients)

use App\Domain\AI\Safeguarding\SafeguardingRecipients;
use App\Domain\Settings\Actions\SaveSafeguardingRecipients;
use App\Domain\Settings\AiSettings;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

function tprUser(string $role, string $status = 'active', ?string $email = null): User
{
    $attributes = ['status' => $status];
    if ($email !== null) {
        $attributes['email'] = $email;
    }

    return User::factory()->create($attributes)->assignRole($role);
}

function tprStored(): ?string
{
    return app(AiSettings::class)->refresh()->safeguarding_extra_recipients;
}

it('lets admin and super_admin save and refuses other roles and inactive admins', function (string $role): void {
    $actor = tprUser($role);

    app(SaveSafeguardingRecipients::class)($actor, 'new@x.test');

    expect(tprStored())->toBe('new@x.test');
})->with(['admin', 'super_admin'])->group('T-PASSMARK-RECIPIENTS', 'B');

it('refuses support, instructor, learner and inactive admin', function (string $role, string $status): void {
    $actor = tprUser($role, $status);

    expect(fn () => app(SaveSafeguardingRecipients::class)($actor, 'new@x.test'))
        ->toThrow(AuthorizationException::class);
    expect(tprStored())->toBeNull();
})->with([
    ['support', 'active'],
    ['instructor_academy', 'active'],
    ['student', 'active'],
    ['admin', 'inactive'],
])->group('T-PASSMARK-RECIPIENTS', 'B');

it('trims, lowercases and deduplicates, and accepts commas, semicolons, spaces and new lines', function (): void {
    $admin = tprUser('admin');

    app(SaveSafeguardingRecipients::class)($admin, " A@x.test,a@x.test;  B@x.test\nc@x.test ");

    expect(tprStored())->toBe('a@x.test, b@x.test, c@x.test')
        ->and(app(SaveSafeguardingRecipients::class)->emails())->toBe(['a@x.test', 'b@x.test', 'c@x.test']);
})->group('T-PASSMARK-RECIPIENTS', 'B');

it('names the invalid email in a ValidationException', function (): void {
    $admin = tprUser('admin');

    try {
        app(SaveSafeguardingRecipients::class)($admin, ['ok@x.test', 'not-an-email']);
        $this->fail('Expected ValidationException');
    } catch (ValidationException $e) {
        expect($e->errors())->toHaveKey('emails')
            ->and($e->errors()['emails'][0])->toContain('not-an-email');
    }
})->group('T-PASSMARK-RECIPIENTS', 'B');

it('accepts exactly 20 addresses and refuses 21', function (): void {
    $admin = tprUser('admin');
    $twenty = array_map(fn (int $i): string => "user{$i}@x.test", range(1, 20));

    app(SaveSafeguardingRecipients::class)($admin, $twenty);
    expect(app(SaveSafeguardingRecipients::class)->emails())->toHaveCount(20);

    $twentyOne = [...$twenty, 'user21@x.test'];
    expect(fn () => app(SaveSafeguardingRecipients::class)($admin, $twentyOne))->toThrow(ValidationException::class);
})->group('T-PASSMARK-RECIPIENTS', 'B');

it('stores null for an empty list', function (): void {
    $admin = tprUser('admin');
    app(SaveSafeguardingRecipients::class)($admin, 'a@x.test');

    app(SaveSafeguardingRecipients::class)($admin, []);

    expect(tprStored())->toBeNull();
})->group('T-PASSMARK-RECIPIENTS', 'B');

it('keeps numeric user ids already in the setting when the emails change', function (): void {
    $settings = app(AiSettings::class);
    $settings->safeguarding_extra_recipients = '12, old@x.test';
    $settings->save();
    $admin = tprUser('admin');

    app(SaveSafeguardingRecipients::class)($admin, 'new@x.test');

    expect(tprStored())->toBe('12, new@x.test')
        ->and(app(SaveSafeguardingRecipients::class)->emails())->toBe(['new@x.test']);
})->group('T-PASSMARK-RECIPIENTS', 'B');

it('writes no audit and does not save when the list is unchanged', function (): void {
    $admin = tprUser('admin');
    app(SaveSafeguardingRecipients::class)($admin, 'a@x.test');
    $before = Activity::query()->where('event', 'settings.safeguarding_recipients_saved')->count();

    app(SaveSafeguardingRecipients::class)($admin, 'A@x.test');

    expect(Activity::query()->where('event', 'settings.safeguarding_recipients_saved')->count())->toBe($before);
})->group('T-PASSMARK-RECIPIENTS', 'B');

it('audits before and after email lists with the actor id', function (): void {
    $admin = tprUser('admin');
    app(SaveSafeguardingRecipients::class)($admin, 'a@x.test');

    app(SaveSafeguardingRecipients::class)($admin, 'b@x.test');

    $audit = Activity::query()->where('event', 'settings.safeguarding_recipients_saved')->latest('id')->firstOrFail();
    expect($audit->causer_id)->toBe($admin->id)
        ->and($audit->properties['before']['emails'])->toBe(['a@x.test'])
        ->and($audit->properties['after']['emails'])->toBe(['b@x.test']);
})->group('T-PASSMARK-RECIPIENTS', 'B');

it('rolls the setting back when the audit cannot be recorded', function (): void {
    // AuditLogger is final, so Mockery cannot stand in for a failing audit; needs a fault seam.
})->todo('AuditLogger is final: no way to force a failed audit without a seam; rollback not covered')->group('T-PASSMARK-RECIPIENTS', 'B');

it('resolver includes a saved active address once, excludes inactive users, and lists an admin only once', function (): void {
    $admin = tprUser('admin', 'active', 'boss@x.test');
    $inactive = tprUser('support', 'inactive', 'gone@x.test');
    $member = tprUser('student', 'active', 'new@x.test');

    app(SaveSafeguardingRecipients::class)($admin, "new@x.test, gone@x.test, boss@x.test");

    $ids = app(SafeguardingRecipients::class)->all()->pluck('id')->all();

    expect($ids)->toContain($member->id)
        ->and($ids)->not->toContain($inactive->id)
        ->and(array_count_values($ids)[$admin->id] ?? 0)->toBe(1)
        ->and(array_unique($ids))->toBe($ids);
})->group('T-PASSMARK-RECIPIENTS', 'B');
