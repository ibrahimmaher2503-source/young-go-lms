<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
    config(['identity.terms_version' => 't', 'identity.privacy_version' => 'p']);
});

function accountTypePayload(array $overrides = []): array
{
    return [...[
        'email' => 'type@example.test', 'password' => 'ExamplePassword1!', 'password_confirmation' => 'ExamplePassword1!',
        'name_en' => 'Type Test', 'terms_version' => 't', 'privacy_version' => 'p', 'accept_terms' => true, 'accept_privacy' => true,
    ], ...$overrides];
}

it('registers a parent or a learner and nothing else', function (?string $type, array $roles): void {
    $payload = accountTypePayload($type === null ? [] : ['account_type' => $type]);
    $this->postJson('/register', $payload)->assertCreated();

    expect(User::where('email', 'type@example.test')->firstOrFail()->getRoleNames()->all())->toBe($roles);
})->with([[null, ['student']], ['learner', ['student']], ['parent', ['parent']]])->group('T-P1-MG', 'J02', 'YG-GAP-FAM-001');

it('rejects staff account types at public sign-up', function (string $type): void {
    $this->postJson('/register', accountTypePayload(['account_type' => $type]))->assertUnprocessable()->assertJsonValidationErrors('account_type');
    expect(User::where('email', 'type@example.test')->exists())->toBeFalse();
})->with(['super_admin', 'admin', 'finance', 'instructor_academy'])->group('T-P1-MG', 'J02', 'D-006');
