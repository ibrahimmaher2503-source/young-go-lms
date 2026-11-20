<?php

declare(strict_types=1);

// T-P10-07 | PRD §25, I01 | A1 legacy enrollment reconciliation

use App\Domain\Entitlements\Actions\GrantEntitlement;
use App\Domain\Entitlements\Entitlement;
use App\Domain\Entitlements\EntitlementSource;
use App\Domain\Entitlements\Queries\LegacyMigrationEntitlementStats;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('reconciles only legacy grants and preserves their recorded access dates', function (): void {
    $learner = User::factory()->create();
    $course = Course::query()->create([
        'title' => ['en' => 'Legacy course', 'ar' => 'دورة سابقة'],
        'slug_en' => 'legacy-course',
        'slug_ar' => 'legacy-course',
        'status' => 'published',
        'access_mode' => 'paid',
    ]);
    $starts = Carbon::parse('2024-09-01T08:00:00Z');
    $ends = Carbon::parse('2024-10-01T08:00:00Z');
    $grant = app(GrantEntitlement::class);
    $dated = $grant($learner, $course, EntitlementSource::LegacyMigration, 'legacy:enrol:41', $starts, $ends, reason: 'Import provenance');
    $lifetime = $grant($learner, $course, EntitlementSource::LegacyMigration, 'legacy:enrol:52', $starts);
    $purchase = $grant($learner, $course, EntitlementSource::Purchase, 'legacy:enrol:41', $starts);
    $missingId = max($dated->id, $lifetime->id, $purchase->id) + 1;
    $stats = app(LegacyMigrationEntitlementStats::class);

    expect($stats->countBySourceRefPrefix())->toBe(2)
        ->and($stats->countBySourceRefPrefix('legacy:enrol:4'))->toBe(1)
        ->and($stats->existingIds([]))->toBe([])
        ->and($stats->existingIds([$dated->id, $purchase->id, $missingId]))->toBe([$dated->id])
        ->and($stats->findSummaryById($purchase->id))->toBeNull()
        ->and($stats->findSummaryById($missingId))->toBeNull()
        ->and($stats->findSummaryById($dated->id))->toBe([
            'id' => $dated->id,
            'learner_id' => $learner->id,
            'course_id' => $course->id,
            'source_type' => EntitlementSource::LegacyMigration->value,
            'source_ref' => 'legacy:enrol:41',
            'starts_at' => '2024-09-01T08:00:00+00:00',
            'ends_at' => '2024-10-01T08:00:00+00:00',
            'status' => Entitlement::ACTIVE,
        ])
        ->and($stats->findSummaryById($lifetime->id))->toHaveKey('ends_at', null);
})->group('T-P10-07', 'A1');
