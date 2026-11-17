<?php

declare(strict_types=1);

namespace App\Filament\Resources\Audit\Pages;

use App\Filament\Resources\Audit\AuditLogResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;

final class ListAuditLogs extends WorkspaceListRecords
{
    protected static string $resource = AuditLogResource::class;
}
