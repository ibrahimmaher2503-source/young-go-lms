<?php

declare(strict_types=1);

namespace App\Filament\Resources\Audit\Pages;

use App\Filament\Resources\Audit\AuditLogResource;
use Filament\Resources\Pages\ListRecords;

final class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;
}
