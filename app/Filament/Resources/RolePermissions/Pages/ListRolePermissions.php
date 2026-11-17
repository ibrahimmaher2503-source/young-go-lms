<?php

declare(strict_types=1);

namespace App\Filament\Resources\RolePermissions\Pages;

use App\Filament\Resources\RolePermissions\RolePermissionResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;

final class ListRolePermissions extends WorkspaceListRecords
{
    protected static string $resource = RolePermissionResource::class;
}
