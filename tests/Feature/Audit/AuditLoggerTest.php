<?php

declare(strict_types=1);

use App\Domain\Audit\AuditLogger;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('T-P1-04 A1 redacts sensitive values recursively from before, after and context', function (): void {
    $actor = User::factory()->create();
    $subject = User::factory()->create();

    app(AuditLogger::class)->record(
        event: 'user.updated',
        subject: $subject,
        actorId: $actor->id,
        before: [
            'password' => 'secret-password',
            'profile' => [
                'refreshToken' => 'secret-refresh-token',
                'id-document' => 'secret-id-document',
            ],
        ],
        after: [
            'finance' => [
                'bank_account_number' => 'secret-bank-account',
            ],
            'payout_details' => 'secret-payout-details',
        ],
        context: [
            'authorization' => 'secret-authorization',
            'untrusted_object' => (object) ['access_token' => 'secret-object-token'],
        ],
    );

    $properties = Activity::query()->latest('id')->firstOrFail()->properties->toArray();

    expect($properties['before']['password'])->toBe('[REDACTED]')
        ->and($properties['before']['profile']['refreshToken'])->toBe('[REDACTED]')
        ->and($properties['before']['profile']['id-document'])->toBe('[REDACTED]')
        ->and($properties['after']['finance']['bank_account_number'])->toBe('[REDACTED]')
        ->and($properties['after']['payout_details'])->toBe('[REDACTED]')
        ->and($properties['context']['authorization'])->toBe('[REDACTED]')
        ->and($properties['context']['untrusted_object'])->toBe('[REDACTED]');

    $storedProperties = json_encode($properties, JSON_THROW_ON_ERROR);

    foreach ([
        'secret-password',
        'secret-refresh-token',
        'secret-id-document',
        'secret-bank-account',
        'secret-payout-details',
        'secret-authorization',
        'secret-object-token',
    ] as $secret) {
        expect($storedProperties)->not->toContain($secret);
    }
})->group('T-P1-04', 'A1');

it('T-P1-04 A2 records the actor and acting_for context', function (): void {
    $actor = User::factory()->create();
    $subject = User::factory()->create();
    $actingFor = User::factory()->create();

    app(AuditLogger::class)->record(
        event: 'guardian.child_created',
        subject: $subject,
        actorId: $actor->id,
        context: ['acting_for' => ['type' => User::class, 'id' => $actingFor->id]],
    );

    $activity = Activity::query()->latest('id')->firstOrFail();

    expect($activity->causer_id)->toBe($actor->id)
        ->and($activity->subject_id)->toBe($subject->id)
        ->and($activity->subject_type)->toBe(User::class)
        ->and($activity->properties->get('context')['acting_for'])
        ->toBe(['type' => User::class, 'id' => $actingFor->id]);
})->group('T-P1-04', 'A2', 'YG-GAP-FAM-004');
