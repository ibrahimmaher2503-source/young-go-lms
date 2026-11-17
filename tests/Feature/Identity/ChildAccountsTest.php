<?php

declare(strict_types=1);

use App\Domain\Identity\Actions\CreateChildAccount;
use App\Domain\Identity\GuardianAccess;
use App\Domain\Identity\Models\GuardianChildLink;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(RolePermissionSeeder::class);
});

function childParent(): User
{
    $parent = User::factory()->create();
    $parent->assignRole('parent');

    return $parent;
}

function childInput(array $overrides = []): array
{
    return [...[
        'username' => 'Little-Ali',
        'password' => 'ChildPassword1!',
        'name_ar' => 'علي',
        'name_en' => 'Ali',
        'birth_year' => 2016,
        'birth_month' => 3,
    ], ...$overrides];
}

it('creates an email-less child that logs in with username and password', function (): void {
    $child = app(CreateChildAccount::class)->handle(childParent(), childInput());

    expect($child->email)->toBeNull()
        ->and($child->username)->toBe('little-ali')
        ->and($child->getRoleNames()->all())->toBe(['student']);

    $this->postJson('/login', ['login' => 'Little-Ali', 'password' => 'ChildPassword1!'])->assertNoContent();
    $this->assertAuthenticatedAs($child);
})->group('T-P1-05', 'A1', 'YG-GAP-FAM-001');

it('stores a verified created_by_parent link with empty minor AI consent', function (): void {
    $parent = childParent();
    $child = app(CreateChildAccount::class)->handle($parent, childInput());

    $link = GuardianChildLink::query()->sole();
    expect($link->guardian_id)->toBe($parent->id)
        ->and($link->child_id)->toBe($child->id)
        ->and($link->status)->toBe('verified')
        ->and($link->method)->toBe('created_by_parent')
        ->and($link->getAttribute('verified_at'))->not->toBeNull()
        ->and($link->getAttribute('ai_consent_granted_at'))->toBeNull();
})->group('T-P1-05', 'A1', 'YG-GAP-FAM-003', 'YG-GAP-FAM-005');

it('lets a parent access only linked children and returns 404 otherwise', function (): void {
    $parent = childParent();
    $child = app(CreateChildAccount::class)->handle($parent, childInput());
    $otherParent = childParent();
    $stranger = User::factory()->create();
    $stranger->assignRole('student');

    expect(GuardianAccess::parentCanAccessChild($parent, $child)->allowed())->toBeTrue()
        ->and(Gate::forUser($parent)->inspect('guardian.child.access', $child)->allowed())->toBeTrue();

    foreach ([[$parent, $stranger], [$otherParent, $child]] as [$actor, $target]) {
        $response = Gate::forUser($actor)->inspect('guardian.child.access', $target);
        expect($response->allowed())->toBeFalse()->and($response->status())->toBe(404);
    }
})->group('T-P1-05', 'A2', 'I02', 'YG-GAP-FAM-006');

it('rejects child creation by non-parents including super admin', function (string $role): void {
    $actor = User::factory()->create();
    $actor->assignRole($role);

    expect(fn () => app(CreateChildAccount::class)->handle($actor, childInput()))
        ->toThrow(AuthorizationException::class);
    expect(User::query()->where('username', 'little-ali')->exists())->toBeFalse();
})->with(['student', 'instructor_independent', 'admin', 'super_admin'])->group('T-P1-05', 'A3', 'D-006');

it('rejects an inactive parent', function (): void {
    $parent = childParent();
    $parent->forceFill(['status' => 'suspended'])->save();

    expect(fn () => app(CreateChildAccount::class)->handle($parent, childInput()))
        ->toThrow(AuthorizationException::class);
})->group('T-P1-05', 'A3');

it('records a guardian.child_created audit entry without the password', function (): void {
    $parent = childParent();
    $child = app(CreateChildAccount::class)->handle($parent, childInput());

    $activity = Activity::query()->where('event', 'guardian.child_created')->sole();
    expect($activity->causer_id)->toBe($parent->id)
        ->and($activity->subject_id)->toBe($child->id)
        ->and((string) json_encode($activity->properties))->not->toContain('ChildPassword1!')
        ->and($activity->properties->get('after')['username'])->toBe('little-ali');
})->group('T-P1-05', 'A4', 'PRD-22');

it('rejects a duplicate username case-insensitively without partial records', function (): void {
    User::factory()->create(['username' => 'little-ali']);

    expect(fn () => app(CreateChildAccount::class)->handle(childParent(), childInput()))
        ->toThrow(ValidationException::class);
    expect(GuardianChildLink::query()->count())->toBe(0);
})->group('T-P1-05', 'A1');
