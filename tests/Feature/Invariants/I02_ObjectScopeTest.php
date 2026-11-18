<?php

declare(strict_types=1);

use App\Domain\Identity\Actions\CreateChildAccount;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

it('enforces object ownership and relationship scopes in policies', function (string $role, string $linkStatus, bool $allowed): void {
    $this->seed(RolePermissionSeeder::class);
    $actor = User::factory()->create()->assignRole($role);
    $child = User::factory()->create()->assignRole('student');
    $unrelated = User::factory()->create()->assignRole('student');
    GuardianChildLink::query()->create([
        'guardian_id' => $actor->id,
        'child_id' => $child->id,
        'status' => $linkStatus,
        'method' => GuardianChildLink::METHOD_LINK_CODE,
    ]);

    expect(Gate::forUser($actor)->allows('guardian.child.access', $child))->toBe($allowed)
        ->and(Gate::forUser($actor)->inspect('guardian.child.access', $unrelated)->status())->toBe(404);
})->with([
    'verified parent' => ['parent', GuardianChildLink::STATUS_VERIFIED, true],
    'pending parent' => ['parent', GuardianChildLink::STATUS_PENDING, false],
    'super admin with a link but no parent role' => ['super_admin', GuardianChildLink::STATUS_VERIFIED, false],
])->group('T-P1-03', 'I02', 'PRD-06');

it('scopes guardian access to verified linked children only', function (): void {
    $this->seed(RolePermissionSeeder::class);
    $parent = User::factory()->create();
    $parent->assignRole('parent');
    $otherParent = User::factory()->create();
    $otherParent->assignRole('parent');
    $child = app(CreateChildAccount::class)->handle($parent, [
        'username' => 'scoped-child', 'password' => 'ChildPassword1!', 'name_en' => 'Kid', 'birth_year' => 2015, 'birth_month' => 1,
    ]);

    expect(Gate::forUser($parent)->allows('guardian.child.access', $child))->toBeTrue()
        ->and(Gate::forUser($otherParent)->inspect('guardian.child.access', $child)->status())->toBe(404)
        ->and(Gate::forUser($child)->inspect('guardian.child.access', $parent)->status())->toBe(404);
})->group('T-P1-05', 'I02', 'A2');
