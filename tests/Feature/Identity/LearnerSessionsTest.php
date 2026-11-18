<?php

declare(strict_types=1);

use App\Domain\Identity\Actions\ManageLearnerSessions;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('signs out the oldest learner session at the configured concurrent limit', function (): void {
    $this->seed(RolePermissionSeeder::class);
    $learner = User::factory()->create(['locale' => 'en'])->assignRole('student');
    $login = function () use ($learner): array {
        session()->flush();
        app('auth')->forgetGuards();
        $this->postJson('/login', ['login' => $learner->email, 'password' => 'password'])->assertNoContent();

        return session()->all();
    };
    $first = $login();
    $this->travel(1)->seconds();
    $second = $login();
    $this->travel(1)->seconds();
    $third = $login();

    foreach ([$second, $third] as $active) {
        session()->flush();
        app('auth')->forgetGuards();
        $this->withSession($active)->postJson('/email/verification-notification')->assertNoContent();
    }
    session()->flush();
    app('auth')->forgetGuards();
    $this->withSession($first)->postJson('/email/verification-notification')
        ->assertUnauthorized()->assertJsonPath('message', __('auth.learner_session_ended'));
    $this->assertGuest();
    expect(Activity::query()->where('event', 'learner.session_evicted')->count())->toBe(1);
    $this->withoutVite();
    app('auth')->forgetGuards();
    $this->withSession($first)->get('/home')->assertRedirect('/login');
    $this->get('/login')->assertOk()->assertSee(__('auth.learner_session_ended'));
})->group('T-P4-07', 'A1', 'YG-GAP-SEC-001');

it('registers an existing browser session once when its initial request is retried', function (): void {
    $learner = User::factory()->create();
    $sessions = app(ManageLearnerSessions::class);
    $first = $sessions->start($learner, 'existing-browser-session');

    expect($sessions->start($learner, 'existing-browser-session'))->toBe($first)
        ->and(DB::table('user_sessions')->count())->toBe(1);

    $sessions->end($learner, $first);
    expect($sessions->start($learner, 'existing-browser-session'))->toBe($first)
        ->and($sessions->touch($learner, $first))->toBeFalse();
})->group('T-P4-07', 'A1', 'YG-GAP-SEC-001');
