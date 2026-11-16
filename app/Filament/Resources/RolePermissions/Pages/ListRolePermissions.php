<?php

declare(strict_types=1);

namespace App\Filament\Resources\RolePermissions\Pages;

use App\Filament\Resources\RolePermissions\RolePermissionResource;
use Filament\Resources\Pages\ListRecords;

final class ListRolePermissions extends ListRecords
{
    protected static string $resource = RolePermissionResource::class;
}
