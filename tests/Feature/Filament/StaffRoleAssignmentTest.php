<?php

declare(strict_types=1);

use App\Domain\Identity\Actions\AssignStaffRoles;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('records the actor and changed roles on authorized assignment', function () {
    $this->seed(RolePermissionSeeder::class);
    $actor = User::factory()->create()->assignRole('super_admin');
    $target = User::factory()->create()->assignRole('student');

    app(AssignStaffRoles::class)->handle($actor, $target, ['student', 'content_manager']);

    expect($target->fresh()->getRoleNames()->sort()->values()->all())->toBe(['content_manager', 'student']);
    $entry = Activity::query()->where('event', 'user.roles_changed')->sole();
    expect($entry->causer_id)->toBe($actor->id)
        ->and($entry->subject_id)->toBe($target->id)
        ->and($entry->properties['before']['roles'])->toBe(['student'])
        ->and($entry->properties['after']['roles'])->toBe(['content_manager', 'student']);
})->group('T-P1-08', 'A2');

it('denies unprivileged assignment and reserves super-admin provisioning', function () {
    $this->seed(RolePermissionSeeder::class);
    $actor = User::factory()->create()->assignRole('admin');
    $superAdmin = User::factory()->create()->assignRole('super_admin');
    $target = User::factory()->create()->assignRole('student');
    $action = app(AssignStaffRoles::class);

    expect(fn () => $action->handle($actor, $target, ['finance']))->toThrow(AuthorizationException::class)
        ->and(fn () => $action->handle($superAdmin, $target, ['super_admin']))->toThrow(AuthorizationException::class)
        ->and(fn () => $action->handle($superAdmin, $superAdmin, ['student']))->toThrow(AuthorizationException::class);

    expect($target->fresh()->getRoleNames()->all())->toBe(['student']);
    expect(Activity::query()->where('event', 'user.roles_changed')->count())->toBe(0);
})->group('T-P1-08', 'A2');
