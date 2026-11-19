<?php

declare(strict_types=1);

// T-DECISIONS-2 | O-028 | A1

use App\Domain\AI\Safeguarding\SafeguardingRecipients;
use App\Domain\Settings\AiSettings;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RolePermissionSeeder::class));

function decisionsSafeguardUser(string $role, string $status = 'active'): User
{
    return User::factory()->create(['status' => $status])->assignRole($role);
}

function decisionsSafeguardSetExtras(string $value): void
{
    $settings = app(AiSettings::class);
    $settings->safeguarding_extra_recipients = $value;
    $settings->save();
}

it('includes active admin and support users and excludes inactive ones', function (): void {
    $admin = decisionsSafeguardUser('admin');
    $support = decisionsSafeguardUser('support');
    $inactive = decisionsSafeguardUser('admin', 'inactive');

    $ids = app(SafeguardingRecipients::class)->all()->pluck('id')->all();

    expect($ids)->toContain($admin->id, $support->id)->not->toContain($inactive->id);
})->group('T-DECISIONS-2', 'O-028', 'A1');

it('drops support when the configured roles are only admin', function (): void {
    $admin = decisionsSafeguardUser('admin');
    $support = decisionsSafeguardUser('support');
    config(['safeguarding.recipient_roles' => ['admin']]);

    $ids = app(SafeguardingRecipients::class)->all()->pluck('id')->all();

    expect($ids)->toContain($admin->id)->not->toContain($support->id);
})->group('T-DECISIONS-2', 'O-028', 'A1');

it('ignores unknown role names without failing', function (): void {
    $admin = decisionsSafeguardUser('admin');
    config(['safeguarding.recipient_roles' => ['no-such-role', 'admin']]);

    expect(app(SafeguardingRecipients::class)->all()->pluck('id')->all())->toContain($admin->id);
})->group('T-DECISIONS-2', 'O-028', 'A1');

it('adds a user by id and by email from the AI settings without duplicates', function (): void {
    $admin = decisionsSafeguardUser('admin');
    $extra = User::factory()->create(['status' => 'active', 'email' => 'a@b.test'])->assignRole('instructor_independent');
    $extraById = User::factory()->create(['status' => 'active'])->assignRole('instructor_independent');
    decisionsSafeguardSetExtras($extraById->id.', a@b.test, a@b.test');

    $ids = app(SafeguardingRecipients::class)->all()->pluck('id')->all();

    expect($ids)->toContain($admin->id, $extra->id, $extraById->id)
        ->and(array_unique($ids))->toBe($ids);
})->group('T-DECISIONS-2', 'O-028', 'A1');
