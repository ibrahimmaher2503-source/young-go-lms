<?php

declare(strict_types=1);

use App\Domain\Audit\AuditLogger;
use App\Filament\Resources\Audit\AuditLogResource;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;

uses(RefreshDatabase::class);

it('requires audit view permission and exposes no audit mutations', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->withoutVite();

    $url = route('filament.admin.resources.audit.audit-logs.index');
    $this->get($url)->assertRedirect('/admin/login');

    expect(AuditLogResource::canViewAny())->toBeFalse();
    $denied = User::factory()->create();
    $denied->givePermissionTo('panel.admin.access');
    $this->actingAs($denied)->get($url)->assertForbidden();
    expect(AuditLogResource::canViewAny())->toBeFalse();
    $allowed = User::factory()->create();
    $allowed->givePermissionTo('panel.admin.access', 'audit.view');
    app(AuditLogger::class)->record('audit-visible-event', $allowed, $allowed->id);
    activity('application')->event('non-audit-event')->log('Non-audit activity');
    $this->actingAs($allowed)->get($url)->assertOk()
        ->assertSee('audit-visible-event')
        ->assertDontSee('non-audit-event');
    expect(AuditLogResource::canViewAny())->toBeTrue()
        ->and(AuditLogResource::canCreate())->toBeFalse()
        ->and(AuditLogResource::canEdit(new Activity))->toBeFalse()
        ->and(AuditLogResource::canDelete(new Activity))->toBeFalse()
        ->and(AuditLogResource::canDeleteAny())->toBeFalse();
})->group('T-P1-04', 'A3');
