<?php

declare(strict_types=1);

use App\Domain\Settings\Actions\EnableGatedFlag;
use App\Domain\Settings\AiSettings;
use App\Domain\Settings\GeneralSettings;
use App\Domain\Settings\LearningSettings;
use App\Domain\Settings\MarketsSettings;
use App\Domain\Settings\NotificationsSettings;
use App\Domain\Settings\PlatformFlag;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Pennant\Feature;

beforeEach(function () {
    config([
        'database.default' => 'flags_test',
        'database.connections.flags_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'foreign_key_constraints' => true],
    ]);

    $this->artisan('migrate', ['--force' => true])->assertSuccessful();
});

it('defaults all platform flags to disabled in the global scope', function () {
    expect(PlatformFlag::cases())->toHaveCount(7);
    foreach (PlatformFlag::cases() as $flag) {
        expect(Feature::for(null)->active($flag->value))->toBeFalse();
    }
})->group('T-P0-07', 'A1', 'ED-015', 'YG-GAP-OPS-005');

it('rejects a blank approval reference even from an authorized actor', function () {
    $actor = User::factory()->create();
    Gate::define(EnableGatedFlag::ABILITY, static fn (User $user): bool => $user->is($actor));

    expect(fn () => app(EnableGatedFlag::class)->handle(PlatformFlag::AiMinors, $actor, '  '))
        ->toThrow(InvalidArgumentException::class);
    expect(Feature::for(null)->active('ai.minors'))->toBeFalse();
    expect(DB::table('flag_approvals')->count())->toBe(0);
})->group('T-P0-07', 'A1', 'ED-015', 'YG-GAP-OPS-005');

it('rejects an unapproved actor even with an approval reference', function () {
    $actor = User::factory()->create();

    expect(fn () => app(EnableGatedFlag::class)->handle(PlatformFlag::AiMinors, $actor, 'test-ref'))
        ->toThrow(AuthorizationException::class);
    expect(Feature::for(null)->active('ai.minors'))->toBeFalse();
    expect(DB::table('flag_approvals')->count())->toBe(0);
})->group('T-P0-07', 'A1', 'ED-015', 'YG-GAP-OPS-005');

it('records the reference and actor when an authorized actor enables a gated flag', function () {
    $actor = User::factory()->create();
    Gate::define(EnableGatedFlag::ABILITY, static fn (User $user): bool => $user->is($actor));

    app(EnableGatedFlag::class)->handle(PlatformFlag::AiAssistant, $actor, ' test-ref ');

    expect(Feature::for(null)->active('ai.assistant'))->toBeTrue();
    expect(DB::table('flag_approvals')->where('flag', 'ai.assistant')->where('approved_by', $actor->getKey())->value('approval_ref'))
        ->toBe('test-ref');
})->group('T-P0-07', 'A1', 'ED-015', 'YG-GAP-OPS-005');

it('persists typed settings and preserves unconfigured ceilings', function () {
    expect(app(GeneralSettings::class)->default_locale)->toBe('ar');
    expect(app(MarketsSettings::class)->default_market)->toBe('EG');
    expect(app(LearningSettings::class)->required_lesson_percent)->toBeNull();
    expect(app(NotificationsSettings::class)->whatsapp_provider)->toBeNull();

    $ai = app(AiSettings::class);
    expect($ai->questions_per_student_day)->toBe(20)
        ->and($ai->monthly_budget_minor)->toBeNull();
    $ai->questions_per_student_day = 15;
    $ai->save();

    expect(app(AiSettings::class)->refresh()->questions_per_student_day)->toBe(15);
})->group('T-P0-07', 'ED-015');
