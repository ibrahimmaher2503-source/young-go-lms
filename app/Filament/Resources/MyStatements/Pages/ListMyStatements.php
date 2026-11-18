<?php

declare(strict_types=1);

namespace App\Filament\Resources\MyStatements\Pages;

use App\Filament\Resources\MyStatements\MyStatementsResource;
use App\Filament\Shared\Pages\WorkspaceListRecords;

final class ListMyStatements extends WorkspaceListRecords
{
    protected static string $resource = MyStatementsResource::class;
}
