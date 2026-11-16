<?php

declare(strict_types=1);

use App\Filament\Resources\Audit\AuditLogResource;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Spatie\Activitylog\Models\Activity;

it('requires audit view permission and exposes no audit mutations', function () {
    Gate::define('audit.view', static fn (User $user): bool => $user->getKey() === 1);

    expect(AuditLogResource::canViewAny())->toBeFalse();
    $this->actingAs((new User)->forceFill(['id' => 2]));
    expect(AuditLogResource::canViewAny())->toBeFalse();
    $this->actingAs((new User)->forceFill(['id' => 1]));
    expect(AuditLogResource::canViewAny())->toBeTrue()
        ->and(AuditLogResource::canCreate())->toBeFalse()
        ->and(AuditLogResource::canEdit(new Activity))->toBeFalse()
        ->and(AuditLogResource::canDelete(new Activity))->toBeFalse()
        ->and(AuditLogResource::canDeleteAny())->toBeFalse();
})->group('T-P1-04', 'A3');
